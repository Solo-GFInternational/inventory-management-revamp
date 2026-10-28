<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Throwable;

/**
 * Settings > Audit trail: a read-only list of the activity log, newest
 * first: when, who ("System" when nobody was signed in), what was done,
 * what it was about and the IP address. Filters: user, type, date range.
 * Administrators only (see routes/web.php).
 *
 * Loading: one query for the entries, one for the people who did them, one
 * per kind of record on the page, and one each for the people, stock items
 * and types the entries mention by id (AuditTrail::names()).
 */
class AuditTrailController extends Controller
{
    public function index(Request $request)
    {
        $model = ActivitylogServiceProvider::determineActivityModel();
        $userType = (new User)->getMorphClass();

        $filters = [
            'user' => (string) $request->query('user', ''),
            'type' => (string) $request->query('type', ''),
            'from' => $this->date($request->query('from')),
            'until' => $this->date($request->query('until')),
        ];

        $entries = $model::query()
            ->with(['causer', 'subject'])
            ->when($filters['user'] === 'system', fn (Builder $query) => $query->whereNull('causer_id'))
            ->when(ctype_digit($filters['user']), fn (Builder $query) => $query
                ->where('causer_type', $userType)
                ->where('causer_id', (int) $filters['user']))
            ->when(in_array($filters['type'], [AuditTrail::LOG_SIGN_IN, AuditTrail::LOG_NAVIGATION], true),
                fn (Builder $query) => $query->where('log_name', $filters['type']))
            ->when($filters['type'] === AuditTrail::TYPE_CHANGES, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereNotIn('log_name', [AuditTrail::LOG_SIGN_IN, AuditTrail::LOG_NAVIGATION])
                ->orWhereNull('log_name')))
            ->when($filters['from'], fn (Builder $query, Carbon $from) => $query->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($filters['until'], fn (Builder $query, Carbon $until) => $query->where('created_at', '<', $until->copy()->addDay()->startOfDay()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // Everyone who has done something, plus "System" (in the view).
        $users = User::query()
            ->whereIn('id', $model::query()->where('causer_type', $userType)->select('causer_id'))
            ->orderBy('name')
            ->pluck('name', 'id');

        return view('settings.audit.index', [
            'entries' => $entries,
            'users' => $users,
            'types' => AuditTrail::TYPES,
            'filters' => [
                ...$filters,
                'from' => $filters['from']?->format('Y-m-d'),
                'until' => $filters['until']?->format('Y-m-d'),
            ],
            'hasFilters' => $filters['user'] !== '' || $filters['type'] !== '' || $filters['from'] || $filters['until'],
            'names' => AuditTrail::names($entries->items()),
        ]);
    }

    /** A Y-m-d query value as a date, or null when missing or invalid. */
    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }
    }
}
