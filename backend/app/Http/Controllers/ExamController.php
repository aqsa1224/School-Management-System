<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExamRequest;
use App\Models\Exam;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $exams = Exam::query()
            ->withCount('marks')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('exams.index', compact('exams', 'search'));
    }

    public function create()
    {
        return view('exams.create');
    }

    public function store(ExamRequest $request)
    {
        $exam = Exam::create($request->validated());

        return redirect()->route('exams.index')->with('success', "Exam \"{$exam->name}\" created. Ab marks enter kar sakte hain.");
    }

    public function edit(Exam $exam)
    {
        return view('exams.edit', compact('exam'));
    }

    public function update(ExamRequest $request, Exam $exam)
    {
        $exam->update($request->validated());

        return redirect()->route('exams.index')->with('success', 'Exam updated successfully.');
    }

    public function destroy(Exam $exam)
    {
        // Marks wale exam ko delete karne se poora result ud jata, isliye rok diya
        if ($exam->marks()->exists()) {
            return redirect()->route('exams.index')
                ->with('error', "\"{$exam->name}\" ke marks maujood hain, isliye delete nahi ho sakta.");
        }

        $name = $exam->name;
        $exam->delete();

        return redirect()->route('exams.index')->with('success', "Exam \"{$name}\" deleted.");
    }
}
