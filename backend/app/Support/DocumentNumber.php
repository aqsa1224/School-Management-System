<?php

namespace App\Support;

use App\Models\Fee;
use App\Models\FeePayment;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Voucher aur receipt ke auto numbers:
 *   VCH-2026-000001, VCH-2026-000002 ...   (har saal naye sire se)
 *   RCP-2026-000001, RCP-2026-000002 ...
 *
 * Number ek COUNTER se aata hai jo kabhi peeche nahi jata. Is liye agar koi receipt/voucher cancel
 * ya delete ho jaye to us ka number dobara kisi aur ko nahi milta (accounting ke liye zaroori hai).
 * Counter "settings" table me "seq_VCH_2026" jaisi key me rehta hai.
 */
class DocumentNumber
{
    public static function voucher(): string
    {
        return self::next('VCH', Fee::class, 'voucher_no');
    }

    public static function receipt(): string
    {
        return self::next('RCP', FeePayment::class, 'receipt_no');
    }

    /**
     * Do log ek saath save karein aur number takra jaye to (database unique rule rokta hai)
     * naya number le kar dobara koshish karta hai.
     */
    public static function retry(Closure $callback, int $times = 3)
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $callback();
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= $times) {
                    throw $e;
                }
            }
        }
    }

    private static function next(string $prefix, string $model, string $column): string
    {
        $year = now()->format('Y');
        $stem = "{$prefix}-{$year}-";
        $key  = "seq_{$prefix}_{$year}";

        $number = DB::transaction(function () use ($key, $stem, $model, $column) {
            $row = DB::table('settings')->where('key', $key)->lockForUpdate()->first();

            // Pehli baar: jo sab se bara number pehle se maujood hai us se aage shuru karo
            $current = $row
                ? (int) $row->value
                : (int) substr((string) ($model::where($column, 'like', $stem . '%')->orderByDesc($column)->value($column) ?? $stem . '0'), strlen($stem));

            $next = $current + 1;

            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => (string) $next, 'created_at' => $row->created_at ?? now(), 'updated_at' => now()]
            );

            return $next;
        });

        return $stem . str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }
}
