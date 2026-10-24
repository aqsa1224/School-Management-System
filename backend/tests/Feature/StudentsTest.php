<?php

use App\Models\Attendance;
use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Storage::fake('public');

    $this->class = SchoolClass::create(['name' => 'Class 1']);
    $this->sectionA = $this->class->sections()->create(['name' => 'A']);
    $this->sectionB = $this->class->sections()->create(['name' => 'B']);
});

function studentData(array $over = []): array
{
    return array_merge([
        'admission_no' => 'ADM-0001', 'first_name' => 'Ali', 'last_name' => 'Khan', 'gender' => 'male',
        'guardian_name' => 'Khan Sahib', 'guardian_phone' => '0300-1111111',
        'school_class_id' => test()->class->id, 'section_id' => test()->sectionA->id, 'status' => 'active',
    ], $over);
}

function makeStudentRow(array $over = []): Student
{
    return Student::create(array_merge([
        'admission_no' => 'ADM-' . random_int(10000, 99999), 'first_name' => 'Ali', 'gender' => 'male',
        'guardian_name' => 'G', 'guardian_phone' => '1', 'school_class_id' => test()->class->id,
        'section_id' => test()->sectionA->id,
    ], $over));
}

test('guests cannot open students pages', function () {
    auth()->logout();
    $this->get('/students')->assertRedirect('/login');
});

test('students pages open', function () {
    $s = makeStudentRow(['first_name' => 'Hamza']);

    $this->get('/students')->assertOk()->assertSee('Hamza');
    $this->get('/students/create')->assertOk()->assertSee('ADM-');
    $this->get("/students/{$s->id}")->assertOk()->assertSee('Student details');
    $this->get("/students/{$s->id}/edit")->assertOk();
});

test('create form suggests the next admission number', function () {
    makeStudentRow(['admission_no' => 'ADM-0001']);
    $next = Student::nextAdmissionNo();
    expect($next)->toStartWith('ADM-')->and(Student::where('admission_no', $next)->exists())->toBeFalse();
});

test('a student is created and shows on the list', function () {
    $this->post('/students', studentData())->assertRedirect();

    $s = Student::where('admission_no', 'ADM-0001')->first();
    expect($s)->not->toBeNull()
        ->and($s->school_class_id)->toBe($this->class->id)
        ->and($s->section_id)->toBe($this->sectionA->id)
        ->and($s->status)->toBe('active');
    $this->get('/students')->assertSee('Ali Khan')->assertSee('Class 1 - A');
});

test('required fields are validated', function () {
    $this->post('/students', [])->assertSessionHasErrors([
        'admission_no', 'first_name', 'gender', 'guardian_name', 'guardian_phone', 'school_class_id', 'status',
    ]);
    expect(Student::count())->toBe(0);
});

test('admission number must be unique', function () {
    makeStudentRow(['admission_no' => 'ADM-0001']);
    $this->post('/students', studentData())->assertSessionHasErrors('admission_no');
});

test('enums, dates and email are validated', function () {
    $this->post('/students', studentData(['gender' => 'zzz', 'admission_no' => 'X1']))->assertSessionHasErrors('gender');
    $this->post('/students', studentData(['status' => 'zzz', 'admission_no' => 'X2']))->assertSessionHasErrors('status');
    $this->post('/students', studentData(['date_of_birth' => 'abc', 'admission_no' => 'X3']))->assertSessionHasErrors('date_of_birth');
    $this->post('/students', studentData(['date_of_birth' => now()->addDay()->toDateString(), 'admission_no' => 'X4']))->assertSessionHasErrors('date_of_birth');
    $this->post('/students', studentData(['email' => 'nope', 'admission_no' => 'X5']))->assertSessionHasErrors('email');
    $this->post('/students', studentData(['school_class_id' => 9999, 'admission_no' => 'X6']))->assertSessionHasErrors('school_class_id');
    expect(Student::count())->toBe(0);
});

