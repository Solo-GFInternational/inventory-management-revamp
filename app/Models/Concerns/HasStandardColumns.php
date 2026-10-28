<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Standard business-table columns: active_yn, created_by, updated_by.
 *
 * created_by / updated_by are filled from the signed-in user; they stay null
 * when there is no user (seeders, console, self-registration).
 *
 * Every create, update and delete is also written to the activity log for
 * Settings > Audit trail: only the columns that changed, old → new. Turning
 * active_yn off or on is logged as "deactivated" / "reactivated".
 */
trait HasStandardColumns
{
    use LogsActivity {
        attributeValuesToBeLogged as logsActivityValuesToBeLogged;
    }

    public static function bootHasStandardColumns()
    {
        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by ??= Auth::id();
                $model->updated_by ??= Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }

    public function initializeHasStandardColumns()
    {
        $this->mergeCasts(['active_yn' => 'boolean']);
    }

    public function scopeActive($query)
    {
        return $query->where($this->qualifyColumn('active_yn'), true);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * What the audit trail records: every column except secrets and
     * bookkeeping (the causer already says who, the entry says when), only
     * those that changed, and nothing when only bookkeeping changed. Log
     * name is the model, e.g. "inventory".
     */
    public function getActivitylogOptions(): LogOptions
    {
        $bookkeeping = ['id', 'created_at', 'updated_at', 'created_by', 'updated_by', 'remember_token'];

        return LogOptions::defaults()
            ->logAll()
            ->logExcept([...$bookkeeping, 'password', 'change_log'])
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly($bookkeeping)
            ->dontSubmitEmptyLogs()
            ->useLogName(Str::snake(class_basename(static::class)));
    }

    /**
     * The values to log. On an update, only the columns this save actually
     * wrote: columns filled by a database default (e.g. active_yn on a row
     * just created) aren't in the model's original values, so the package
     * would otherwise report them as changed from null.
     */
    public function attributeValuesToBeLogged(string $processingEvent): array
    {
        $properties = $this->logsActivityValuesToBeLogged($processingEvent);

        if ($processingEvent === 'updated') {
            $written = array_keys($this->getChanges());

            foreach (['attributes', 'old'] as $key) {
                $properties[$key] = array_intersect_key($properties[$key] ?? [], array_flip($written));
            }
        }

        return $properties;
    }

    /**
     * An update that turns active_yn off (or back on) is a deactivation
     * (reactivation), whatever else changed with it.
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        if ($eventName !== 'updated') {
            return;
        }

        $new = data_get($activity->properties, 'attributes', []);

        if (! array_key_exists('active_yn', $new)) {
            return;
        }

        $event = filter_var($new['active_yn'], FILTER_VALIDATE_BOOLEAN) ? 'reactivated' : 'deactivated';

        $activity->event = $event;
        $activity->description = $event;
    }
}
