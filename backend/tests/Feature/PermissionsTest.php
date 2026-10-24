<?php

use App\Models\Exam;
use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;

function pm_fixtures(): object
{
    static $n = 0;
    $n++;

    $class = SchoolClass::create(['name' => "PM Class {$n}"]);
    $section = $class->sections()->create(['name' => 'A']);
    $subject = Subject::create(['name' => "PM Subject {$n}"]);
    $class->subjects()->attach($subject);
    $student = Student::create([
        'admission_no' => "PM-{$n}", 'first_name' => "Pm{$n}", 'gender' => 'male', 'guardian_name' => 'G',
        'guardian_phone' => '1', 'school_class_id' => $class->id, 'section_id' => $section->id,
    ]);
    $type = FeeType::create(['name' => "PM Type {$n}", 'default_amount' => 10]);
    $fee = Fee::create(['voucher_no' => "PMV-{$n}", 'student_id' => $student->id, 'fee_type_id' => $type->id, 'amount' => 100]);
    $payment = FeePayment::create(['receipt_no' => "PMR-{$n}", 'fee_id' => $fee->id, 'amount' => 10, 'payment_date' => '2026-10-01']);

    return (object) [
        'class' => $class, 'section' => $section, 'subject' => $subject, 'student' => $student, 'type' => $type,
        'fee' => $fee, 'payment' => $payment,
        'teacher' => Teacher::create(['name' => "PM Teacher {$n}", 'email' => "pmt{$n}@x.com"]),
        'exam' => Exam::create(['name' => "PM Exam {$n}"]),
        'other' => User::factory()->create(['role' => 'teacher']),
    ];
}

/** Har route ke saamne un roles ki list jin ko wo khula hona chahiye */
function pm_matrix(object $f): array
{
    $all  = ['admin', 'teacher', 'accountant', 'receptionist'];
    $a    = ['admin'];

    return [
        // [method, url, allowed roles]
        ['GET',  '/dashboard', $all],
        ['GET',  '/profile',   $all],

        ['GET',  '/settings', $a], ['PUT', '/settings', $a],

        ['GET',  '/users', $a], ['GET', '/users/create', $a], ['POST', '/users', $a],
        ['GET',  "/users/{$f->other->id}/edit", $a], ['PUT', "/users/{$f->other->id}", $a], ['DELETE', "/users/{$f->other->id}", $a],

        ['GET',  '/classes', $a], ['GET', '/classes/create', $a], ['POST', '/classes', $a],
        ['GET',  "/classes/{$f->class->id}", $a], ['GET', "/classes/{$f->class->id}/edit", $a],
        ['POST', "/classes/{$f->class->id}/sections", $a],
        ['GET',  '/subjects', $a], ['GET', '/subjects/create', $a], ['GET', "/subjects/{$f->subject->id}", $a],
        ['GET',  '/teachers', $a], ['GET', '/teachers/create', $a], ['GET', "/teachers/{$f->teacher->id}", $a],
        ['POST', "/teachers/{$f->teacher->id}/assignments", $a],
        ['GET',  '/exams', $a], ['GET', '/exams/create', $a], ['POST', '/exams', $a], ['GET', "/exams/{$f->exam->id}/edit", $a],

        ['GET',  '/students', ['admin', 'teacher', 'accountant', 'receptionist']],
        // Teacher ke paas students.view hai, isliye 403 nahi; apni class ke bahar ka student 404 deta hai (neeche alag test)
        ['GET',  "/students/{$f->student->id}", ['admin', 'teacher', 'accountant', 'receptionist']],
        ['GET',  '/students/create', ['admin', 'receptionist']], ['POST', '/students', ['admin', 'receptionist']],
        ['GET',  "/students/{$f->student->id}/edit", ['admin', 'receptionist']],
        ['PUT',  "/students/{$f->student->id}", ['admin', 'receptionist']],

        ['GET',  '/attendances', ['admin', 'teacher']], ['GET', '/attendances/mark', ['admin', 'teacher']],
        ['POST', '/attendances', ['admin', 'teacher']],
        ['GET',  '/marks', ['admin', 'teacher']], ['GET', '/marks/entry', ['admin', 'teacher']], ['POST', '/marks', ['admin', 'teacher']],

        ['GET',  '/fee-types', ['admin', 'accountant']], ['GET', '/fee-types/create', ['admin', 'accountant']],
        ['POST', '/fee-types', ['admin', 'accountant']], ['GET', "/fee-types/{$f->type->id}/edit", ['admin', 'accountant']],

        ['GET',  '/fees', ['admin', 'accountant']], ['GET', "/fees/{$f->fee->id}", ['admin', 'accountant']],
        ['GET',  "/fees/{$f->fee->id}/voucher", ['admin', 'accountant']],
        ['GET',  "/fees/{$f->fee->id}/payments/{$f->payment->id}/receipt", ['admin', 'accountant']],
        ['GET',  '/fees/create', ['admin', 'accountant']], ['GET', '/fees/bulk', ['admin', 'accountant']],
        ['POST', '/fees', ['admin', 'accountant']], ['POST', '/fees/bulk', ['admin', 'accountant']],
        ['GET',  "/fees/{$f->fee->id}/edit", ['admin', 'accountant']], ['PUT', "/fees/{$f->fee->id}", ['admin', 'accountant']],
        ['POST', "/fees/{$f->fee->id}/payments", ['admin', 'accountant']],

        // Delete / cancel sirf admin
        ['DELETE', "/fees/{$f->fee->id}", $a],
        ['DELETE', "/fees/{$f->fee->id}/payments/{$f->payment->id}", $a],
        ['DELETE', "/students/{$f->student->id}", $a],
    ];
}

