<?php

namespace App\Support;

use Spatie\Activitylog\Models\Activity;

/**
 * The activity-log model (config/activitylog.php 'activity_model'): spatie's
 * own, plus the request's IP address stamped on every entry made during a
 * web request, whoever wrote it (a model change, a sign-in, an approval
 * logged with activity()). See AuditTrail::tagIp().
 */
class AuditTrailEntry extends Activity
{
    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            AuditTrail::tagIp($entry);
        });
    }
}
