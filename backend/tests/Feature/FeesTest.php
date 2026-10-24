<?php

use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'accountant']));

    $this->class1 = SchoolClass::create(['name' => 'Class 1']);
    $this->secA = $this->class1->sections()->create(['name' => 'A']);
    $this->secB = $this->class1->sections()->create(['name' => 'B']);
    $this->class2 = SchoolClass::create(['name' => 'Class 2']);

    $this->ali = fe_student($this->class1, $this->secA, 'Ali');
    $this->sara = fe_student($this->class1, $this->secB, 'Sara');
    $this->zoya = fe_student($this->class2, null, 'Zoya');

    $this->tuition = FeeType::create(['name' => 'Tuition Fee', 'default_amount' => 3000]);
    $this->exam = FeeType::create(['name' => 'Exam Fee', 'default_amount' => 500]);
});

function fe_student(SchoolClass $class, $section, string $name, string $status = 'active'): Student
{
    static $n = 0;
    $n++;

    return Student::create([
        'admission_no' => "FE-{$n}", 'first_name' => $name, 'gender' => 'male', 'guardian_name' => "Guardian {$name}",
        'guardian_phone' => "0300-00{$n}", 'school_class_id' => $class->id, 'section_id' => $section?->id, 'status' => $status,
    ]);
}

function fe_voucher(Student $s, FeeType $t, array $over = []): Fee
{
    static $v = 0;
    $v++;

    return Fee::create(array_merge([
        'voucher_no' => "T-{$v}", 'student_id' => $s->id, 'fee_type_id' => $t->id, 'amount' => 1000,
    ], $over));
}

function fe_pay(Fee $fee, float $amount, string $date = '2026-10-05'): FeePayment
{
    static $r = 0;
    $r++;
    $p = FeePayment::create(['receipt_no' => "TR-{$r}", 'fee_id' => $fee->id, 'amount' => $amount, 'payment_date' => $date]);
    $fee->refresh()->refreshStatus();

    return $p;
}

function fe_payload($t, array $over = []): array
{
    return array_merge([
        'student_id' => $t->ali->id, 'fee_type_id' => $t->tuition->id, 'for_month' => '2026-10',
        'amount' => '3000', 'discount' => '0', 'due_date' => '2026-10-10',
    ], $over);
}

test('guests cannot open fee pages', function () {
    auth()->logout();
    foreach (['/fees', '/fees/create', '/fees/bulk', '/fees/1', '/fees/1/voucher'] as $url) {
        $this->get($url)->assertRedirect('/login');
    }
    $this->post('/fees', [])->assertRedirect('/login');
    $this->post('/fees/bulk', [])->assertRedirect('/login');
});

test('fee pages open', function () {
    $fee = fe_voucher($this->ali, $this->tuition);

    $this->get('/fees')->assertOk()->assertSee($fee->voucher_no);
    $this->get('/fees/create')->assertOk()->assertSee('Ali');
    $this->get("/fees/create?student_id={$this->sara->id}")->assertOk();
    $this->get("/fees/{$fee->id}")->assertOk()->assertSee('Receive payment');
    $this->get("/fees/{$fee->id}/edit")->assertOk();
    $this->get('/fees/bulk')->assertOk()->assertSee('Generate Vouchers');
});

// ---------------- create ----------------

test('a voucher is created with an automatic number, unpaid status and first-of-month date', function () {
    $this->post('/fees', fe_payload($this))->assertSessionHasNoErrors()->assertRedirect();
    $this->post('/fees', fe_payload($this, ['student_id' => $this->sara->id]));

    $fees = Fee::orderBy('id')->get();
    $year = now()->format('Y');
    expect($fees)->toHaveCount(2)
        ->and($fees[0]->voucher_no)->toBe("VCH-{$year}-000001")
        ->and($fees[1]->voucher_no)->toBe("VCH-{$year}-000002")
        ->and($fees[0]->status)->toBe('unpaid')
        ->and($fees[0]->for_month->toDateString())->toBe('2026-10-01')
        ->and($fees[0]->due_date->toDateString())->toBe('2026-10-10')
        ->and((float) $fees[0]->discount)->toBe(0.0);
});

