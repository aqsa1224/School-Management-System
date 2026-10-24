<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Percentage se grade aur pass/fail.
 * Pass marks Settings me "pass_percentage" se badal sakte hain (default 40).
 */
class Grading
{
    public const BANDS = [
        'A+' => 80,
        'A'  => 70,
        'B'  => 60,
        'C'  => 50,
        'D'  => 40,
    ];

    public static function passMark(): float
    {
        return (float) Setting::get('pass_percentage', 40);
    }

    public static function grade(float $percentage): string
    {
        foreach (self::BANDS as $grade => $min) {
            if ($percentage >= $min) {
                return $grade;
            }
        }

        return 'F';
    }
}
