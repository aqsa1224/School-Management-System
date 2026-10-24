<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettingsRequest;
use App\Models\Setting;
use App\Support\Grading;
use App\Support\School;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function index()
    {
        return view('settings.index', [
            'school'         => School::info(),
            'passPercentage' => Grading::passMark(),
        ]);
    }

    public function update(SettingsRequest $request)
    {
        $data = $request->validated();

        // SIRF ye keys likhte hain (internal counters jaise seq_VCH_2026 ko kabhi nahi chhoote)
        foreach (['school_name', 'school_address', 'school_phone', 'school_email', 'currency'] as $key) {
            Setting::set($key, trim((string) ($data[$key] ?? '')));
        }
        Setting::set('pass_percentage', (string) (float) $data['pass_percentage']);

        $oldLogo = Setting::get('school_logo');

        if ($request->hasFile('school_logo')) {
            if ($oldLogo) {
                Storage::disk('public')->delete($oldLogo);
            }
            Setting::set('school_logo', $request->file('school_logo')->store('logo', 'public'));
        } elseif ($request->boolean('remove_logo') && $oldLogo) {
            Storage::disk('public')->delete($oldLogo);
            Setting::set('school_logo', '');
        }

        return redirect()->route('settings.index')->with('success', 'Settings save ho gayi.');
    }
}