test('a one-time fee can be created without a month', function () {
    $this->post('/fees', fe_payload($this, ['for_month' => '', 'due_date' => '']))->assertSessionHasNoErrors();
    $fee = Fee::first();
    expect($fee->for_month)->toBeNull()->and($fee->due_date)->toBeNull();
});

test('voucher validation', function () {
    $left = fe_student($this->class1, $this->secA, 'Gone', 'left');

    $this->post('/fees', fe_payload($this, ['student_id' => '']))->assertSessionHasErrors('student_id');
    $this->post('/fees', fe_payload($this, ['student_id' => 9999]))->assertSessionHasErrors('student_id');
    $this->post('/fees', fe_payload($this, ['student_id' => $left->id]))->assertSessionHasErrors('student_id');
    $this->post('/fees', fe_payload($this, ['fee_type_id' => 9999]))->assertSessionHasErrors('fee_type_id');
    $this->post('/fees', fe_payload($this, ['amount' => '']))->assertSessionHasErrors('amount');
    $this->post('/fees', fe_payload($this, ['amount' => '0']))->assertSessionHasErrors('amount');
    $this->post('/fees', fe_payload($this, ['amount' => '-10']))->assertSessionHasErrors('amount');
    $this->post('/fees', fe_payload($this, ['amount' => 'abc']))->assertSessionHasErrors('amount');
    $this->post('/fees', fe_payload($this, ['amount' => '10.123']))->assertSessionHasErrors('amount');
    $this->post('/fees', fe_payload($this, ['discount' => '3500']))->assertSessionHasErrors('discount');
    $this->post('/fees', fe_payload($this, ['discount' => '-1']))->assertSessionHasErrors('discount');
    $this->post('/fees', fe_payload($this, ['for_month' => '2026-13']))->assertSessionHasErrors('for_month');
    $this->post('/fees', fe_payload($this, ['for_month' => 'October']))->assertSessionHasErrors('for_month');
    $this->post('/fees', fe_payload($this, ['due_date' => 'abc']))->assertSessionHasErrors('due_date');

    expect(Fee::count())->toBe(0);
});

test('the same fee type for the same student and month cannot be created twice', function () {
    $this->post('/fees', fe_payload($this))->assertSessionHasNoErrors();

    $this->post('/fees', fe_payload($this))->assertSessionHasErrors('fee_type_id');
    // doosra month, doosra student, doosri fee type theek hain
    $this->post('/fees', fe_payload($this, ['for_month' => '2026-11']))->assertSessionHasNoErrors();
    $this->post('/fees', fe_payload($this, ['student_id' => $this->sara->id]))->assertSessionHasNoErrors();
    $this->post('/fees', fe_payload($this, ['fee_type_id' => $this->exam->id]))->assertSessionHasNoErrors();

    expect(Fee::count())->toBe(4);
});

test('a one-time fee without month also cannot be duplicated for a student', function () {
    $this->post('/fees', fe_payload($this, ['for_month' => '']))->assertSessionHasNoErrors();
    $this->post('/fees', fe_payload($this, ['for_month' => '']))->assertSessionHasErrors('fee_type_id');
    expect(Fee::count())->toBe(1);
});

// ---------------- update / delete ----------------

test('a voucher can be edited and keeps its own month without a duplicate error', function () {
    $this->post('/fees', fe_payload($this));
    $fee = Fee::first();

    $this->put("/fees/{$fee->id}", ['fee_type_id' => $this->tuition->id, 'for_month' => '2026-10', 'amount' => '3500', 'discount' => '500', 'due_date' => '2026-10-20'])
        ->assertSessionHasNoErrors()->assertRedirect("/fees/{$fee->id}");

    $fee->refresh();
    expect((float) $fee->amount)->toBe(3500.0)->and((float) $fee->discount)->toBe(500.0)
        ->and($fee->due_date->toDateString())->toBe('2026-10-20')->and($fee->voucher_no)->toContain('VCH-');
});

