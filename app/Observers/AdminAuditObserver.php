<?php

namespace App\Observers;

use App\Models\AdminAudit;
use Illuminate\Database\Eloquent\Model;

/**
 * Registered against every plain-CRUD auditable model in
 * AppServiceProvider::boot() — User, Server, Provider, ConnectorInstance,
 * ServerGroup, SiteOption. Role/permission changes do NOT go through this:
 * Filament's relationship-field sync and Spatie's syncPermissions() both
 * write pivot tables directly, bypassing Eloquent model events entirely,
 * so those are logged from explicit afterSave()/afterCreate() hooks in
 * UserResource/RoleResource's own pages instead (see those classes).
 *
 * EXCLUDED_ATTRIBUTES covers two different reasons under one mechanism:
 * telemetry noise (last_check/last_raw_response/status/error_message —
 * fields the scheduler and the debug panel's Test button rewrite
 * constantly) and secret exposure (credentials/password/remember_token).
 * Applied as one flat list across every observed model rather than
 * per-model, so e.g. a manually-edited Server.status is excluded too —
 * a deliberate simplification, not an oversight; scoping it more finely
 * later is a small, isolated follow-up if it turns out to matter.
 */
class AdminAuditObserver
{
    protected const EXCLUDED_ATTRIBUTES = [
        'credentials', 'password', 'remember_token',
        'last_check', 'last_raw_response', 'status', 'error_message',
    ];

    public function created(Model $model): void
    {
        if (! auth()->check()) {
            return;
        }

        AdminAudit::record(
            'created',
            class_basename($model),
            $model->getKey(),
            $this->filtered($model->getAttributes())
        );
    }

    public function updated(Model $model): void
    {
        if (! auth()->check()) {
            return;
        }

        $changes = [];

        foreach ($model->getChanges() as $key => $new) {
            if ($key === $model->getUpdatedAtColumn()) {
                continue;
            }
            if (in_array($key, self::EXCLUDED_ATTRIBUTES, true)) {
                continue;
            }

            $changes[$key] = ['old' => $model->getOriginal($key), 'new' => $new];
        }

        if (empty($changes)) {
            return;
        }

        AdminAudit::record('updated', class_basename($model), $model->getKey(), $changes);
    }

    public function deleted(Model $model): void
    {
        if (! auth()->check()) {
            return;
        }

        AdminAudit::record(
            'deleted',
            class_basename($model),
            $model->getKey(),
            $this->filtered($model->getOriginal())
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function filtered(array $attributes): array
    {
        return collect($attributes)->except(self::EXCLUDED_ATTRIBUTES)->all();
    }
}
