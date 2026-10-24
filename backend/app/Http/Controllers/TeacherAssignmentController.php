<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeacherAssignmentRequest;
use App\Models\Teacher;
use App\Models\TeacherAssignment;

class TeacherAssignmentController extends Controller
{
    public function store(TeacherAssignmentRequest $request, Teacher $teacher)
    {
        $teacher->assignments()->create($request->validated());

        return redirect()->route('teachers.show', $teacher)->with('success', 'Assignment added.');
    }

    public function destroy(Teacher $teacher, TeacherAssignment $assignment)
    {
        $assignment->delete();

        return redirect()->route('teachers.show', $teacher)->with('success', 'Assignment removed.');
    }
}