test('the student of a voucher cannot be changed', function () {
    $fee = fe_voucher($this->ali, $this->tuition);
    $this->put("/fees/{$fee->id}", ['student_id' => $this->sara->id, 'fee_type_id' => $this->tuition->id, 'amount' => 1000])
        ->assertSessionHasErrors('student_id');
    expect($fee->refresh()->student_id)->toBe($this->ali->id);
});

test('editing cannot create a duplicate of another voucher', function () {
    fe_voucher($this->ali, $this->tuition, ['for_month' => '2026-10-01']);
    $other = fe_voucher($this->ali, $this->tuition, ['for_month' => '2026-11-01']);

    $this->put("/fees/{$other->id}", ['fee_type_id' => $this->tuition->id, 'for_month' => '2026-10', 'amount' => 1000])
        ->assertSessionHasErrors('fee_type_id');
});

test('net amount cannot be lowered below what was already paid', function () {
    $fee = fe_voucher($this->ali, $this->tuition, ['amount' => 1000]);
    fe_pay($fee, 600);

    $this->put("/fees/{$fee->id}", ['fee_type_id' => $this->tuition->id, 'amount' => 1000, 'discount' => 500])   // net 500 < 600
        ->assertSessionHasErrors('amount');
    $this->put("/fees/{$fee->id}", ['fee_type_id' => $this->tuition->id, 'amount' => 500])                          // net 500 < 600
        ->assertSessionHasErrors('amount');
    expect((float) $fee->refresh()->amount)->toBe(1000.0);
});

test('editing amount or discount recalculates the status', function () {
    $fee = fe_voucher($this->ali, $this->tuition, ['amount' => 1000]);
    fe_pay($fee, 1000);
    expect($fee->refresh()->status)->toBe('paid');

    // Amount barhane se baqi nikal aaya -> partial
    $this->put("/fees/{$fee->id}", ['fee_type_id' => $this->tuition->id, 'amount' => 1500])->assertSessionHasNoErrors();
    expect($fee->refresh()->status)->toBe('partial');

    // Discount de kar total barabar -> paid
    $this->put("/fees/{$fee->id}", ['fee_type_id' => $this->tuition->id, 'amount' => 1500, 'discount' => 500])->assertSessionHasNoErrors();
    expect($fee->refresh()->status)->toBe('paid');
});

test('a voucher without payments can be deleted, one with payments cannot', function () {
    // Voucher delete karna sirf Admin ka kaam hai (fees.delete)
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $free = fe_voucher($this->ali, $this->tuition);
    $used = fe_voucher($this->sara, $this->tuition);
    fe_pay($used, 100);

    $this->delete("/fees/{$free->id}")->assertRedirect('/fees')->assertSessionHas('success');
    $this->delete("/fees/{$used->id}")->assertSessionHas('error');

    expect(Fee::find($free->id))->toBeNull()->and(Fee::find($used->id))->not->toBeNull();
});

// ---------------- list, filters, totals ----------------

test('the list shows paid, balance and an overdue badge', function () {
    $overdue = fe_voucher($this->ali, $this->tuition, ['amount' => 1000, 'due_date' => today()->subDays(3)]);
    fe_pay($overdue, 400);
    fe_voucher($this->sara, $this->tuition, ['amount' => 1000, 'due_date' => today()->addDays(5)]);
    $paid = fe_voucher($this->zoya, $this->tuition, ['amount' => 1000, 'due_date' => today()->subDays(10)]);
    fe_pay($paid, 1000);

    $html = $this->get('/fees')->getContent();
    preg_match_all('#<tr>.*?</tr>#s', $html, $m);
    $rows = collect($m[0])->map(fn ($tr) => preg_replace('/\s+/', ' ', strip_tags($tr)));

    $ali = $rows->first(fn ($r) => str_contains($r, 'Ali'));
    $zoya = $rows->first(fn ($r) => str_contains($r, 'Zoya'));
    $sara = $rows->first(fn ($r) => str_contains($r, 'Sara'));

    expect($ali)->toContain('1,000.00')->toContain('400.00')->toContain('600.00')->toContain('Partial')->toContain('Overdue');
    expect($sara)->toContain('Unpaid')->not->toContain('Overdue');
    expect($zoya)->toContain('Paid')->not->toContain('Overdue');   // poori ada hui to overdue nahi
});

