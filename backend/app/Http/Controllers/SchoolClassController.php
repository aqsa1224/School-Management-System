<?php

namespace App\Http\Controllers;

use App\Http\Requests\SchoolClassRequest;
use App\Models\SchoolClass;
use Illuminate\Http\Request;

class SchoolClassController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $classes = SchoolClass::query()
            ->withCount(['sections', 'students'])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('classes.index', compact('classes', 'search'));
    }

    public function create()
    {
        return view('classes.create', [
            // Naya class list ke aakhir me aaye
            'nextOrder' => (int) SchoolClass::max('sort_order') + 1,
        ]);
    }

    public function store(SchoolClassRequest $request)
    {
        $data = $request->validated();
        $data['sort_order'] = $data['sort_order'] ?? ((int) SchoolClass::max('sort_order') + 1);

        $class = SchoolClass::create($data);

        return redirect()->route('classes.show', $class)
            ->with('success', "Class \"{$class->name}\" created. Ab is me sections add kar sakte hain.");
    }

    public function show(SchoolClass $schoolClass)
    {
        $schoolClass->load(['sections' => fn ($q) => $q->withCount('students')->orderBy('name')]);
        $schoolClass->loadCount('students');

        return view('classes.show', ['class' => $schoolClass]);
    }

    public function edit(SchoolClass $schoolClass)
    {
        return view('classes.edit', ['class' => $schoolClass]);
    }

    public function update(SchoolClassRequest $request, SchoolClass $schoolClass)
    {
        $data = $request->validated();
        $data['sort_order'] = $data['sort_order'] ?? $schoolClass->sort_order;

        $schoolClass->update($data);

        return redirect()->route('classes.show', $schoolClass)
            ->with('success', 'Class updated successfully.');
    }

    public function destroy(SchoolClass $schoolClass)
    {
        // Students wali class delete nahi hogi (database bhi rokta hai, ye friendly message hai)
        if ($schoolClass->students()->exists()) {
            return redirect()->route('classes.index')
                ->with('error', "\"{$schoolClass->name}\" me students hain, isliye delete nahi ho sakti. Pehle students ko doosri class me bhej dein.");
        }

        $name = $schoolClass->name;
        $schoolClass->delete(); // is ke sections khud delete ho jate hain

        return redirect()->route('classes.index')
            ->with('success', "Class \"{$name}\" deleted.");
    }
}
