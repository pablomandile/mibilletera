<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\Reminder;
use Illuminate\Console\Command;

class DispatchReminders extends Command
{
    protected $signature = 'reminders:dispatch';

    protected $description = 'Crea alertas (campanita) para los recordatorios activos cuya hora ya pasó hoy.';

    public function handle(): int
    {
        $now = now();
        $weekday = $now->dayOfWeek; // 0=domingo .. 6=sábado
        $currentTime = $now->format('H:i');

        $created = 0;

        Reminder::where('enabled', true)->chunkById(200, function ($reminders) use ($weekday, $currentTime, $now, &$created) {
            foreach ($reminders as $reminder) {
                $days = $reminder->days ?: [];

                // Días específicos: si no corresponde hoy, saltar.
                if (! empty($days) && ! in_array($weekday, $days, true)) {
                    continue;
                }

                // ¿Ya pasó la hora?
                if ($currentTime < substr((string) $reminder->time, 0, 5)) {
                    continue;
                }

                // ¿Ya se generó una alerta hoy para este recordatorio?
                $already = Alert::where('reminder_id', $reminder->id)
                    ->whereDate('created_at', $now->toDateString())
                    ->exists();

                if ($already) {
                    continue;
                }

                Alert::create([
                    'user_id' => $reminder->user_id,
                    'reminder_id' => $reminder->id,
                    'title' => $reminder->title,
                    'body' => 'Es hora de registrar tus movimientos.',
                ]);

                $created++;
            }
        });

        $this->info("Alertas generadas: {$created}.");

        return self::SUCCESS;
    }
}