function pm_user(string $role): User
{
    return User::factory()->create(['role' => $role]);
}

test('every page and action is open only to the roles that should have it', function () {
    $problems = [];

    foreach (User::ROLES as $role) {
        $f = pm_fixtures();   // har role ke liye naye records (taake delete wale routes baad walon ko na bigarein)
        $user = pm_user($role);

        foreach (pm_matrix($f) as [$method, $url, $allowed]) {
            test()->actingAs($user);
            $status = test()->call($method, $url)->getStatusCode();
            $shouldPass = in_array($role, $allowed, true);

            if ($shouldPass && $status === 403) {
                $problems[] = "{$role} ko {$method} {$url} milna chahiye tha, magar 403 aaya";
            }
            if (! $shouldPass && $status !== 403) {
                $problems[] = "{$role} ko {$method} {$url} par 403 aana chahiye tha, magar {$status} aaya";
            }
        }
    }

    expect($problems)->toBe([]);
});

test('guests are sent to login for every page', function () {
    $f = pm_fixtures();
    $problems = [];
    foreach (pm_matrix($f) as [$method, $url]) {
        $res = $this->call($method, $url);
        if ($res->getStatusCode() !== 302 || ! str_contains((string) $res->headers->get('Location'), '/login')) {
            $problems[] = "{$method} {$url} -> {$res->getStatusCode()}";
        }
    }
    expect($problems)->toBe([]);
});

// ---------------- roles config / model ----------------

test('a brand new user is a teacher and active even before it is reloaded from the database', function () {
    $u = new User(['name' => 'X', 'email' => 'x@x.com']);
    expect($u->role)->toBe('teacher')->and($u->is_active)->toBeTrue();
});

test('hasPermission follows config/roles.php', function () {
    expect(pm_user('admin')->hasPermission('anything.at.all'))->toBeTrue()
        ->and(pm_user('accountant')->hasPermission('fees.manage'))->toBeTrue()
        ->and(pm_user('accountant')->hasPermission('fees.delete'))->toBeFalse()
        ->and(pm_user('receptionist')->hasPermission('students.manage'))->toBeTrue()
        ->and(pm_user('receptionist')->hasPermission('fees.view'))->toBeFalse()
        ->and(pm_user('teacher')->hasPermission('attendance.manage'))->toBeTrue()
        ->and(pm_user('teacher')->hasPermission('students.manage'))->toBeFalse();
});

