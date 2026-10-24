<?php

use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Support\Pdf;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'accountant', 'name' => 'Accounts Officer']));

    $class = SchoolClass::create(['name' => 'Class 5']);
    $section = $class->sections()->create(['name' => 'B']);
    $this->student = Student::create([
        'admission_no' => 'D-1', 'first_name' => 'Hamza', 'last_name' => 'Raza', 'gender' => 'male',
        'guardian_name' => 'Raza Ali', 'guardian_phone' => '1', 'school_class_id' => $class->id, 'section_id' => $section->id,
    ]);
    $this->type = FeeType::create(['name' => 'Tuition Fee', 'default_amount' => 3000]);

    Setting::set('school_name', 'ABC Public School');
    Setting::set('school_address', 'Main Road, Multan');
    Setting::set('school_phone', '061-1234567');
    Setting::set('currency', 'PKR');

    $this->fee = Fee::create([
        'voucher_no' => 'VCH-2026-000007', 'student_id' => $this->student->id, 'fee_type_id' => $this->type->id,
        'amount' => 3000, 'discount' => 500, 'for_month' => '2026-10-01', 'due_date' => '2026-10-10',
    ]);
});

function fd_pay(Fee $fee, float $amount, string $no, string $date = '2026-10-05', string $method = 'cash'): FeePayment
{
    $p = FeePayment::create(['receipt_no' => $no, 'fee_id' => $fee->id, 'amount' => $amount, 'payment_date' => $date,
        'method' => $method, 'received_by' => User::where('name', 'Accounts Officer')->value('id')]);
    $fee->refresh()->refreshStatus();

    return $p;
}

function fd_text(string $html): string
{
    return preg_replace('/\s+/', ' ', strip_tags($html));
}

test('guests cannot open documents', function () {
    auth()->logout();
    $this->get("/fees/{$this->fee->id}/voucher")->assertRedirect('/login');
    $this->get("/fees/{$this->fee->id}/voucher/pdf")->assertRedirect('/login');
});

test('the voucher shows school, student, amounts and an unpaid stamp', function () {
    $text = fd_text($this->get("/fees/{$this->fee->id}/voucher")->assertOk()->getContent());

    expect($text)->toContain('ABC Public School')->toContain('Main Road, Multan')->toContain('061-1234567')
        ->toContain('FEE VOUCHER')->toContain('VCH-2026-000007')
        ->toContain('Hamza Raza')->toContain('D-1')->toContain('Class 5 - B')->toContain('Raza Ali')
        ->toContain('Tuition Fee - Oct 2026')->toContain('10 Oct 2026')
        ->toContain('PKR 3,000.00')->toContain('- PKR 500.00')->toContain('PKR 2,500.00')
        ->toContain('UNPAID');
});

test('the voucher has print and pdf buttons in the browser view', function () {
    $this->get("/fees/{$this->fee->id}/voucher")->assertSee('window.print()', false)
        ->assertSee("fees/{$this->fee->id}/voucher/pdf", false)->assertSee('Back');
});

test('a partly paid and a fully paid voucher show the right figures and stamp', function () {
    fd_pay($this->fee, 1000, 'RCP-2026-000001');
    $partial = fd_text($this->get("/fees/{$this->fee->id}/voucher")->getContent());
    expect($partial)->toContain('PARTIAL')->toContain('PKR 1,000.00')->toContain('PKR 1,500.00');

    fd_pay($this->fee, 1500, 'RCP-2026-000002');
    $paid = fd_text($this->get("/fees/{$this->fee->id}/voucher")->getContent());
    expect($paid)->toContain('PAID')->not->toContain('UNPAID')->toContain('PKR 0.00');
});

test('the receipt shows the payment, who received it and the balance at that time', function () {
    $first = fd_pay($this->fee, 1000, 'RCP-2026-000001', '2026-10-05', 'bank');
    $second = fd_pay($this->fee, 1500, 'RCP-2026-000002', '2026-10-08');

    $one = fd_text($this->get("/fees/{$this->fee->id}/payments/{$first->id}/receipt")->assertOk()->getContent());
    expect($one)->toContain('FEE RECEIPT')->toContain('RCP-2026-000001')->toContain('VCH-2026-000007')
        ->toContain('05 Oct 2026')->toContain('Bank')->toContain('Raza Ali')->toContain('Hamza Raza (D-1)')
        ->toContain('PKR 1,000.00')->toContain('PKR 1,500.00')   // baad me baqi
        ->toContain('PARTIALLY PAID')->toContain('Accounts Officer')->toContain('ABC Public School');

    $two = fd_text($this->get("/fees/{$this->fee->id}/payments/{$second->id}/receipt")->getContent());
    expect($two)->toContain('RCP-2026-000002')->toContain('PAID IN FULL')->toContain('PKR 0.00');
});

