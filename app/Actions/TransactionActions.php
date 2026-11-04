<?php

namespace App\Actions;

use App\Models\Account;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RecurringGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Altas, cambios y bajas de movimientos. La usan el controlador de siempre y la
 * sincronización de cambios hechos sin conexión (SyncController), así las
 * reglas viven en un solo lugar.
 */
class TransactionActions
{
    public function __construct(private RecurringGenerator $generator) {}

    public function create(User $user, array $input, ?UploadedFile $photo = null): Transaction
    {
        if (($input['type'] ?? null) === 'transfer') {
            $data = $this->validateTransfer($input, $user);
            [$from, $to] = $this->transferAccounts($user, $data);

            return $user->transactions()->create($this->transferAttributes($data, $from, $to));
        }

        $data = $this->validateExpenseIncome(['photo' => $photo] + $input, $user);
        $account = Account::where('user_id', $user->id)->findOrFail($data['account_id']);

        $attributes = $this->expenseIncomeAttributes($data, $account);
        $installments = (int) ($data['installments'] ?? 1);
        $fixed = (bool) ($data['is_fixed'] ?? false);
        $installmentAmount = null;

        if ($installments > 1) {
            $installmentAmount = $this->checkInstallments($data, $account, $installments, $fixed);
            // La cuota 1 se lleva los centavos del redondeo: 100 en 3 = 33,34 + 33,33 + 33,33.
            $attributes['amount'] = round((float) $data['amount'] - ($installments - 1) * $installmentAmount, 2);
        }

        $attributes['photo_path'] = $photo
            ? $photo->store('transactions/'.$user->id, 'public')
            : null;

        return DB::transaction(function () use ($user, $attributes, $installments, $installmentAmount, $fixed) {
            $transaction = $user->transactions()->create($attributes);

            if ($installmentAmount !== null) {
                $this->startSeries($user, $transaction, 'installments', $installmentAmount, $installments);
            } elseif ($fixed) {
                $this->startSeries($user, $transaction, 'fixed', (float) $transaction->amount);
            }

            return $transaction;
        });
    }

    public function update(User $user, Transaction $transaction, array $input, ?UploadedFile $photo = null): Transaction
    {
        if (($input['type'] ?? null) === 'transfer') {
            $data = $this->validateTransfer($input, $user);
            [$from, $to] = $this->transferAccounts($user, $data);

            // Si venía con foto (era gasto/ingreso), se descarta al pasar a transferencia.
            $this->deletePhoto($transaction);
            // Y si era un gasto fijo, deja de repetirse.
            if ($transaction->isFixed()) {
                $transaction->recurring->update(['is_active' => false]);
            }

            $transaction->update($this->transferAttributes($data, $from, $to));

            return $transaction;
        }

        $data = $this->validateExpenseIncome(['photo' => $photo] + $input, $user);
        $account = Account::where('user_id', $user->id)->findOrFail($data['account_id']);

        $attributes = $this->expenseIncomeAttributes($data, $account);

        if ($photo) {
            $this->deletePhoto($transaction);
            $attributes['photo_path'] = $photo->store('transactions/'.$user->id, 'public');
        }
        // Si no viene foto nueva, se conserva la existente (no se toca photo_path).

        return DB::transaction(function () use ($user, $transaction, $attributes, $data) {
            $previousDate = $transaction->transaction_date->copy();
            $wasFixed = $transaction->isFixed();
            // Un cliente viejo (o una operación encolada antes de esto) no manda el
            // campo: entonces no se toca la serie.
            $wantsFixed = array_key_exists('is_fixed', $data) && $data['is_fixed'] !== null
                ? (bool) $data['is_fixed']
                : $wasFixed;

            $transaction->update($attributes);

            // Una cuota se corrige sola; la cantidad de cuotas no se edita.
            if ($transaction->installment_number) {
                return $transaction;
            }

            if ($wasFixed && ! $wantsFixed) {
                // Se dio de baja: esta fue la última repetición. Las que ya estaban
                // generadas por adelantado (del mes en curso) y vienen después, se van.
                $this->futureOccurrences($transaction)
                    ->whereDate('transaction_date', '>', $transaction->transaction_date)
                    ->delete();
                $transaction->recurring->update(['is_active' => false]);
            } elseif ($wasFixed) {
                $this->updateFixedSeries($transaction, $previousDate);
            } elseif ($wantsFixed) {
                $this->startSeries($user, $transaction, 'fixed', (float) $transaction->amount);
            }

            return $transaction;
        });
    }

