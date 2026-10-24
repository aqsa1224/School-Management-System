<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * School ki maloomat (naam, address, phone, currency, logo) ek hi jagah se.
 * Settings page inhein badalta hai, aur sidebar, login page, voucher, receipt, result card sab yahin se parhte hain.
 */
class School
{
    public const KEYS = ['school_name', 'school_address', 'school_phone', 'school_email', 'currency', 'school_logo'];

    /** @return array{name:string,address:string,phone:string,email:string,currency:string,logo_path:?string,logo_url:?string} */
    public static function info(): array
    {
        try {
            $v = Setting::whereIn('key', self::KEYS)->pluck('value', 'key')->all();
        } catch (Throwable) {
            $v = []; // database abhi taiyar nahi (jaise migrate se pehle): default dikhao, crash nahi
        }

        $logo = ($v['school_logo'] ?? '') !== '' ? $v['school_logo'] : null;
        $hasLogo = $logo && Storage::disk('public')->exists($logo);

        return [
            'name'      => ($v['school_name'] ?? '') !== '' ? $v['school_name'] : config('app.name'),
            'address'   => $v['school_address'] ?? '',
            'phone'     => $v['school_phone'] ?? '',
            'email'     => $v['school_email'] ?? '',
            'currency'  => ($v['currency'] ?? '') !== '' ? $v['currency'] : 'Rs.',
            'logo_path' => $logo,
            // asset() maujooda host (127.0.0.1 ya localhost) use karta hai
            'logo_url'  => $hasLogo ? asset('storage/' . $logo) : null,
        ];
    }

    /** Logo ko page ke andar hi jor dene wali (base64) shakal: PDF ke liye (file path ka masla nahi) */
    public static function logoDataUri(?string $logoPath = null): ?string
    {
        $logoPath ??= self::info()['logo_path'];

        if (! $logoPath || ! Storage::disk('public')->exists($logoPath)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($logoPath) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('public')->get($logoPath));
    }

    /** Voucher/receipt ke liye: browser me logo ka URL, PDF me base64 */
    public static function forDocument(bool $pdf): array
    {
        $info = self::info();
        $info['logo_src'] = $pdf ? self::logoDataUri($info['logo_path']) : $info['logo_url'];

        return $info;
    }
}
