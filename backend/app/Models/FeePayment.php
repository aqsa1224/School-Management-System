<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ek FeePayment = ek receipt (asal me wasool hua paisa).
 */
class FeePayment extends Model
{
    protected $fillable = ['receipt_no', 'fee_id', 'amount', 'payment_date', 'method', 'note', 'received_by'];

    protected $attributes = ['method' => 'cash'];

    protected function casts(): array
    {
        return ['payment_date' => 'date', 'amount' => 'decimal:2'];
    }

    public function fee()
    {
        return $this->belongsTo(Fee::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
