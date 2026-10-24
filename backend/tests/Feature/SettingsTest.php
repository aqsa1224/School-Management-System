<?php

use App\Models\Exam;
use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\Grading;
use App\Support\Pdf;
use App\Support\School;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
});

function st_data(array $over = []): array
{
    return array_merge([
        'school_name' => 'ABC Public School', 'school_address' => 'Main Road, Multan', 'school_phone' => '061-1234567',
        'school_email' => 'info@abc.test', 'currency' => 'PKR', 'pass_percentage' => '40',
    ], $over);
}

function st_requireGd(): void
{
    if (! extension_loaded('gd')) {
        test()->markTestSkipped('PHP GD extension band hai (php.ini me extension=gd on karein).');
    }
}

function st_logo(string $name = 'logo.png', int $w = 200, int $h = 120): UploadedFile
{
    return UploadedFile::fake()->image($name, $w, $h);
}

/** Ek voucher + payment + result ke liye zaroori records */
function st_records(): array
{
    $class = SchoolClass::create(['name' => 'Class 1']);
    $student = Student::create(['admission_no' => 'S-1', 'first_name' => 'Ali', 'gender' => 'male', 'guardian_name' => 'Khan Sahib',
        'guardian_phone' => '1', 'school_class_id' => $class->id]);
    $fee = Fee::create(['voucher_no' => 'VCH-2026-000001', 'student_id' => $student->id,
        'fee_type_id' => FeeType::create(['name' => 'Tuition', 'default_amount' => 1])->id, 'amount' => 1000]);
    $payment = FeePayment::create(['receipt_no' => 'RCP-2026-000001', 'fee_id' => $fee->id, 'amount' => 400, 'payment_date' => '2026-10-01']);
    $exam = Exam::create(['name' => 'Mid']);
    Mark::create(['exam_id' => $exam->id, 'student_id' => $student->id, 'subject_id' => Subject::create(['name' => 'Maths'])->id, 'obtained_marks' => 80]);

    return compact('student', 'fee', 'payment', 'exam');
}

// ---------------- access ----------------

test('guests are sent to login', function () {
    auth()->logout();
    $this->get('/settings')->assertRedirect('/login');
    $this->put('/settings', st_data())->assertRedirect('/login');
});

