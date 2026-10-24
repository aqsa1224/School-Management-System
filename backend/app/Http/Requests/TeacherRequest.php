<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // roles/permissions Step 10 me lagengi
    }

    public function rules(): array
    {
        $teacher    = $this->route('teacher');          // update par maujooda teacher, create par null
        $hasLogin   = (bool) $teacher?->user_id;
        $wantsLogin = $this->boolean('create_login');

        $emailRules = ['required', 'email', 'max:255', Rule::unique('teachers', 'email')->ignore($teacher)];
        // Login account hai (ya ban raha hai) to email users table me bhi unique ho
        if ($hasLogin || $wantsLogin) {
            $emailRules[] = Rule::unique('users', 'email')->ignore($teacher?->user_id);
        }

        return [
            'name'          => ['required', 'string', 'max:100'],
            'email'         => $emailRules,
            'phone'         => ['nullable', 'string', 'max:30'],
            'gender'        => ['nullable', Rule::in(['male', 'female', 'other'])],
            'qualification' => ['nullable', 'string', 'max:255'],
            'joining_date'  => ['nullable', 'date'],
            'address'       => ['nullable', 'string', 'max:500'],
            'status'        => ['required', Rule::in(['active', 'left'])],
            'create_login'  => ['nullable', 'boolean'],
            // Naya login banate waqt password zaroori; maujooda login ka password badalna optional
            'password'      => [Rule::requiredIf($wantsLogin && ! $hasLogin), 'nullable', 'string', 'min:8'],
        ];
    }
}
