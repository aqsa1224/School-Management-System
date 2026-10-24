<?php

namespace Database\Seeders;

use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Zaroori starting data: admin user, classes + section A, school settings.
     * IMPORTANT: login ke baad admin ka password foran badal dein.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@school.test'],
            [
                'name'              => 'Administrator',
                'role'              => User::ROLE_ADMIN,
                'password'          => 'password',
                'email_verified_at' => now(),
            ]
        );

        $classes = ['Play Group', 'Nursery', 'Prep'];
        for ($i = 1; $i <= 10; $i++) {
            $classes[] = 'Class ' . $i;
        }

        foreach ($classes as $order => $name) {
            $class = SchoolClass::firstOrCreate(['name' => $name], ['sort_order' => $order + 1]);
            Section::firstOrCreate(['school_class_id' => $class->id, 'name' => 'A']);
        }

        $defaults = [
            'school_name'    => 'My School',
            'school_address' => '',
            'school_phone'   => '',
            'school_email'   => '',
            'currency'       => 'Rs.',
            'pass_percentage' => '40',
        ];
        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
