<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * School settings (naam, logo, address ...) key/value form me.
 * Use: Setting::get('school_name')   |   Setting::set('school_name', 'ABC School')
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null)
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
