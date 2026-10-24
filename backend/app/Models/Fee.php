<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ek Fee = ek voucher. Isme payments (receipts) judti hain.
 */
class Fee extends Model
{
    public const STATUS_UNPAID  = 'unpaid';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PAID    = 'paid';

    protected $fillable = [
        'voucher_no', 'student_id', 'fee_type_id', 'for_month',
        'amount', 'discount', 'due_date', 'status',
    ];

    // Naya voucher shuru me unpaid hota hai
    protected $attributes = ['status' => self::STATUS_UNPAID, 'discount' => 0];

    protected function casts(): array
    {
        return [
            'for_month' => 'date',
            'due_date'  => 'date',
            'amount'    => 'decimal:2',
            'discount'  => 'decimal:2',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function feeType()
    {
        return $this->belongsTo(FeeType::class);
    }

    public function payments()
    {
        return $this->hasMany(FeePayment::class);
    }

    // Discount ke baad kitna dena hai
    public function getNetAmountAttribute(): float
    {
        return (float) $this->amount - (float) $this->discount;
    }

    // Ab tak kitna wasool hua
    public function getPaidAmountAttribute(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    // Abhi kitna baqi hai
    public function getBalanceAttribute(): float
    {
        return max(0, $this->net_amount - $this->paid_amount);
    }

    // Payments ke hisaab se status theek karta hai
    public function refreshStatus(): void
    {
        $paid = $this->paid_amount;

        $this->status = $paid <= 0
            ? self::STATUS_UNPAID
            : ($paid >= $this->net_amount ? self::STATUS_PAID : self::STATUS_PARTIAL);

        $this->save();
    }
}
