<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoldenClubSetting extends Model
{
    protected $fillable = ['key', 'value', 'description'];

    /**
     * No 'value' => 'array' cast here on purpose: get()/set() do their own
     * json_encode/decode. Casting AND manually encoding double-encoded
     * every value (set() would json_encode a string the cast was about to
     * json_encode again on save), and get() then called json_decode() on
     * an already-array attribute, which always failed and silently
     * returned the default - every setting read was effectively dead.
     */
    public static function get($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        if (!$setting || $setting->value === null) {
            return $default;
        }
        $decoded = json_decode($setting->value, true);
        return $decoded ?? $default;
    }

    public static function set($key, $value, $description = null)
    {
        return self::updateOrCreate(
            ['key' => $key],
            ['value' => json_encode($value), 'description' => $description]
        );
    }
}
