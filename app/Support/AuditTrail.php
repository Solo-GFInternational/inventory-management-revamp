<?php

namespace App\Support;

use App\Models\Inventory;
use App\Models\Item;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * Settings > Audit trail: who did what, when and from where, read from the
 * activity log (spatie/laravel-activitylog).
 *
 * Three jobs:
 *   - signedIn() / signedOut() / failedSignIn() / pageView()   entries for
 *                        signing in and out (log sign_in) and for pages
 *                        opened by signed-in users (log navigation)
 *   - tagIp($activity)   stamps the request's IP address on every entry made
 *                        during a web request, as properties['ip'] (see
 *                        AuditTrailEntry, the activity model)
 *   - who() / action() / about() / ip() / type() / changes()   the list's
 *                        plain-English columns
 *
 * Record changes are logged by HasStandardColumns (log name = the model,
 * e.g. "inventory"); anything else written with activity() shows its own
 * description.
 */
class AuditTrail
{
    /** The activity-log property holding the request's IP address. */
    public const IP_PROPERTY = 'ip';

    /** Who did an entry with no causer: a command, a seeder, a guest. */
    public const SYSTEM = 'System';

    /** Log names: signing in and out, and pages opened. */
    public const LOG_SIGN_IN = 'sign_in';

    public const LOG_NAVIGATION = 'navigation';

    /** The Type filter's value for everything that isn't signing in or a page. */
    public const TYPE_CHANGES = 'changes';

    /** The Type filter's options. */
    public const TYPES = [
        self::LOG_SIGN_IN => 'Signing in and out',
        self::LOG_NAVIGATION => 'Pages opened',
        self::TYPE_CHANGES => 'Changes',
    ];

    /** A page opened again within this many seconds (a reload) isn't logged again. */
    private const PAGE_REPEAT_SECONDS = 10;

    /** Readable names for the pages a signed-in user can open, by route name. */
    private const PAGES = [
        'dashboard' => 'Dashboard',
        'profile.edit' => 'Profile',
        'inventory.index' => 'Inventory',
        'inventory.pending' => 'Pending approvals',
        'stock.index' => 'Stock',
        'users.index' => 'Settings – Users',
        'audit.index' => 'Settings – Audit trail',
    ];

    /** Friendly names for logged columns, in the order the details list them. */
    private const FIELDS = [
        'name' => 'Name',
        'email' => 'Email',
        'role' => 'Role',
        'date' => 'Date',
        'item' => 'Item',
        'item_id' => 'Item',
        'qty' => 'Quantity',
        'serial_number' => 'Serial number',
        'condition' => 'Condition',
        'taken_by' => 'Taken by',
        'given_by' => 'Given by',
        'department' => 'Department',
        'site' => 'Site',
        'status' => 'Status',
        'approval_type' => 'Approval type',
        'edited_by' => 'Edited by',
        'email_verified_at' => 'Email verified',
        'active_yn' => 'Active',
    ];

    /** Columns holding a users.id, shown as the person's name. */
    private const USER_FIELDS = ['given_by', 'edited_by', 'created_by', 'updated_by'];

    /** Model events worded as "<Verb> <record type>". */
    private const MODEL_EVENTS = ['created', 'updated', 'deleted', 'deactivated', 'reactivated'];

    public static function signedIn(User $user): void
    {
        static::write(fn () => activity(self::LOG_SIGN_IN)
            ->causedBy($user)
            ->event('signed in')
            ->log('Signed in'));
    }

    public static function signedOut(User $user): void
    {
        static::write(fn () => activity(self::LOG_SIGN_IN)
            ->causedBy($user)
            ->event('signed out')
            ->log('Signed out'));
    }

    /**
     * A sign-in that failed: a wrong password or an unknown email. Never the
     * password itself. Under the account when the email belongs to one, so
     * filtering by user finds it.
     */
    public static function failedSignIn(?User $user, ?string $email): void
    {
        $email = Str::limit(trim((string) $email), 255, '');

        static::write(fn () => activity(self::LOG_SIGN_IN)
            ->causedBy($user)
            ->withProperties(['email' => $email])
            ->event('failed')
            ->log('Failed sign-in'.($email !== '' ? " as {$email}" : '')));
    }