    public function delete(User $user, Transaction $transaction): void
    {
        $this->deletePhoto($transaction);

        DB::transaction(function () use ($transaction) {
            $series = $transaction->recurring;
            $transaction->delete();

            // Se borró el gasto que le dio origen antes de que se repitiera de verdad
            // (lo que queda, si queda algo, es la repetición adelantada de este mes):
            // era un error de carga, así que no quedan cuotas ni repeticiones fantasma.
            if ($series && $series->kind !== 'manual'
                && ! $series->transactions()->whereDate('transaction_date', '<=', today())->exists()) {
                $series->transactions()->delete();
                $series->delete();
            }
        });
    }

    /**
     * Valida una compra en cuotas y devuelve el importe de cada cuota.
     */
    private function checkInstallments(array $data, Account $account, int $installments, bool $fixed): float
    {
        $error = match (true) {
            $data['type'] !== 'expense' => 'Solo un gasto se puede pagar en cuotas.',
            ! $account->accepts_installments => 'Esta cuenta no acepta cuotas.',
            $fixed => 'Un gasto en cuotas no puede ser también gasto fijo.',
            default => null,
        };
        if ($error) {
            throw ValidationException::withMessages(['installments' => $error]);
        }

        $amount = round((float) $data['amount'] / $installments, 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['installments' => 'El monto es muy chico para tantas cuotas.']);
        }