test('a receipt cannot be opened through another vouchers url', function () {
    $p = fd_pay($this->fee, 1000, 'RCP-2026-000001');
    $other = Fee::create(['voucher_no' => 'OTHER-1', 'student_id' => $this->student->id, 'fee_type_id' => $this->type->id, 'amount' => 10]);

    $this->get("/fees/{$other->id}/payments/{$p->id}/receipt")->assertNotFound();
    $this->get("/fees/{$other->id}/payments/{$p->id}/receipt/pdf")->assertNotFound();
});

test('the document falls back to the app name and Rs. when settings are empty', function () {
    Setting::where('key', 'school_name')->delete();
    Setting::where('key', 'currency')->delete();
    config(['app.name' => 'My App School']);

    $text = fd_text($this->get("/fees/{$this->fee->id}/voucher")->getContent());
    expect($text)->toContain('My App School')->toContain('Rs. 3,000.00');
});

// ---------------- PDF ----------------

function fd_requirePdf(): void
{
    if (! Pdf::available()) {
        test()->markTestSkipped('DomPDF install nahi hai (composer require dompdf/dompdf).');
    }
}

test('the voucher downloads as a real pdf', function () {
    fd_requirePdf();

    $res = $this->get("/fees/{$this->fee->id}/voucher/pdf")->assertOk();
    $body = $res->getContent();

    expect($res->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($res->headers->get('Content-Disposition'))->toContain('attachment')->toContain('voucher-VCH-2026-000007.pdf')
        ->and(substr($body, 0, 5))->toBe('%PDF-')
        ->and(rtrim($body))->toEndWith('%%EOF')
        ->and(strlen($body))->toBeGreaterThan(2000)
        ->and(preg_match_all('#/Type\s*/Page[^s]#', $body))->toBe(1);   // sirf ek page
});

test('the receipt downloads as a real pdf', function () {
    fd_requirePdf();
    $p = fd_pay($this->fee, 1000, 'RCP-2026-000001');

    $res = $this->get("/fees/{$this->fee->id}/payments/{$p->id}/receipt/pdf")->assertOk();

    expect($res->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($res->headers->get('Content-Disposition'))->toContain('receipt-RCP-2026-000001.pdf')
        ->and(substr($res->getContent(), 0, 5))->toBe('%PDF-');
});

test('the pdf template has no browser toolbar', function () {
    fd_requirePdf();
    // PDF wale HTML me Print/Back buttons nahi hone chahiyen (browser wale me hain)
    $html = view('fees.documents.voucher', [
        'fee' => $this->fee->load(['student.schoolClass', 'student.section', 'feeType', 'payments']),
        'school' => ['name' => 'X', 'address' => '', 'phone' => '', 'currency' => 'Rs.'], 'pdf' => true,
    ])->render();

    expect($html)->not->toContain('window.print()')->not->toContain('class="toolbar"');
});

test('a student with a long name and unicode still makes a valid pdf', function () {
    fd_requirePdf();
    $this->student->update(['first_name' => 'Muhammad Abdul Rehman Al-Farooqi', 'guardian_name' => 'Ch. Ghulam Mustafa & Sons <Traders>']);

    $res = $this->get("/fees/{$this->fee->id}/voucher/pdf")->assertOk();
    expect(substr($res->getContent(), 0, 5))->toBe('%PDF-');
});

test('the pdf version uses the full page width class while the browser version does not', function () {
    $data = fn (bool $pdf) => [
        'fee' => $this->fee->load(['student.schoolClass', 'student.section', 'feeType', 'payments']),
        'school' => ['name' => 'X', 'address' => '', 'phone' => '', 'currency' => 'Rs.'], 'pdf' => $pdf,
    ];

    expect(view('fees.documents.voucher', $data(true))->render())->toContain('<body class="pdf">');
    expect(view('fees.documents.voucher', $data(false))->render())->toContain('<body class="">');
});

test('the pdf is one page for voucher and receipt even with a long note', function () {
    fd_requirePdf();
    $p = fd_pay($this->fee, 1000, 'RCP-2026-000001');
    $p->update(['note' => str_repeat('Bohot lamba note ', 12)]);

    foreach (["/fees/{$this->fee->id}/voucher/pdf", "/fees/{$this->fee->id}/payments/{$p->id}/receipt/pdf"] as $url) {
        $body = $this->get($url)->assertOk()->getContent();
        expect(preg_match_all('#/Type\s*/Page[^s]#', $body))->toBe(1);
    }
});