test('only admin can open or change settings', function () {
    foreach (['teacher', 'accountant', 'receptionist'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $this->get('/settings')->assertForbidden();
        $this->put('/settings', st_data(['school_name' => 'Hacked']))->assertForbidden();
    }
    expect(Setting::get('school_name'))->not->toBe('Hacked');
});

// ---------------- page + saving ----------------

test('the settings page shows defaults when nothing was saved', function () {
    config(['app.name' => 'Fallback School']);
    $html = $this->get('/settings')->assertOk()->getContent();

    expect($html)->toContain('value="Fallback School"')->toContain('value="Rs."')->toContain('value="40"')
        ->toContain('Abhi koi logo nahi');
});

test('settings are saved and shown again', function () {
    $this->put('/settings', st_data(['pass_percentage' => '35.5']))->assertSessionHasNoErrors()
        ->assertRedirect('/settings')->assertSessionHas('success');

    expect(Setting::get('school_name'))->toBe('ABC Public School')
        ->and(Setting::get('school_address'))->toBe('Main Road, Multan')
        ->and(Setting::get('school_phone'))->toBe('061-1234567')
        ->and(Setting::get('school_email'))->toBe('info@abc.test')
        ->and(Setting::get('currency'))->toBe('PKR')
        ->and(Grading::passMark())->toBe(35.5);

    $html = $this->get('/settings')->getContent();
    expect($html)->toContain('value="ABC Public School"')->toContain('value="Main Road, Multan"')
        ->toContain('value="PKR"')->toContain('value="35.5"');
});

test('optional fields can be cleared and spaces are trimmed', function () {
    $this->put('/settings', st_data());
    $this->put('/settings', st_data(['school_name' => '  Trim School  ', 'school_address' => '', 'school_phone' => '', 'school_email' => '']))
        ->assertSessionHasNoErrors();

    expect(Setting::get('school_name'))->toBe('Trim School')->and(Setting::get('school_address'))->toBe('')
        ->and(Setting::get('school_phone'))->toBe('')->and(Setting::get('school_email'))->toBe('');
});

test('settings validation', function () {
    $this->put('/settings', st_data(['school_name' => '']))->assertSessionHasErrors('school_name');
    $this->put('/settings', st_data(['school_name' => str_repeat('x', 101)]))->assertSessionHasErrors('school_name');
    $this->put('/settings', st_data(['school_email' => 'nope']))->assertSessionHasErrors('school_email');
    $this->put('/settings', st_data(['school_phone' => str_repeat('1', 31)]))->assertSessionHasErrors('school_phone');
    $this->put('/settings', st_data(['school_address' => str_repeat('x', 256)]))->assertSessionHasErrors('school_address');
    $this->put('/settings', st_data(['currency' => '']))->assertSessionHasErrors('currency');
    $this->put('/settings', st_data(['currency' => 'WAYTOOLONGCUR']))->assertSessionHasErrors('currency');
    foreach (['', '0', '0.5', '101', '-5', 'abc', '33.333'] as $bad) {
        $this->put('/settings', st_data(['pass_percentage' => $bad]))->assertSessionHasErrors('pass_percentage');
    }
    foreach (['1', '33', '100', '40.25'] as $good) {
        $this->put('/settings', st_data(['pass_percentage' => $good]))->assertSessionHasNoErrors();
    }
    expect(Setting::get('school_name'))->toBe('ABC Public School');   // bure data se kuch nahi badla
});

test('saving settings never touches other internal settings like the number counters', function () {
    Setting::set('seq_VCH_2026', '41');
    Setting::set('some_future_key', 'keep me');

    $this->put('/settings', st_data())->assertSessionHasNoErrors();

    expect(Setting::get('seq_VCH_2026'))->toBe('41')->and(Setting::get('some_future_key'))->toBe('keep me');
});

test('extra fields in the request are ignored', function () {
    $this->put('/settings', st_data(['seq_RCP_2026' => '999', 'hacker_key' => 'x']))->assertSessionHasNoErrors();
    expect(Setting::get('seq_RCP_2026'))->toBeNull()->and(Setting::get('hacker_key'))->toBeNull();
});

// ---------------- pass percentage drives results ----------------

test('the pass percentage from settings changes Pass or Fail in results', function () {
    $class = SchoolClass::create(['name' => 'C']);
    $student = Student::create(['admission_no' => 'P1', 'first_name' => 'Sara', 'gender' => 'female', 'guardian_name' => 'G', 'guardian_phone' => '1', 'school_class_id' => $class->id]);
    $exam = Exam::create(['name' => 'Mid']);
    Mark::create(['exam_id' => $exam->id, 'student_id' => $student->id, 'subject_id' => Subject::create(['name' => 'M'])->id, 'obtained_marks' => 35]);
    $url = "/marks?exam_id={$exam->id}&school_class_id={$class->id}";

    $this->get($url)->assertSee('Fail')->assertSee('Pass marks: 40%');
    $this->put('/settings', st_data(['pass_percentage' => '30']));
    $this->get($url)->assertSee('Pass marks: 30%')->assertDontSee('>Fail<', false);
});

// ---------------- school name / currency shown everywhere ----------------

test('the school name shows in the sidebar, footer and title for every role', function () {
    $this->put('/settings', st_data());

    foreach (['admin', 'teacher', 'accountant', 'receptionist'] as $role) {
        $html = $this->actingAs(User::factory()->create(['role' => $role]))->get('/dashboard')->assertOk()->getContent();
        expect($html)->toContain('<title>ABC Public School</title>')
            ->and($html)->toContain('<span class="sidebar-title">ABC Public School</span>')
            ->and($html)->toContain(date('Y') . ' ABC Public School');
    }
});

test('the login and welcome pages show the school name', function () {
    $this->put('/settings', st_data());
    auth()->logout();

    $this->get('/login')->assertOk()->assertSee('ABC Public School');
    $this->get('/')->assertOk()->assertSee('ABC Public School');
});

test('voucher, receipt and result card use the settings', function () {
    $this->put('/settings', st_data());
    $r = st_records();

    $voucher = strip_tags($this->get("/fees/{$r['fee']->id}/voucher")->getContent());
    expect($voucher)->toContain('ABC Public School')->toContain('Main Road, Multan')->toContain('061-1234567')->toContain('PKR 1,000.00');

    $receipt = strip_tags($this->get("/fees/{$r['fee']->id}/payments/{$r['payment']->id}/receipt")->getContent());
    expect($receipt)->toContain('ABC Public School')->toContain('PKR 400.00');

    $card = strip_tags($this->get("/marks/card/{$r['student']->id}?exam_id={$r['exam']->id}")->getContent());
    expect($card)->toContain('ABC Public School')->toContain('Main Road, Multan');
});

test('changing the currency changes new documents immediately', function () {
    $r = st_records();
    $this->put('/settings', st_data(['currency' => '$']));
    expect(strip_tags($this->get("/fees/{$r['fee']->id}/voucher")->getContent()))->toContain('$ 1,000.00');
});

test('School::info falls back to the app name and Rs. when nothing is saved', function () {
    config(['app.name' => 'My App']);
    Setting::query()->delete();
    $info = School::info();
    expect($info['name'])->toBe('My App')->and($info['currency'])->toBe('Rs.')->and($info['logo_url'])->toBeNull();
});

test('School::info does not crash when the settings table is missing', function () {
    config(['app.name' => 'Safe School']);
    Schema::drop('settings');
    expect(School::info()['name'])->toBe('Safe School');
});

// ---------------- logo ----------------

test('a logo can be uploaded and appears everywhere', function () {
    st_requireGd();
    $r = st_records();

    $this->put('/settings', st_data(['school_logo' => st_logo()]))->assertSessionHasNoErrors();

    $path = Setting::get('school_logo');
    expect($path)->toStartWith('logo/');
    Storage::disk('public')->assertExists($path);
    $url = School::info()['logo_url'];
    expect($url)->toContain("/storage/{$path}");

    expect($this->get('/dashboard')->getContent())->toContain($url);
    expect($this->get('/settings')->getContent())->toContain($url);
    expect($this->get("/fees/{$r['fee']->id}/voucher")->getContent())->toContain($url);
    expect($this->get("/fees/{$r['fee']->id}/payments/{$r['payment']->id}/receipt")->getContent())->toContain($url);
    expect($this->get("/marks/card/{$r['student']->id}?exam_id={$r['exam']->id}")->getContent())->toContain($url);
    auth()->logout();
    expect($this->get('/login')->getContent())->toContain($url);
    expect($this->get('/')->getContent())->toContain($url);
});

test('the logo uses the current host so it works on 127.0.0.1 and localhost alike', function () {
    st_requireGd();
    $this->put('/settings', st_data(['school_logo' => st_logo()]));

    $html = $this->get('http://127.0.0.1:8000/dashboard')->getContent();
    expect($html)->toContain('http://127.0.0.1:8000/storage/logo/');
});

test('the pdf version embeds the logo inside the file and is a valid pdf', function () {
    st_requireGd();
    if (! Pdf::available()) {
        $this->markTestSkipped('DomPDF install nahi hai (composer require dompdf/dompdf).');
    }
    $r = st_records();
    $this->put('/settings', st_data(['school_logo' => st_logo()]));

    $html = view('fees.documents.voucher', [
        'fee' => $r['fee']->load(['student.schoolClass', 'student.section', 'feeType', 'payments']),
        'school' => School::forDocument(true), 'pdf' => true,
    ])->render();
    expect($html)->toContain('data:image/png;base64,')->not->toContain('/storage/logo/');

    $res = $this->get("/fees/{$r['fee']->id}/voucher/pdf")->assertOk();
    expect(substr($res->getContent(), 0, 5))->toBe('%PDF-')
        ->and(preg_match_all('#/Type\s*/Page[^s]#', $res->getContent()))->toBe(1);

    $rp = $this->get("/fees/{$r['fee']->id}/payments/{$r['payment']->id}/receipt/pdf")->assertOk();
    expect(substr($rp->getContent(), 0, 5))->toBe('%PDF-');
});

test('a new logo replaces and deletes the old file', function () {
    st_requireGd();
    $this->put('/settings', st_data(['school_logo' => st_logo('a.png')]));
    $first = Setting::get('school_logo');

    $this->put('/settings', st_data(['school_logo' => st_logo('b.jpg')]))->assertSessionHasNoErrors();
    $second = Setting::get('school_logo');

    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
});

test('saving settings without a file keeps the current logo', function () {
    st_requireGd();
    $this->put('/settings', st_data(['school_logo' => st_logo()]));
    $path = Setting::get('school_logo');

    $this->put('/settings', st_data(['school_name' => 'Renamed School']));

    expect(Setting::get('school_logo'))->toBe($path);
    Storage::disk('public')->assertExists($path);
});

test('the logo can be removed and the default icon returns', function () {
    st_requireGd();
    $this->put('/settings', st_data(['school_logo' => st_logo()]));
    $path = Setting::get('school_logo');

    $this->put('/settings', st_data(['remove_logo' => 1]))->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing($path);
    expect(School::info()['logo_url'])->toBeNull();
    $html = $this->get('/dashboard')->getContent();
    expect($html)->not->toContain('/storage/logo/')->toContain('sidebar-logo');
});

test('removing a logo when there is none is harmless', function () {
    $this->put('/settings', st_data(['remove_logo' => 1]))->assertSessionHasNoErrors();
    expect(School::info()['logo_url'])->toBeNull();
});

test('a missing logo file on disk does not break any page', function () {
    st_requireGd();
    $r = st_records();
    $this->put('/settings', st_data(['school_logo' => st_logo()]));
    Storage::disk('public')->delete(Setting::get('school_logo'));   // file kisi wajah se ghayab

    $this->get('/dashboard')->assertOk();
    $this->get("/fees/{$r['fee']->id}/voucher")->assertOk();
    expect(School::logoDataUri())->toBeNull();
});

test('only real small images are accepted as the logo', function () {
    st_requireGd();
    $bad = [
        'a pdf'        => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf'),
        'a text file'  => UploadedFile::fake()->create('logo.png', 5, 'text/plain'),
        'an svg'       => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        'too big'      => UploadedFile::fake()->image('big.png', 400, 400)->size(2000),
        'too small'    => UploadedFile::fake()->image('tiny.png', 10, 10),
        'too large px' => UploadedFile::fake()->image('huge.png', 3500, 100),
    ];

    foreach ($bad as $label => $file) {
        $this->put('/settings', st_data(['school_logo' => $file]))->assertSessionHasErrors('school_logo');
    }
    expect(Setting::get('school_logo'))->toBeNull();
});

test('the worst case (big logo, very long names, long fee name and long note) still fits on one pdf page', function () {
    st_requireGd();
    if (! Pdf::available()) {
        $this->markTestSkipped('DomPDF install nahi hai (composer require dompdf/dompdf).');
    }

    $this->put('/settings', st_data(['school_logo' => st_logo('logo.png', 400, 160)]));
    $class = SchoolClass::create(['name' => 'Class 5']);
    $section = $class->sections()->create(['name' => 'A']);
    $student = Student::create([
        'admission_no' => 'W-1', 'first_name' => 'Muhammad Abdul Rehman', 'last_name' => 'Al-Farooqi', 'gender' => 'male',
        'guardian_name' => 'Ch. Ghulam Mustafa Al-Farooqi & Sons Traders', 'guardian_phone' => '1',
        'school_class_id' => $class->id, 'section_id' => $section->id,
    ]);
    $type = FeeType::create(['name' => 'Tuition Fee (Annual Package incl. Lab and Library)', 'default_amount' => 1]);
    $fee = Fee::create(['voucher_no' => 'VCH-2026-000001', 'student_id' => $student->id, 'fee_type_id' => $type->id,
        'amount' => 3000, 'discount' => 500, 'for_month' => '2026-10-01', 'due_date' => '2026-10-10']);
    $payment = FeePayment::create(['receipt_no' => 'RCP-2026-000001', 'fee_id' => $fee->id, 'amount' => 1000,
        'payment_date' => '2026-10-05', 'note' => str_repeat('Bohot lamba note likha hua hai. ', 6)]);

    foreach (["/fees/{$fee->id}/voucher/pdf", "/fees/{$fee->id}/payments/{$payment->id}/receipt/pdf"] as $url) {
        $body = $this->get($url)->assertOk()->getContent();
        expect(substr($body, 0, 5))->toBe('%PDF-')->and(preg_match_all('#/Type\s*/Page[^s]#', $body))->toBe(1);
    }
});
