<?php

namespace App\Http\Controllers;

use App\Actions\AccountActions;
use App\Actions\BudgetActions;
use App\Actions\CategoryActions;
use App\Actions\PreferencesActions;
use App\Actions\RecurringTransactionActions;
use App\Actions\ReminderActions;
use App\Actions\TransactionActions;
use App\Exceptions\UnresolvedSyncReference;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Reminder;
use App\Models\SyncOperation;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aplica los cambios que la app guardó en su cola mientras no tenía conexión.
 *
 * Recibe una lista ordenada de operaciones ({op_id, entity, action, id?,
 * temp_id?, data}) y las aplica de a una con las mismas Actions que usan los
 * controladores, así las reglas son idénticas con y sin conexión. Cada una
 * contesta por separado: un error de validación en una no frena a las demás,
 * salvo a las que dependen de algo que ella tenía que crear.
 *
 * Es idempotente por op_id: reenviar una operación ya aplicada no la repite.
 */
class SyncController extends Controller
{
    private const MAX_OPS = 100;

    // Campos de `data` que pueden traer el id temporal de algo creado sin conexión.
    private const REF_KEYS = ['account_id', 'to_account_id', 'category_id', 'parent_id'];

    /** entidad => [modelo, Actions, campo del archivo adjunto] */
    private const ENTITIES = [
        'transaction' => [Transaction::class, TransactionActions::class, 'photo'],
        'account' => [Account::class, AccountActions::class, null],
        'category' => [Category::class, CategoryActions::class, 'image'],
        'budget' => [Budget::class, BudgetActions::class, null],
        'recurring' => [RecurringTransaction::class, RecurringTransactionActions::class, null],
        'reminder' => [Reminder::class, ReminderActions::class, null],
        'preferences' => [null, PreferencesActions::class, null],
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $ops = json_decode((string) $request->input('ops', '[]'), true);

        if (! is_array($ops) || count($ops) > self::MAX_OPS) {
            return response()->json(['message' => 'Lote de cambios inválido.'], 422);
        }

        /** @var User $user */
        $user = Auth::user();

        $results = [];
        foreach ($ops as $op) {
            $results[] = $this->apply($user, is_array($op) ? $op : [], $request);
        }

        return response()->json(['results' => $results]);
    }

    private function apply(User $user, array $op, Request $request): array
    {
        $opId = (string) ($op['op_id'] ?? '');
        $entity = $op['entity'] ?? null;
        $action = $op['action'] ?? null;
        $base = ['op_id' => $opId, 'temp_id' => $op['temp_id'] ?? null];

        if (! preg_match('/^[0-9a-f-]{36}$/i', $opId) || ! isset(self::ENTITIES[$entity])
            || ! in_array($action, ['create', 'update', 'delete'], true)) {
            return $base + ['status' => 'error', 'errors' => ['op' => ['Operación inválida.']]];
        }

        // Ya aplicada en un envío anterior (la respuesta no llegó a la app).
        if ($done = $this->findDone($user, $opId)) {
            return $base + ['status' => 'ok', 'id' => $done->real_id, 'duplicate' => true];
        }

        try {
            $id = DB::transaction(fn () => $this->run($user, $op, $request->file("files.$opId")));
        } catch (UnresolvedSyncReference) {
            // Depende de algo que todavía no se pudo crear: queda en la cola.
            return $base + ['status' => 'blocked'];
        } catch (ValidationException $e) {
            return $base + ['status' => 'error', 'errors' => $e->errors()];
        } catch (UniqueConstraintViolationException) {
            // La misma operación llegó dos veces a la vez (dos pestañas).
            $done = $this->findDone($user, $opId);

            return $base + ['status' => 'ok', 'id' => $done?->real_id, 'duplicate' => true];
        }

        return $base + ['status' => 'ok', 'id' => $id];
    }

    /**
     * Aplica la operación y la registra como hecha. Devuelve el id real.
     */
    private function run(User $user, array $op, ?UploadedFile $file): ?int
    {
        [$modelClass, $actionsClass, $fileField] = self::ENTITIES[$op['entity']];
        $actions = app($actionsClass);
        $action = $op['action'];

        // Presupuestos no tiene `update` (crear reemplaza) y preferencias solo `update`.
        if (! method_exists($actions, $modelClass === null ? 'update' : $action)) {
            throw ValidationException::withMessages(['op' => 'Operación inválida.']);
        }

        $data = $this->resolveRefs($user, is_array($op['data'] ?? null) ? $op['data'] : []);
        // Las Actions sin adjunto no declaran el parámetro; PHP ignora el extra.
        $file = $fileField ? $file : null;

        if ($modelClass === null) {
            // Preferencias: no tienen id, se actualiza el usuario.
            $actions->update($user, $data);
            $realId = $user->id;
        } elseif ($action === 'create') {
            $realId = $actions->create($user, $data, $file)->id;
        } else {
            $model = $modelClass::where('user_id', $user->id)->find($this->resolveId($user, $op['id'] ?? null));

            if (! $model) {
                // Borrar algo que ya no está es lo que se quería; editarlo, no.
                if ($action === 'delete') {
                    $realId = null;
                } else {
                    throw ValidationException::withMessages(['id' => 'Ya no existe: se borró desde otro dispositivo.']);
                }
            } elseif ($action === 'update') {
                $actions->update($user, $model, $data, $file);
                $realId = $model->id;
            } else {
                $actions->delete($user, $model);
                $realId = $model->id;
            }
        }

        SyncOperation::create([
            'user_id' => $user->id,
            'op_id' => $op['op_id'],
            'entity' => $op['entity'],
            'action' => $action,
            'temp_id' => $action === 'create' ? ($op['temp_id'] ?? null) : null,
            'real_id' => $realId,
        ]);

        return $realId;
    }

    private function resolveRefs(User $user, array $data): array
    {
        foreach (self::REF_KEYS as $key) {
            if (isset($data[$key])) {
                $data[$key] = $this->resolveId($user, $data[$key]);
            }
        }

        return $data;
    }

    /**
     * "tmp:<uuid>" => id real de lo que se creó con ese id temporal.
     */
    private function resolveId(User $user, mixed $id): mixed
    {
        if (! is_string($id) || ! str_starts_with($id, 'tmp:')) {
            return $id;
        }

        $realId = SyncOperation::where('user_id', $user->id)
            ->where('temp_id', $id)
            ->whereNotNull('real_id')
            ->value('real_id');

        if ($realId === null) {
            throw new UnresolvedSyncReference($id);
        }

        return $realId;
    }

    private function findDone(User $user, string $opId): ?SyncOperation
    {
        return SyncOperation::where('user_id', $user->id)->where('op_id', $opId)->first();
    }
}
