<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubjectRequest;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $subjects = Subject::query()
            ->withCount('classes')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('subjects.index', compact('subjects', 'search'));
    }

    public function create()
    {
        return view('subjects.create', ['classes' => SchoolClass::orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function store(SubjectRequest $request)
    {
        $data = $request->validated();

        $subject = Subject::create([
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
        ]);
        $subject->classes()->sync($data['class_ids'] ?? []);

        return redirect()->route('subjects.show', $subject)
            ->with('success', "Subject \"{$subject->name}\" added.");
    }

    public function show(Subject $subject)
    {
        $subject->load([
            'classes' => fn ($q) => $q->orderBy('sort_order'),
            'assignments.teacher', 'assignments.schoolClass', 'assignments.section',
        ]);

        return view('subjects.show', compact('subject'));
    }

    public function edit(Subject $subject)
    {
        return view('subjects.edit', [
            'subject'  => $subject,
            'classes'  => SchoolClass::orderBy('sort_order')->orderBy('name')->get(),
            'selected' => $subject->classes()->pluck('school_classes.id')->all(),
        ]);
    }

    public function update(SubjectRequest $request, Subject $subject)
    {
        $data = $request->validated();

        $subject->update(['name' => $data['name'], 'code' => $data['code'] ?? null]);
        $subject->classes()->sync($data['class_ids'] ?? []);

        return redirect()->route('subjects.show', $subject)
            ->with('success', 'Subject updated successfully.');
    }

    public function destroy(Subject $subject)
    {
        // Marks wale subject ko delete karne se result kharab ho jata, isliye rok diya
        if ($subject->marks()->exists()) {
            return redirect()->route('subjects.index')
                ->with('error', "\"{$subject->name}\" ke marks maujood hain, isliye delete nahi ho sakta.");
        }

        $name = $subject->name;
        $subject->delete(); // class links aur teacher assignments khud hat jate hain

        return redirect()->route('subjects.index')->with('success', "Subject \"{$name}\" deleted.");
    }
}
