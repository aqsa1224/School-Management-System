<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // access route ke can:users.manage se control hota hai
    }

    public function rules(): array
    {
        $target = $this->route('user'); // update par maujooda user, create par null

        return [
            'name'      => ['required', 'string', 'max:100'],
            'email'     => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target)],
            'role'      => ['required', Rule::in(User::ROLES)],
            // Naye user ka password zaroori, maujooda ka sirf tab jab badalna ho
            'password'  => [$target ? 'nullable' : 'required', 'string', 'min:8', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['is_active' => 'status'];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $target = $this->route('user');
                if (! $target || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $newRole = $this->input('role');
                $active  = $this->boolean('is_active');

                // Apne aap ko lock-out na kar le
                if ($target->id === $this->user()->id) {
                    if ($newRole !== $target->role) {
                        $validator->errors()->add('role', 'Aap apna role khud nahi badal sakte.');
                    }
                    if (! $active) {
                        $validator->errors()->add('is_active', 'Aap apne aap ko deactivate nahi kar sakte.');
                    }
                }

                // Kam az kam ek active admin hamesha rahe
                $losesAdmin = $target->role === User::ROLE_ADMIN && $target->is_active && ($newRole !== User::ROLE_ADMIN || ! $active);
                if ($losesAdmin && ! User::where('role', User::ROLE_ADMIN)->where('is_active', true)->where('id', '!=', $target->id)->exists()) {
                    $validator->errors()->add('role', 'Kam az kam ek active Admin hona zaroori hai. Pehle kisi aur ko Admin banayein.');
                }
            },
        ];
    }
}
