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

    public static function electionDate(): ?CarbonImmutable
    {
        $value = static::get(self::ELECTION_DATE);

        return $value ? CarbonImmutable::parse($value) : null;
    }
}