    /**
     * "Opened Inventory": a full page load by a signed-in user (see
     * App\Http\Middleware\RecordPageView), with the route name and URL path.
     */
    public static function pageView(Request $request, User $user): void
    {
        static::write(function () use ($request, $user) {
            $route = $request->route();
            $routeName = $route?->getName();
            $path = '/'.ltrim($request->path(), '/');

            if (! Cache::add('audit-page:'.$user->getKey().':'.sha1($request->getRequestUri()), true, self::PAGE_REPEAT_SECONDS)) {
                return;
            }

            activity(self::LOG_NAVIGATION)
                ->causedBy($user)
                ->withProperties([
                    'route' => $routeName,
                    'url' => Str::limit($path, 500, ''),
                ])
                ->event('viewed')
                ->log('Opened '.(self::PAGES[$routeName] ?? $path));
        });
    }

    /**
     * Runs a logging call, reporting rather than throwing any failure: the
     * audit trail never breaks the request it is recording.
     */
    public static function write(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Adds the request's IP address to the entry's properties, keeping
     * whatever is already there. Only for web requests (a request that
     * matched a route): console commands, seeders and queue workers get none.
     */
    public static function tagIp(ActivityContract $activity): void
    {
        $ip = static::requestIp();

        if ($ip === null) {
            return;
        }

        $properties = $activity->properties instanceof Collection
            ? $activity->properties
            : collect($activity->properties ?? []);

        if (! $properties->has(self::IP_PROPERTY)) {
            $activity->properties = $properties->put(self::IP_PROPERTY, $ip);
        }
    }

    /** The current web request's IP address, or null outside a web request. */
    public static function requestIp(): ?string
    {
        try {
            if (! app()->bound('request')) {
                return null;
            }

            $request = request();

            if ($request->route() === null) {
                return null;
            }

            $ip = $request->ip();

            return filled($ip) ? (string) $ip : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function ip(Activity $activity): ?string
    {
        $ip = data_get($activity->properties, self::IP_PROPERTY);

        return filled($ip) ? (string) $ip : null;
    }

    /** The account's name, "System" when nobody was signed in. */
    public static function who(Activity $activity): string
    {
        if ($activity->causer_id === null) {
            return self::SYSTEM;
        }

        $name = $activity->causer?->getAttribute('name');

        return filled($name) ? (string) $name : 'Removed account #'.$activity->causer_id;
    }

    /**
     * The entry's kind, for the badge: sign_in (or failed_sign_in),
     * navigation or changes.
     */
    public static function type(Activity $activity): string
    {
        return match (true) {
            $activity->log_name === self::LOG_SIGN_IN && $activity->event === 'failed' => 'failed_sign_in',
            $activity->log_name === self::LOG_SIGN_IN => self::LOG_SIGN_IN,
            $activity->log_name === self::LOG_NAVIGATION => self::LOG_NAVIGATION,
            default => self::TYPE_CHANGES,
        };
    }

    /** The badge's text for type(). */
    public static function typeLabel(Activity $activity): string
    {
        return match (static::type($activity)) {
            'failed_sign_in' => 'Failed sign-in',
            self::LOG_SIGN_IN => 'Sign-in',
            self::LOG_NAVIGATION => 'Page',
            default => 'Change',
        };
    }

    /**
     * What was done, in plain English. A model change ("updated") or a
     * one-word entry about a record ("Approved") is worded with the kind of
     * record: "Updated inventory", "Deactivated user", "Approved inventory".
     * Anything else already has a sentence as its description.
     */
    public static function action(Activity $activity): string
    {
        $description = trim((string) $activity->description);

        $isModelEvent = in_array(Str::lower($description), self::MODEL_EVENTS, true);
        $isOneWordAboutRecord = $activity->subject_type !== null && $description !== '' && ! str_contains($description, ' ');

        if ($isModelEvent || $isOneWordAboutRecord) {
            return Str::ucfirst(Str::lower($description)).' '.Str::lower(static::recordType($activity->subject_type));
        }

        return $description;
    }

    /**
     * What it was about: "Inventory #12 – Laptop bag for Jane", a stock
     * item, a person's name. From the record itself, or from the logged
     * values when the record no longer exists. Null when there's no record.
     */
    public static function about(Activity $activity): ?string
    {
        if ($activity->subject_type === null || $activity->subject_id === null) {
            return null;
        }

        $values = collect(data_get($activity->properties, 'old', []))
            ->merge(data_get($activity->properties, 'attributes', []));

        if ($activity->subject !== null) {
            $values = $values->merge($activity->subject->getAttributes());
        }

        $id = $activity->subject_id;

        return match ($activity->subject_type) {
            Inventory::class => 'Inventory #'.$id.static::join(' – ', trim($values->get('item').(filled($values->get('taken_by')) ? ' for '.$values->get('taken_by') : ''))),
            Stock::class => 'Stock #'.$id.static::join(' – ', $values->get('item')),
            Item::class => 'Item #'.$id.static::join(' – ', $values->get('name')),
            User::class => filled($values->get('name')) ? (string) $values->get('name') : 'User #'.$id,
            default => static::recordType($activity->subject_type).' #'.$id,
        };
    }

    /**
     * The fields a record change touched, for the row's details:
     * [['field' => 'Quantity', 'old' => '1', 'new' => '2'], ...]. A new
     * record lists its values (old null); a deleted one its last values
     * (new null). Empty for anything that isn't a record change.
     *
     * @param  array<int|string, string>  $userNames  users.id => name, from userNames()
     * @return list<array{field: string, old: ?string, new: ?string}>
     */
    public static function changes(Activity $activity, array $userNames = []): array
    {
        $new = (array) data_get($activity->properties, 'attributes', []);
        $old = (array) data_get($activity->properties, 'old', []);
        $fields = array_unique(array_merge(array_keys($new), array_keys($old)));

        usort($fields, function (string $a, string $b): int {
            $order = array_keys(self::FIELDS);
            $ia = array_search($a, $order, true);
            $ib = array_search($b, $order, true);

            return ($ia === false ? PHP_INT_MAX : $ia) <=> ($ib === false ? PHP_INT_MAX : $ib) ?: strcmp($a, $b);
        });

        $rows = [];

        foreach ($fields as $field) {
            $rows[] = [
                'field' => static::fieldLabel($field),
                'old' => array_key_exists($field, $old) ? static::formatValue($field, $old[$field], $userNames) : null,
                'new' => array_key_exists($field, $new) ? static::formatValue($field, $new[$field], $userNames) : null,
            ];
        }

        return $rows;
    }

    /**
     * Names for every user id the given entries' changes mention (given_by,
     * edited_by, ...), in one query.
     *
     * @param  iterable<Activity>  $entries
     * @return array<int|string, string>
     */
    public static function userNames(iterable $entries): array
    {
        $ids = collect($entries)
            ->flatMap(fn (Activity $activity) => collect(['attributes', 'old'])
                ->flatMap(fn (string $key) => collect((array) data_get($activity->properties, $key, []))->only(self::USER_FIELDS)->values()))
            ->filter(fn ($id) => is_numeric($id))
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return User::query()->whereIn('id', $ids->all())->pluck('name', 'id')->all();
    }

    public static function fieldLabel(string $field): string
    {
        return self::FIELDS[$field] ?? Str::ucfirst(str_replace('_', ' ', $field));
    }

    /**
     * A logged value as text: booleans as Yes / No, user ids as names,
     * timestamps as d/m/Y H:i, empty as null (shown as "—").
     *
     * @param  array<int|string, string>  $userNames
     */
    public static function formatValue(string $field, mixed $value, array $userNames = []): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($field === 'active_yn' || is_bool($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No';
        }

        if (in_array($field, self::USER_FIELDS, true) && is_numeric($value)) {
            return $userNames[$value] ?? 'User #'.$value;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        // Dates serialized by the model ("2026-10-28T09:15:00.000000Z").
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value)) {
            try {
                $date = Carbon::parse($value)->setTimezone(config('app.timezone'));

                return $date->format($date->format('H:i:s') === '00:00:00' ? 'd/m/Y' : 'd/m/Y H:i');
            } catch (Throwable) {
                return $value;
            }
        }

        return (string) $value;
    }

    /** "App\Models\Inventory" → "Inventory". */
    public static function recordType(?string $type): string
    {
        return $type === null ? 'Record' : Str::ucfirst(Str::lower(Str::headline(class_basename($type))));
    }

    private static function join(string $separator, mixed $text): string
    {
        $text = trim((string) $text);

        return $text === '' ? '' : $separator.$text;
    }
}
