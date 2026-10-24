<?php

namespace App\Support;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Teacher ko sirf wohi classes/sections dikhane ka rule (Attendance, aage Marks me bhi).
 *
 * forUser() ka nateeja:
 *   null                       = koi pabandi nahi (admin, accountant ... sab classes)
 *   []                         = kuch bhi nahi (teacher record nahi ya koi assignment nahi)
 *   [class_id => null]         = us class ke SAB sections
 *   [class_id => [1, 2]]       = us class ke sirf ye sections
 */
class TeacherScope
{
    public static function forUser(?User $user): ?array
    {
        if (! $user || $user->role !== User::ROLE_TEACHER) {
            return null;
        }

        $teacher = $user->teacher;
        if (! $teacher || $teacher->status !== 'active') {
            return [];
        }

        $map = [];
        foreach ($teacher->assignments()->get(['school_class_id', 'section_id']) as $a) {
            $c = $a->school_class_id;

            if (array_key_exists($c, $map) && $map[$c] === null) {
                continue; // pehle se "sab sections"
            }
            if ($a->section_id === null) {
                $map[$c] = null;
                continue;
            }
            $map[$c][] = $a->section_id;
        }

        return $map;
    }

    /** Allowed classes (un ke allowed sections ke saath) */
    public static function classes(?array $allowed): Collection
    {
        $query = SchoolClass::with('sections')->orderBy('sort_order')->orderBy('name');

        if ($allowed !== null) {
            $query->whereIn('id', array_keys($allowed));
        }

        $classes = $query->get();

        if ($allowed !== null) {
            foreach ($classes as $class) {
                $sections = $allowed[$class->id] ?? null;
                if ($sections !== null) {
                    $class->setRelation('sections', $class->sections->whereIn('id', $sections)->values());
                }
            }
        }

        return $classes;
    }

    /** Students (ya students se joré query) ko allowed classes/sections tak mehdood karta hai */
    public static function restrictStudents($query, ?array $allowed)
    {
        if ($allowed === null) {
            return $query;
        }
        if ($allowed === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($allowed) {
            foreach ($allowed as $classId => $sections) {
                $q->orWhere(function ($w) use ($classId, $sections) {
                    $w->where('students.school_class_id', $classId);
                    if ($sections !== null) {
                        $w->whereIn('students.section_id', $sections);
                    }
                });
            }
        });
    }

    /**
     * Teacher ko is class (aur section) me kaun se subjects ke marks lagane ki ijazat hai.
     *   null = koi pabandi nahi (admin, accountant ...)
     *   []   = kuch nahi
     * Section chuna ho to "sab sections" wali assignment bhi chalti hai.
     * Section na chuna ho (poori class) to sirf "sab sections" wali assignment chalegi.
     */
    public static function subjectIds(?User $user, int $classId, ?int $sectionId): ?array
    {
        if (! $user || $user->role !== User::ROLE_TEACHER) {
            return null;
        }

        $teacher = $user->teacher;
        if (! $teacher || $teacher->status !== 'active') {
            return [];
        }

        return $teacher->assignments()
            ->where('school_class_id', $classId)
            ->where(function ($q) use ($sectionId) {
                $q->whereNull('section_id');
                if ($sectionId) {
                    $q->orWhere('section_id', $sectionId);
                }
            })
            ->pluck('subject_id')->unique()->values()->all();
    }

    /** Dropdown ke liye: [class_id => [subject ids]] (kisi bhi section me). null = sab subjects */
    public static function subjectsByClass(?User $user): ?array
    {
        if (! $user || $user->role !== User::ROLE_TEACHER) {
            return null;
        }

        $teacher = $user->teacher;
        if (! $teacher || $teacher->status !== 'active') {
            return [];
        }

        return $teacher->assignments()->get(['school_class_id', 'subject_id'])
            ->groupBy('school_class_id')
            ->map(fn ($rows) => $rows->pluck('subject_id')->unique()->values()->all())
            ->all();
    }
}