test('totals show billed, collected and balance and follow the filters', function () {
    $a = fe_voucher($this->ali, $this->tuition, ['amount' => 1000, 'discount' => 100]);   // net 900
    fe_pay($a, 300);
    $b = fe_voucher($this->zoya, $this->exam, ['amount' => 500]);                           // net 500
    fe_pay($b, 500);

    $all = $this->get('/fees')->getContent();
    expect($all)->toContain('1,400.00')    // billed
        ->and($all)->toContain('800.00')   // collected
        ->and($all)->toContain('600.00');  // balance

    $onlyClass1 = $this->get("/fees?class_id={$this->class1->id}")->getContent();
    expect($onlyClass1)->toContain('900.00')->toContain('300.00')->toContain('600.00');
});

test('search finds by student name, admission number, voucher number and guardian phone', function () {
    $a = fe_voucher($this->ali, $this->tuition);
    $s = fe_voucher($this->sara, $this->tuition);

    $this->get('/fees?q=Ali')->assertSee($a->voucher_no)->assertDontSee($s->voucher_no);
    $this->get("/fees?q={$this->sara->admission_no}")->assertSee($s->voucher_no)->assertDontSee($a->voucher_no);
    $this->get("/fees?q={$a->voucher_no}")->assertSee($a->voucher_no)->assertDontSee($s->voucher_no);
    $this->get("/fees?q={$this->ali->guardian_phone}")->assertSee($a->voucher_no)->assertDontSee($s->voucher_no);
    $this->get('/fees?q=zzzz')->assertSee('koi voucher nahi mila');
});

test('filters by status, overdue, class, fee type and month', function () {
    $unpaid = fe_voucher($this->ali, $this->tuition, ['for_month' => '2026-10-01', 'due_date' => today()->addDays(9)]);
    $partial = fe_voucher($this->sara, $this->exam, ['for_month' => '2026-11-01']);
    fe_pay($partial, 100);
    $paid = fe_voucher($this->zoya, $this->tuition, ['for_month' => '2026-10-01', 'due_date' => today()->subDays(30)]);
    fe_pay($paid, 1000);
    $late = fe_voucher($this->ali, $this->exam, ['for_month' => '2026-09-01', 'due_date' => today()->subDays(2)]);

    $this->get('/fees?status=unpaid')->assertSee($unpaid->voucher_no)->assertSee($late->voucher_no)->assertDontSee($partial->voucher_no)->assertDontSee($paid->voucher_no);
    $this->get('/fees?status=partial')->assertSee($partial->voucher_no)->assertDontSee($unpaid->voucher_no);
    $this->get('/fees?status=paid')->assertSee($paid->voucher_no)->assertDontSee($unpaid->voucher_no);
    $this->get('/fees?status=overdue')->assertSee($late->voucher_no)->assertDontSee($unpaid->voucher_no)->assertDontSee($paid->voucher_no);
    $this->get("/fees?class_id={$this->class2->id}")->assertSee($paid->voucher_no)->assertDontSee($unpaid->voucher_no);
    $this->get("/fees?fee_type_id={$this->exam->id}")->assertSee($partial->voucher_no)->assertSee($late->voucher_no)->assertDontSee($unpaid->voucher_no);
    $this->get('/fees?month=2026-11')->assertSee($partial->voucher_no)->assertDontSee($unpaid->voucher_no);
    $this->get('/fees?month=garbage')->assertOk()->assertSee($unpaid->voucher_no);   // ghalat month ignore
});

test('the list is paginated', function () {
    foreach (range(1, 17) as $i) {
        fe_voucher($this->ali, $this->tuition, ['voucher_no' => sprintf('ZZ-%02d', $i)]);
    }
    $this->get('/fees')->assertSee('17 voucher(s)')->assertSee('ZZ-17')->assertDontSee('ZZ-01');
    $this->get('/fees?page=2')->assertSee('ZZ-01');
});

