<?php

namespace App\Support;

use App\Models\Exam;
use App\Models\Mark;
use Illuminate\Support\Collection;

/**
 * Ek exam ke students ka result nikalta hai: subject-wise marks, total, percentage,
 * grade, pass/fail aur position (percentage ke hisaab se, barabar percentage ko barabar position).
 */
class ResultCalculator
{
    /**
     * @return array{subjects: Collection, rows: array<int, array>}
     */
    public static function forGroup(Exam $exam, Collection $students): array
    {
        $marks = Mark::query()
            ->where('exam_id', $exam->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->with('subject')
            ->get();

        $byStudent = $marks->groupBy('student_id');
        $subjects  = $marks->pluck('subject')->unique('id')->sortBy('name')->values();
        $passMark  = Grading::passMark();

        $rows = [];
        foreach ($students as $student) {
            $own      = $byStudent->get($student->id, collect())->keyBy('subject_id');
            $obtained = (float) $own->sum(fn ($m) => (float) $m->obtained_marks);
            $total    = (float) $own->sum(fn ($m) => (float) $m->total_marks);
            $pct      = $total > 0 ? round($obtained / $total * 100, 2) : null;

            $rows[$student->id] = [
                'student'    => $student,
                'marks'      => $own,
                'obtained'   => $obtained,
                'total'      => $total,
                'percentage' => $pct,
                'grade'      => $pct !== null ? Grading::grade($pct) : null,
                // Har subject me pass marks hon tabhi Pass
                'result'     => $own->isEmpty() ? null : ($own->contains(fn ($m) => $m->percentage < $passMark) ? 'Fail' : 'Pass'),
                'position'   => null,
            ];
        }

        // Position: sirf unhein jin ke marks lage hain
        $ranked = collect($rows)->filter(fn ($r) => $r['percentage'] !== null)
            ->sortByDesc('percentage')->values();

        $position = 0;
        $previous = null;
        foreach ($ranked as $i => $row) {
            if ($previous === null || $row['percentage'] !== $previous) {
                $position = $i + 1;
                $previous = $row['percentage'];
            }
            $rows[$row['student']->id]['position'] = $position;
        }

        return ['subjects' => $subjects, 'rows' => array_values($rows)];
    }
}
