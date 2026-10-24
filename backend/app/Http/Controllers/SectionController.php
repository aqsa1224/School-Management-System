<?php

namespace App\Http\Controllers;

use App\Http\Requests\SectionRequest;
use App\Models\Section;
use App\Models\SchoolClass;

class SectionController extends Controller
{
    public function store(SectionRequest $request, SchoolClass $schoolClass)
    {
        $section = $schoolClass->sections()->create($request->validated());

        return redirect()->route('classes.show', $schoolClass)
            ->with('success', "Section \"{$section->name}\" added.");
    }

    public function update(SectionRequest $request, SchoolClass $schoolClass, Section $section)
    {
        $section->update($request->validated());

        return redirect()->route('classes.show', $schoolClass)
            ->with('success', 'Section updated.');
    }

    public function destroy(SchoolClass $schoolClass, Section $section)
    {
        if ($section->students()->exists()) {
            return redirect()->route('classes.show', $schoolClass)
                ->with('error', "Section \"{$section->name}\" me students hain, isliye delete nahi ho sakta.");
        }

        $name = $section->name;
        $section->delete();

        return redirect()->route('classes.show', $schoolClass)
            ->with('success', "Section \"{$name}\" deleted.");
    }
}