// ---------------- bulk ----------------

function fe_bulk($t, array $over = []): array
{
    return array_merge([
        'school_class_id' => $t->class1->id, 'section_id' => '', 'fee_type_id' => $t->tuition->id,
        'for_month' => '2026-10', 'amount' => '3000', 'discount' => '0', 'due_date' => '2026-10-10',
    ], $over);
}

test('bulk creates one voucher per active student of the class', function () {
    fe_student($this->class1, $this->secA, 'LeftKid', 'left');

    $this->post('/fees/bulk', fe_bulk($this))->assertSessionHasNoErrors()->assertRedirect()->assertSessionHas('success');

    expect(Fee::count())->toBe(2)   // Ali + Sara, left student nahi, Zoya doosri class
        ->and(Fee::pluck('student_id')->sort()->values()->all())->toBe([$this->ali->id, $this->sara->id])
        ->and(Fee::pluck('voucher_no')->unique()->count())->toBe(2)
        ->and(Fee::first()->status)->toBe('unpaid');
});

test('bulk can be limited to one section', function () {
    $this->post('/fees/bulk', fe_bulk($this, ['section_id' => $this->secB->id]));
    expect(Fee::pluck('student_id')->all())->toBe([$this->sara->id]);
});

test('bulk skips students who already have that fee for that month and reports it', function () {
    fe_voucher($this->ali, $this->tuition, ['for_month' => '2026-10-01']);

    $response = $this->post('/fees/bulk', fe_bulk($this));

    expect(Fee::count())->toBe(2)   // Ali ka purana + Sara ka naya
        ->and(session('success'))->toContain('1 vouchers ban gaye')->toContain('1 students');
    // dobara chalane par kuch naya nahi banta
    $this->post('/fees/bulk', fe_bulk($this));
    expect(Fee::count())->toBe(2)->and(session('success'))->toContain('0 vouchers ban gaye');
});

test('bulk keeps other months and other fee types separate', function () {
    $this->post('/fees/bulk', fe_bulk($this));
    $this->post('/fees/bulk', fe_bulk($this, ['for_month' => '2026-11']));
    $this->post('/fees/bulk', fe_bulk($this, ['fee_type_id' => $this->exam->id, 'amount' => 500]));
    expect(Fee::count())->toBe(6);
});

test('bulk validation and an empty class', function () {
    $this->post('/fees/bulk', fe_bulk($this, ['for_month' => '']))->assertSessionHasErrors('for_month');
    $this->post('/fees/bulk', fe_bulk($this, ['amount' => '0']))->assertSessionHasErrors('amount');
    $this->post('/fees/bulk', fe_bulk($this, ['discount' => '99999']))->assertSessionHasErrors('discount');
    $this->post('/fees/bulk', fe_bulk($this, ['school_class_id' => 9999]))->assertSessionHasErrors('school_class_id');
    $this->post('/fees/bulk', fe_bulk($this, ['fee_type_id' => 9999]))->assertSessionHasErrors('fee_type_id');
    $this->post('/fees/bulk', fe_bulk($this, ['section_id' => SchoolClass::create(['name' => 'X'])->sections()->create(['name' => 'Q'])->id]))->assertSessionHasErrors('section_id');

    $empty = SchoolClass::create(['name' => 'Empty']);
    $this->post('/fees/bulk', fe_bulk($this, ['school_class_id' => $empty->id]))->assertSessionHas('error');
    expect(Fee::count())->toBe(0);
});

// ---------------- student profile ----------------

test('the student profile lists the vouchers with a link to create a new one', function () {
    $fee = fe_voucher($this->ali, $this->tuition, ['for_month' => '2026-10-01']);

    $this->get("/students/{$this->ali->id}")->assertOk()->assertSee('Fee vouchers')->assertSee($fee->voucher_no)
        ->assertSee("fees/create?student_id={$this->ali->id}", false);
    $this->get("/students/{$this->sara->id}")->assertSee('Abhi koi fee voucher nahi hai');
});

test('the sidebar shows Fees', function () {
    $this->get('/dashboard')->assertOk()->assertSee('Fees');
});
