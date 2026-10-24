<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mark extends Model
{
    protected $fillable = ['exam_id', 'student_id', 'subject_id', 'total_marks', 'obtained_marks', 'remarks'];

    protected $attributes = ['total_marks' => 100];

    protected function casts(): array
    {
        return ['total_marks' => 'decimal:2', 'obtained_marks' => 'decimal:2'];
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function getPercentageAttribute(): float
    {
        return $this->total_marks > 0
            ? round(($this->obtained_marks / $this->total_marks) * 100, 2)
            : 0.0;
    }
}
