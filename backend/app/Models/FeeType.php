<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeeType extends Model
{
    protected $fillable = ['name', 'default_amount'];

    protected function casts(): array
    {
        return ['default_amount' => 'decimal:2'];
    }

    public function fees()
    {
        return $this->hasMany(Fee::class);
    }
}
