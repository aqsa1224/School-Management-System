<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    public const STATUSES = ['present', 'absent', 'late', 'leave'];

    protected $fillable = ['student_id', 'date', 'status', 'remarks', 'marked_by'];

    protected $attributes = ['status' => 'present'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function markedBy()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
