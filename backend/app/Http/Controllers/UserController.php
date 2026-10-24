<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\FeePayment;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $role   = (string) $request->query('role', '');
        $status = (string) $request->query('status', '');

        $users = User::query()
            ->with('teacher')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when(in_array($role, User::ROLES, true), fn ($q) => $q->where('role', $role))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('users.index', compact('users', 'search', 'role', 'status'));
    }

    public function create()
    {
        return view('users.create');
    }

    public function store(UserRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'role'              => $data['role'],
            'password'          => $data['password'],
            'is_active'         => $request->boolean('is_active'),
            'email_verified_at' => now(),
        ]);

        return redirect()->route('users.index')
            ->with('success', "User \"{$user->name}\" ban gaya ({$user->role_label}).");
    }

    public function edit(User $user)
    {
        $user->load('teacher');

        return view('users.edit', compact('user'));
    }

    public function update(UserRequest $request, User $user)
    {
        $data = $request->validated();

        $user->fill([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'role'      => $data['role'],
            'is_active' => $request->boolean('is_active'),
        ]);
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return redirect()->route('users.index')->with('error', 'Aap apna account khud delete nahi kar sakte.');
        }

        if ($user->role === User::ROLE_ADMIN && $user->is_active
            && ! User::where('role', User::ROLE_ADMIN)->where('is_active', true)->where('id', '!=', $user->id)->exists()) {
            return redirect()->route('users.index')->with('error', 'Kam az kam ek active Admin hona zaroori hai, isliye ye delete nahi ho sakta.');
        }

        // Jis ne fee receive ki ho us ka naam receipts par hai, isliye delete ke bajaye deactivate karein
        if (FeePayment::where('received_by', $user->id)->exists()) {
            return redirect()->route('users.index')
                ->with('error', "\"{$user->name}\" ne fee receive ki hai (receipts par naam hai), isliye delete nahi ho sakta. Account ko Deactivate kar dein.");
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('users.index')->with('success', "User \"{$name}\" deleted.");
    }
}
