<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Small key/value store for settings a super admin changes from the dashboard. */
class Setting extends Model
{
    public const ELECTION_DATE = 'election_date';

    public const ELECTION_LABEL = 'election_label';

    /** Presidential and National Assembly election, used until a super admin sets another date. */
    public const DEFAULT_ELECTION_DATE = '2027-01-16 00:00';

    public const DEFAULT_ELECTION_LABEL = 'Presidential and National Assembly election';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = Cache::rememberForever("setting:{$key}", fn () => static::find($key)?->value ?? '');

        return $value === '' ? $default : $value;
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting:{$key}");
    }

    /** The election date in UTC: the admin setting, or 16 January 2027 (Nigeria time) when none is set. */
    public static function electionDate(): CarbonImmutable
    {
        $value = static::get(self::ELECTION_DATE);

        return $value
            ? CarbonImmutable::parse($value)
            : CarbonImmutable::parse(self::DEFAULT_ELECTION_DATE, config('membership.timezone'))->utc();
    }

    public static function electionLabel(): string
    {
        return static::get(self::ELECTION_LABEL) ?? self::DEFAULT_ELECTION_LABEL;
    }
}
