<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route par can:settings.manage laga hai
    }

    public function rules(): array
    {
        return [
            'school_name'     => ['required', 'string', 'max:100'],
            'school_address'  => ['nullable', 'string', 'max:255'],
            'school_phone'    => ['nullable', 'string', 'max:30'],
            'school_email'    => ['nullable', 'email', 'max:255'],
            'currency'        => ['required', 'string', 'max:10'],
            'pass_percentage' => ['required', 'numeric', 'min:1', 'max:100', 'decimal:0,2'],
            // SVG jaan-boojh ke nahi (usme script chhup sakti hai)
            'school_logo'     => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024',
                                  'dimensions:min_width=32,min_height=32,max_width=3000,max_height=3000'],
            'remove_logo'     => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'school_logo.max'        => 'Logo 1 MB se chhota hona chahiye.',
            'school_logo.mimes'      => 'Logo sirf PNG, JPG ya WEBP ho sakta hai.',
            'school_logo.image'      => 'Logo ek asli tasveer honi chahiye.',
            'school_logo.dimensions' => 'Logo kam az kam 32x32 aur ziyada se ziyada 3000x3000 pixels ka ho.',
            'pass_percentage.min'    => 'Pass percentage 1 se 100 ke beech ho.',
            'pass_percentage.max'    => 'Pass percentage 1 se 100 ke beech ho.',
        ];
    }

    public function attributes(): array
    {
        return [
            'school_name' => 'school name', 'school_address' => 'address', 'school_phone' => 'phone',
            'school_email' => 'email', 'currency' => 'currency', 'pass_percentage' => 'pass percentage',
            'school_logo' => 'logo',
        ];
    }
}
