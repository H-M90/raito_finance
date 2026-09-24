<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditObserver
{
    public function created(Model $model): void
    {
        $this->write('created', $model, [], $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        $oldValues = Arr::only($model->getOriginal(), array_keys($changes));
        $this->write('updated', $model, $oldValues, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->write('deleted', $model, $model->getOriginal(), []);
    }

    public function restored(Model $model): void
    {
        $this->write('restored', $model, [], $model->getAttributes());
    }

    private function write(string $event, Model $model, array $old, array $new): void
    {
        if ($model instanceof AuditLog) {
            return;
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'old_values' => $this->redact($old) ?: null,
            'new_values' => $this->redact($new) ?: null,
            'reason' => (app()->runningInConsole() ? 'system' : null) ?? request()?->input('correction_reason') ?? request()?->input('cancellation_reason'),
            'ip_address' => app()->runningInConsole() ? null : request()?->ip(),
            'user_agent' => app()->runningInConsole() ? 'system/console' : mb_substr((string) request()?->userAgent(), 0, 1000),
            'created_at' => now(),
        ]);
    }

    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (preg_match('/password|secret|token|api[_-]?key|private[_-]?key/i', (string) $key)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}
