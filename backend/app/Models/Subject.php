<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = ['name', 'code'];

    public function classes()
    {
        return $this->belongsToMany(SchoolClass::class, 'class_subject')->withTimestamps();
    }

    public function assignments()
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    public function marks()
    {
        return $this->hasMany(Mark::class);
    }
}