        return $amount;
    }

    /**
     * Arma la serie mensual de un gasto fijo o en cuotas a partir del primer
     * gasto, y genera ya las repeticiones vencidas (si se cargó con fecha vieja).
     */
    private function startSeries(User $user, Transaction $transaction, string $kind, float $amount, ?int $installments = null): RecurringTransaction
    {
        $date = $transaction->transaction_date->copy()->startOfDay();

        $series = new RecurringTransaction([
            'account_id' => $transaction->account_id,
            'category_id' => $transaction->category_id,
            'type' => $transaction->type,
            'kind' => $kind,
            'amount' => $amount,
            'currency_code' => $transaction->currency_code,
            'exchange_rate' => $transaction->exchange_rate,
            'note' => $transaction->note,
            'frequency' => 'monthly',
            'interval' => 1,
            'anchor_day' => $date->day,
            'installments_total' => $installments,
            'next_installment' => $installments ? 2 : null,
            'last_generated_at' => $date,
            'is_active' => true,
        ]);
        $series->next_run_date = $series->advance($date);

        if ($installments) {
            $end = $date;
            for ($i = 1; $i < $installments; $i++) {
                $end = $series->advance($end);
            }
            $series->end_date = $end;
        }

        $user->recurringTransactions()->save($series);

        $transaction->update([
            'recurring_transaction_id' => $series->id,
            'installment_number' => $installments ? 1 : null,
            'installments_total' => $installments,
        ]);
        $transaction->setRelation('recurring', $series);

        $this->generator->process($series);

        return $series;
    }

    /**
     * Un gasto fijo editado que sigue siendo fijo: los cambios valen para las
     * repeticiones que vienen, no para las ya generadas.
     */
    private function updateFixedSeries(Transaction $transaction, Carbon $previousDate): void
    {
        $series = $transaction->recurring;

        $series->fill([
            'account_id' => $transaction->account_id,
            'category_id' => $transaction->category_id,
            'type' => $transaction->type,
            'amount' => $transaction->amount,
            'currency_code' => $transaction->currency_code,
            'exchange_rate' => $transaction->exchange_rate,
            'note' => $transaction->note,
        ]);

        $date = $transaction->transaction_date->copy()->startOfDay();

        if ($date->isSameDay($previousDate)) {
            // Las repeticiones adelantadas (fecha futura) toman los datos nuevos en el
            // lugar, así no pierden el «pagado» si ya se pagaron por adelantado.
            $this->futureOccurrences($transaction)->update([
                'account_id' => $transaction->account_id,
                'category_id' => $transaction->category_id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'currency_code' => $transaction->currency_code,
                'exchange_rate' => $transaction->exchange_rate,
                'note' => $transaction->note,
            ]);
            $series->save();

            return;
        }

        // Se corrigió la fecha: las adelantadas se rehacen y la serie retoma desde
        // la última que queda.
        $this->futureOccurrences($transaction)->delete();
        $lastDate = Carbon::parse($series->transactions()->max('transaction_date') ?? $date)->startOfDay();

        // Si todavía no se repitió nunca, la serie se corre con ella.
        if ($series->transactions()->count() === 1) {
            $series->anchor_day = $date->day;
            $lastDate = $date;
        }

        $series->last_generated_at = $lastDate;
        $series->next_run_date = $series->advance($lastDate);
        $series->save();
        $this->generator->process($series);
    }

    /**
     * Repeticiones de la misma serie que todavía no llegaron (generadas por
     * adelantado para el mes en curso), sin contar la que se está editando.
     */
    private function futureOccurrences(Transaction $transaction)
    {
        return Transaction::where('recurring_transaction_id', $transaction->recurring_transaction_id)
            ->whereKeyNot($transaction->id)
            ->whereDate('transaction_date', '>', today());
    }

    private function validateExpenseIncome(array $input, User $user): array
    {
        return Validator::make($input, [
            'type' => ['required', Rule::in(['expense', 'income'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('user_id', $user->id)],
            'category_id' => ['required', Rule::exists('categories', 'id')->where('user_id', $user->id)],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['required', 'date'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'is_fixed' => ['nullable', 'boolean'],
            'installments' => ['nullable', 'integer', 'min:1', 'max:60'],
        ])->validate();
    }

    private function validateTransfer(array $input, User $user): array
    {
        return Validator::make($input, [
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('user_id', $user->id)],
            'to_account_id' => ['required', 'different:account_id', Rule::exists('accounts', 'id')->where('user_id', $user->id)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'transfer_amount' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['required', 'date'],
        ])->validate();
    }

    private function transferAccounts(User $user, array $data): array
    {
        return [
            Account::where('user_id', $user->id)->findOrFail($data['account_id']),
            Account::where('user_id', $user->id)->findOrFail($data['to_account_id']),
        ];
    }

    /**
     * Atributos comunes de un gasto/ingreso (sin la foto).
     */
    private function expenseIncomeAttributes(array $data, Account $account): array
    {
        return [
            'account_id' => $account->id,
            'category_id' => $data['category_id'],
            'type' => $data['type'],
            'amount' => $data['amount'],
            'currency_code' => $account->currency_code,
            'exchange_rate' => $data['exchange_rate'] ?? 1,
            'note' => $data['note'] ?? null,
            'transaction_date' => $data['transaction_date'],
            'to_account_id' => null,
            'transfer_amount' => null,
            'transfer_currency' => null,
        ];
    }

    private function transferAttributes(array $data, Account $from, Account $to): array
    {
        return [
            'account_id' => $from->id,
            'to_account_id' => $to->id,
            'category_id' => null,
            'type' => 'transfer',
            'amount' => $data['amount'],
            'currency_code' => $from->currency_code,
            'exchange_rate' => 1,
            'transfer_amount' => $data['transfer_amount'],
            'transfer_currency' => $to->currency_code,
            'note' => $data['note'] ?? null,
            'transaction_date' => $data['transaction_date'],
            'photo_path' => null,
        ];
    }

    private function deletePhoto(Transaction $transaction): void
    {
        if ($transaction->photo_path) {
            Storage::disk('public')->delete($transaction->photo_path);
        }
    }
}
