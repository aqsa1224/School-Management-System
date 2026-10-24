<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * SIRF testing/demo ke liye (asli school me na chalayen):
 *   php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Test users (har role ke liye) - password: password
        foreach ([
            ['Demo Accountant',   'accountant@school.test',   User::ROLE_ACCOUNTANT],
            ['Demo Receptionist', 'receptionist@school.test', User::ROLE_RECEPTIONIST],
            ['Demo Teacher',      'teacher@school.test',      User::ROLE_TEACHER],
        ] as [$name, $email, $role]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $name, 'role' => $role, 'password' => 'password', 'email_verified_at' => now(),
            ]);
        }

        // Subjects
        $subjects = collect(['English', 'Urdu', 'Mathematics', 'Science', 'Islamiat'])
            ->mapWithKeys(fn ($n) => [$n => Subject::firstOrCreate(['name' => $n], ['code' => strtoupper(substr($n, 0, 3))])]);

        $classes = SchoolClass::whereIn('name', ['Class 1', 'Class 2', 'Class 3'])->get();
        foreach ($classes as $class) {
            $class->subjects()->syncWithoutDetaching($subjects->pluck('id'));
        }

        // Teachers + assignments
        $teacher = Teacher::firstOrCreate(
            ['email' => 'teacher@school.test'],
            ['name' => 'Demo Teacher', 'phone' => '0300-0000000', 'gender' => 'female',
             'user_id' => User::where('email', 'teacher@school.test')->value('id')]
        );
        foreach ($classes as $class) {
            TeacherAssignment::firstOrCreate([
                'teacher_id' => $teacher->id, 'school_class_id' => $class->id,
                'section_id' => $class->sections()->value('id'), 'subject_id' => $subjects['English']->id,
            ]);
        }

        // Students
        $n = 1;
        foreach ($classes as $class) {
            for ($i = 1; $i <= 3; $i++, $n++) {
                Student::firstOrCreate(['admission_no' => sprintf('ADM-%04d', $n)], [
                    'first_name' => "Student{$n}", 'last_name' => 'Demo', 'gender' => $n % 2 ? 'male' : 'female',
                    'guardian_name' => "Guardian {$n}", 'guardian_phone' => '0300-1111111',
                    'school_class_id' => $class->id, 'section_id' => $class->sections()->value('id'),
                    'admission_date' => now()->toDateString(),
                ]);
            }
        }

        // Fee types + one exam
        foreach (['Tuition Fee' => 3000, 'Admission Fee' => 5000, 'Exam Fee' => 500] as $name => $amount) {
            FeeType::firstOrCreate(['name' => $name], ['default_amount' => $amount]);
        }
        Exam::firstOrCreate(['name' => 'Mid Term'], ['start_date' => now()->toDateString()]);
    }
}