test('an unknown role has no permissions at all', function () {
    $u = pm_user('teacher');
    $u->role = 'hacker';
    expect($u->hasPermission('students.view'))->toBeFalse()->and($u->role_label)->toBe('Hacker');
});

test('changing config/roles.php changes access without touching code', function () {
    config(['roles.permissions.receptionist' => ['students.view', 'students.manage', 'fees.view']]);
    $this->actingAs(pm_user('receptionist'))->get('/fees')->assertOk();

    config(['roles.permissions.receptionist' => ['students.view']]);
    $this->get('/fees')->assertForbidden();
    $this->get('/students/create')->assertForbidden();
});

// ---------------- sidebar ----------------

function pm_sidebar(string $role): array
{
    $html = test()->actingAs(pm_user($role))->get('/dashboard')->assertOk()->getContent();
    preg_match('#<nav class="sidebar-nav">(.*?)</nav>#s', $html, $nav);
    preg_match_all('#<span>([^<]+)</span>#', $nav[1], $m);

    return array_values(array_diff($m[1], ['Profile', 'Log out']));
}

test('each role sees only its own sidebar links', function () {
    expect(pm_sidebar('admin'))->toBe(['Dashboard', 'Students', 'Teachers', 'Classes', 'Subjects', 'Attendance', 'Exams', 'Marks / Results', 'Fees', 'Fee Types', 'Reports', 'Settings', 'Users']);
    expect(pm_sidebar('teacher'))->toBe(['Dashboard', 'Students', 'Attendance', 'Marks / Results', 'Reports']);
    expect(pm_sidebar('accountant'))->toBe(['Dashboard', 'Students', 'Fees', 'Fee Types', 'Reports']);
    expect(pm_sidebar('receptionist'))->toBe(['Dashboard', 'Students']);
});

test('the top menu shows the role', function () {
    $this->actingAs(pm_user('accountant'))->get('/dashboard')->assertSee('Accountant');
});

// ---------------- students: teacher scope + role-based page parts ----------------

function pm_two_classes(): array
{
    $c1 = SchoolClass::create(['name' => 'Scope One']);
    $a = $c1->sections()->create(['name' => 'A']);
    $b = $c1->sections()->create(['name' => 'B']);
    $c2 = SchoolClass::create(['name' => 'Scope Two']);
    $mk = fn ($class, $sec, $name) => Student::create([
        'admission_no' => 'SC-' . $name, 'first_name' => $name, 'gender' => 'male', 'guardian_name' => 'G',
        'guardian_phone' => '1', 'school_class_id' => $class->id, 'section_id' => $sec?->id,
    ]);

    return [$c1, $a, $b, $c2, $mk($c1, $a, 'InA'), $mk($c1, $b, 'InB'), $mk($c2, null, 'InTwo')];
}

function pm_teacher_for(SchoolClass $class, $section = null): User
{
    $user = pm_user('teacher');
    $t = Teacher::create(['name' => 'T', 'email' => $user->email, 'user_id' => $user->id]);
    $subject = Subject::firstOrCreate(['name' => 'PMS']);
    $class->subjects()->syncWithoutDetaching([$subject->id]);
    $t->assignments()->create(['school_class_id' => $class->id, 'section_id' => $section?->id, 'subject_id' => $subject->id]);

    return $user;
}

