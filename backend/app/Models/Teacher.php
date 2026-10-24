<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    protected $fillable = [
        'user_id', 'name', 'email', 'phone', 'gender',
        'qualification', 'joining_date', 'address', 'status',
    ];

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return ['joining_date' => 'date'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignments()
    {
        return $this->hasMany(TeacherAssignment::class);
    }
}
