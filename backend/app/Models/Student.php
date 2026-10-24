<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = [
        'admission_no', 'first_name', 'last_name', 'gender', 'date_of_birth',
        'phone', 'email', 'address', 'guardian_name', 'guardian_phone', 'photo',
        'school_class_id', 'section_id', 'admission_date', 'status',
    ];

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return [
            'date_of_birth'  => 'date',
            'admission_date' => 'date',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    // Photo ka URL (agar photo nahi to null)
    public function getPhotoUrlAttribute(): ?string
    {
        // asset() maujooda host (127.0.0.1 ya localhost) use karta hai, isliye APP_URL ghalat ho tab bhi photo dikhti hai
        return $this->photo ? asset('storage/' . $this->photo) : null;
    }

    // Naam ke pehle harf (photo na ho to avatar me dikhane ke liye)
    public function getInitialsAttribute(): string
    {
        return strtoupper(mb_substr($this->first_name, 0, 1) . mb_substr((string) $this->last_name, 0, 1));
    }

    // Agla admission number, e.g. ADM-0007 (form me suggestion ke taur par)
    public static function nextAdmissionNo(): string
    {
        $n = (int) static::max('id') + 1;
        do {
            $no = sprintf('ADM-%04d', $n++);
        } while (static::where('admission_no', $no)->exists());

        return $no;
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function marks()
    {
        return $this->hasMany(Mark::class);
    }

    public function fees()
    {
        return $this->hasMany(Fee::class);
    }
}