test('a teacher sees only the students of their own classes and sections', function () {
    [$c1, $a, $b, $c2, $inA, $inB, $inTwo] = pm_two_classes();

    $this->actingAs(pm_teacher_for($c1, $a));
    $this->get('/students')->assertOk()->assertSee('InA')->assertDontSee('InB')->assertDontSee('InTwo');
    $this->get("/students/{$inA->id}")->assertOk();
    $this->get("/students/{$inB->id}")->assertNotFound();
    $this->get("/students/{$inTwo->id}")->assertNotFound();

    // URL me doosri class ka filter daalne se bhi kuch nahi milta
    $this->get("/students?class_id={$c2->id}")->assertOk()->assertDontSee('InTwo');
    // dropdown me sirf apni class
    $html = $this->get('/students')->getContent();
    expect($html)->toContain('>Scope One</option>')->and($html)->not->toContain('>Scope Two</option>');
});

test('a teacher assigned to a whole class sees all its sections', function () {
    [$c1, , , , $inA, $inB, $inTwo] = pm_two_classes();

    $this->actingAs(pm_teacher_for($c1, null));
    $this->get('/students')->assertSee('InA')->assertSee('InB')->assertDontSee('InTwo');
});

test('a teacher without assignments or a teacher record sees no students', function () {
    pm_two_classes();
    $this->actingAs(pm_user('teacher'));
    $this->get('/students')->assertOk()->assertDontSee('InA')->assertDontSee('InTwo');
});

test('accountant, receptionist and admin are not limited to classes', function () {
    pm_two_classes();
    foreach (['admin', 'accountant', 'receptionist'] as $role) {
        $this->actingAs(pm_user($role))->get('/students')->assertSee('InA')->assertSee('InB')->assertSee('InTwo');
    }
});

test('admin sees every part of the student profile', function () {
    [$c1, , , , $inA] = pm_two_classes();
    $type = FeeType::create(['name' => 'T', 'default_amount' => 1]);
    Fee::create(['voucher_no' => 'PMV-X', 'student_id' => $inA->id, 'fee_type_id' => $type->id, 'amount' => 500]);

    $html = $this->actingAs(pm_user('admin'))->get("/students/{$inA->id}")->assertOk()->getContent();
    expect($html)->toContain('Fee vouchers')->toContain('PMV-X')->toContain('Marks entries')
        ->toContain('Fees balance')->toContain("students/{$inA->id}/edit")->toContain('Delete')
        ->toContain("classes/{$c1->id}");   // class ka link (sirf admin ke liye)
});

test('accountant sees fees but cannot edit or delete the student, and gets no class link', function () {
    [$c1, , , , $inA] = pm_two_classes();
    $type = FeeType::create(['name' => 'T', 'default_amount' => 1]);
    Fee::create(['voucher_no' => 'PMV-X', 'student_id' => $inA->id, 'fee_type_id' => $type->id, 'amount' => 500]);

    $html = $this->actingAs(pm_user('accountant'))->get("/students/{$inA->id}")->assertOk()->getContent();
    expect($html)->toContain('Fee vouchers')->toContain('PMV-X')->toContain('New voucher')
        ->and($html)->not->toContain('Marks entries')->not->toContain('>Attendance<')
        ->not->toContain("students/{$inA->id}/edit")->not->toContain('Delete')
        ->not->toContain("classes/{$c1->id}");
});

test('receptionist can edit but not delete, and sees no fees, attendance or marks', function () {
    [, , , , $inA] = pm_two_classes();
    $type = FeeType::create(['name' => 'T', 'default_amount' => 1]);
    Fee::create(['voucher_no' => 'PMV-X', 'student_id' => $inA->id, 'fee_type_id' => $type->id, 'amount' => 500]);

    $html = $this->actingAs(pm_user('receptionist'))->get("/students/{$inA->id}")->assertOk()->getContent();
    expect($html)->toContain("students/{$inA->id}/edit")->not->toContain('Delete')
        ->not->toContain('Fee vouchers')->not->toContain('PMV-X')->not->toContain('Fees balance')
        ->not->toContain('Marks entries');
});