test('section is required when the class has sections and must belong to that class', function () {
    $this->post('/students', studentData(['section_id' => null]))->assertSessionHasErrors('section_id');

    $two = SchoolClass::create(['name' => 'Class 2']);
    $foreign = $two->sections()->create(['name' => 'Z']);
    $this->post('/students', studentData(['section_id' => $foreign->id]))->assertSessionHasErrors('section_id');
    expect(Student::count())->toBe(0);
});

test('a class without sections does not need a section', function () {
    $plain = SchoolClass::create(['name' => 'Nursery']);

    $this->post('/students', studentData(['school_class_id' => $plain->id, 'section_id' => null]))
        ->assertSessionHasNoErrors();
    expect(Student::first()->section_id)->toBeNull();
});

test('a photo can be uploaded, replaced and removed', function () {
    if (! extension_loaded('gd')) { $this->markTestSkipped('PHP GD extension band hai (php.ini me extension=gd on karein).'); }
    $this->post('/students', studentData(['photo' => UploadedFile::fake()->image('a.jpg', 200, 200)]))->assertSessionHasNoErrors();
    $s = Student::first();
    Storage::disk('public')->assertExists($s->photo);
    $first = $s->photo;
    expect($s->photo_url)->not->toBeNull();

    $this->put("/students/{$s->id}", studentData(['photo' => UploadedFile::fake()->image('b.png', 200, 200)]))->assertSessionHasNoErrors();
    $s->refresh();
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($s->photo);

    $this->put("/students/{$s->id}", studentData(['remove_photo' => 1]));
    $s->refresh();
    expect($s->photo)->toBeNull();
});

