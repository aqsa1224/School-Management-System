<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetUserRole extends Command
{
    protected $signature = 'user:role {email : User ki email} {role=admin : admin, teacher, accountant ya receptionist}';

    protected $description = 'Kisi user ka role set karta hai (default: admin)';

    public function handle(): int
    {
        $role = strtolower($this->argument('role'));

        if (! in_array($role, User::ROLES, true)) {
            $this->error('Role galat hai. Ye me se koi ek likhein: ' . implode(', ', User::ROLES));
            return self::FAILURE;
        }

        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('Is email ka koi user nahi mila: ' . $this->argument('email'));
            return self::FAILURE;
        }

        $user->update(['role' => $role]);
        $this->info("{$user->name} ({$user->email}) ka role ab \"{$role}\" hai.");

        return self::SUCCESS;
    }
}