test('teacher profile view hides fees and edit buttons but shows attendance and marks', function () {
    [$c1, $a, , , $inA] = pm_two_classes();

    $html = $this->actingAs(pm_teacher_for($c1, $a))->get("/students/{$inA->id}")->assertOk()->getContent();
    expect($html)->toContain('Marks entries')->toContain('Attendance')
        ->not->toContain('Fee vouchers')->not->toContain('Fees balance')
        ->not->toContain("students/{$inA->id}/edit")->not->toContain('Delete');
});

test('the students list hides add, edit and delete from roles that cannot use them', function () {
    [, , , , $inA] = pm_two_classes();

    $admin = $this->actingAs(pm_user('admin'))->get('/students')->getContent();
    expect($admin)->toContain('Add Student')->toContain("students/{$inA->id}/edit")->toContain('title="Delete"');

    $reception = $this->actingAs(pm_user('receptionist'))->get('/students')->getContent();
    expect($reception)->toContain('Add Student')->toContain("students/{$inA->id}/edit")->not->toContain('title="Delete"');

    $accountant = $this->actingAs(pm_user('accountant'))->get('/students')->getContent();
    expect($accountant)->not->toContain('Add Student')->not->toContain("students/{$inA->id}/edit")->not->toContain('title="Delete"');
});

// ---------------- error pages ----------------

test('a forbidden page shows the friendly 403 page', function () {
    $this->actingAs(pm_user('receptionist'))->get('/fees')->assertForbidden()
        ->assertSee('Ijazat nahi hai')->assertSee('Dashboard');
});

test('an unknown page shows the friendly 404 page for guests and users', function () {
    $this->get('/no-such-page')->assertNotFound()->assertSee('Page nahi mila')->assertSee('Login');
    $this->actingAs(pm_user('admin'))->get('/no-such-page')->assertNotFound()->assertSee('Dashboard');
});

test('error pages do not depend on the vite build', function () {
    foreach ([403, 404, 419, 429, 500, 503] as $code) {
        $html = view("errors.{$code}")->render();
        expect($html)->toContain((string) $code)->not->toContain('build/assets')->not->toContain('@vite');
    }
});

// ---------------- CSRF ----------------

test('every form that posts data contains a csrf token', function () {
    $missing = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

    foreach ($files as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }
        $src = file_get_contents($file->getPathname());
        $postForms = preg_match_all('/<form[^>]*method\s*=\s*["\'](post|put|patch|delete)["\']/i', $src);
        if ($postForms > 0 && substr_count($src, '@csrf') < $postForms) {
            $missing[] = str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $file->getPathname()) . " ({$postForms} forms, " . substr_count($src, '@csrf') . ' @csrf)';
        }
    }

    expect($missing)->toBe([]);
});

test('every state-changing route goes through the csrf middleware and no route is exempted', function () {
    // (Test mode me Laravel CSRF check khud band rakhta hai, isliye hum wiring check karte hain)
    $router = app('router');

    // 1) "web" group me CSRF middleware maujood hai
    $webGroup = app(\Illuminate\Contracts\Http\Kernel::class)->getMiddlewareGroups()['web'] ?? [];
    $hasCsrf = collect($webGroup)->contains(
        fn ($m) => is_string($m) && is_a($m, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, true)
    );
    expect($hasCsrf)->toBeTrue();

    // 2) Har POST/PUT/PATCH/DELETE route "web" group se guzarta hai
    $outside = [];
    foreach ($router->getRoutes() as $route) {
        if (! array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            continue;
        }
        // Laravel ka apna built-in route (local disk ke signed upload links), CSRF nahi balki signature se bachta hai
        if (str_starts_with(ltrim($route->uri(), '/'), 'storage/')) {
            continue;
        }
        if (! in_array('web', $route->gatherMiddleware(), true)) {
            $outside[] = implode('|', $route->methods()) . ' /' . ltrim($route->uri(), '/');
        }
    }
    expect($outside)->toBe([]);

    // 3) Kisi ne bootstrap/app.php me CSRF se koi URL alag (exempt) na kiya ho
    expect(file_get_contents(base_path('bootstrap/app.php')))->not->toContain('validateCsrfTokens');
});