test('only real small images are accepted as photo', function () {
    if (! extension_loaded('gd')) { $this->markTestSkipped('PHP GD extension band hai (php.ini me extension=gd on karein).'); }
    $this->post('/students', studentData(['photo' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')]))
        ->assertSessionHasErrors('photo');
    $this->post('/students', studentData(['admission_no' => 'B1', 'photo' => UploadedFile::fake()->image('big.jpg')->size(3000)]))
        ->assertSessionHasErrors('photo');
    expect(Student::count())->toBe(0);
});

test('update keeps own admission number and can change class/section/status', function () {
    $s = makeStudentRow(['admission_no' => 'ADM-0001']);

    $this->put("/students/{$s->id}", studentData(['section_id' => $this->sectionB->id, 'status' => 'left']))
        ->assertSessionHasNoErrors()->assertRedirect("/students/{$s->id}");

    $s->refresh();
    expect($s->section_id)->toBe($this->sectionB->id)->and($s->status)->toBe('left');
});

test('update cannot take another admission number', function () {
    makeStudentRow(['admission_no' => 'ADM-0001']);
    $two = makeStudentRow(['admission_no' => 'ADM-0002']);

    $this->put("/students/{$two->id}", studentData(['admission_no' => 'ADM-0001']))->assertSessionHasErrors('admission_no');
});

test('a student without records can be deleted and the photo file goes too', function () {
    if (! extension_loaded('gd')) { $this->markTestSkipped('PHP GD extension band hai (php.ini me extension=gd on karein).'); }
    $this->post('/students', studentData(['photo' => UploadedFile::fake()->image('a.jpg')]));
    $s = Student::first();
    $path = $s->photo;

    $this->delete("/students/{$s->id}")->assertRedirect('/students')->assertSessionHas('success');

    expect(Student::count())->toBe(0);
    Storage::disk('public')->assertMissing($path);
});

test('a student with attendance, marks or fees cannot be deleted', function () {
    $withAttendance = makeStudentRow();
    Attendance::create(['student_id' => $withAttendance->id, 'date' => '2026-10-01', 'status' => 'present']);

    $withFee = makeStudentRow();
    $type = FeeType::create(['name' => 'Tuition']);
    Fee::create(['voucher_no' => 'V1', 'student_id' => $withFee->id, 'fee_type_id' => $type->id, 'amount' => 100]);

    foreach ([$withAttendance, $withFee] as $s) {
        $this->delete("/students/{$s->id}")->assertSessionHas('error');
        expect(Student::find($s->id))->not->toBeNull();
    }
});

test('search finds by name, full name, admission number, guardian and phone', function () {
    makeStudentRow(['admission_no' => 'ADM-7777', 'first_name' => 'Hamza', 'last_name' => 'Raza', 'guardian_name' => 'Raza Ali', 'guardian_phone' => '0321-5550000']);
    makeStudentRow(['admission_no' => 'ADM-8888', 'first_name' => 'Sara', 'last_name' => 'Noor']);

    $this->get('/students?q=Hamza')->assertSee('Hamza')->assertDontSee('Sara');
    $this->get('/students?q=Hamza+Raza')->assertSee('Hamza')->assertDontSee('Sara');
    $this->get('/students?q=ADM-8888')->assertSee('Sara')->assertDontSee('Hamza');
    $this->get('/students?q=Raza+Ali')->assertSee('Hamza');
    $this->get('/students?q=5550000')->assertSee('Hamza')->assertDontSee('Sara');
    $this->get('/students?q=zzzz')->assertSee('koi student nahi mila');
});

test('filters by class, section, status and gender work together', function () {
    $two = SchoolClass::create(['name' => 'Class 2']);
    makeStudentRow(['first_name' => 'InA', 'section_id' => $this->sectionA->id]);
    makeStudentRow(['first_name' => 'InB', 'section_id' => $this->sectionB->id]);
    makeStudentRow(['first_name' => 'InTwo', 'school_class_id' => $two->id, 'section_id' => null]);
    makeStudentRow(['first_name' => 'LeftOne', 'status' => 'left']);
    makeStudentRow(['first_name' => 'Girl', 'gender' => 'female']);

    $this->get("/students?class_id={$two->id}")->assertSee('InTwo')->assertDontSee('InA');
    $this->get("/students?section_id={$this->sectionB->id}")->assertSee('InB')->assertDontSee('InA');
    $this->get('/students?status=left')->assertSee('LeftOne')->assertDontSee('InA');
    $this->get('/students?gender=female')->assertSee('Girl')->assertDontSee('InA');
    $this->get("/students?class_id={$this->class->id}&status=active&gender=male")->assertSee('InA')->assertDontSee('LeftOne')->assertDontSee('Girl');
});

test('the list is paginated and filters survive page links', function () {
    foreach (range(1, 12) as $i) {
        makeStudentRow(['first_name' => sprintf('Stu%02d', $i)]);
    }
    $this->get('/students')->assertSee('Stu01')->assertDontSee('Stu11')->assertSee('12 student(s)');
    $this->get('/students?page=2&status=active')->assertSee('Stu11');
});

test('the profile page shows attendance and fee summary', function () {
    $s = makeStudentRow();
    Attendance::create(['student_id' => $s->id, 'date' => '2026-10-01', 'status' => 'present']);
    Attendance::create(['student_id' => $s->id, 'date' => '2026-10-02', 'status' => 'absent']);
    $type = FeeType::create(['name' => 'Tuition']);
    $fee = Fee::create(['voucher_no' => 'V1', 'student_id' => $s->id, 'fee_type_id' => $type->id, 'amount' => 3000, 'discount' => 500]);
    FeePayment::create(['receipt_no' => 'R1', 'fee_id' => $fee->id, 'amount' => 1000, 'payment_date' => '2026-10-05']);

    $this->get("/students/{$s->id}")->assertOk()
        ->assertSee('1 P /')->assertSee('1 A /')
        ->assertSee('1,500.00')   // balance
        ->assertSee('Paid 1,000.00 of 2,500.00');
});

test('the sidebar shows the Students link and the dashboard counts students', function () {
    makeStudentRow();
    $this->get('/dashboard')->assertOk()->assertSee('Students');
});
