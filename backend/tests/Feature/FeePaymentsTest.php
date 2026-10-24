<?php

use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Support\DocumentNumber;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->accountant = User::factory()->create(['role' => 'accountant']);
    $this->actingAs($this->accountant);

    $class = SchoolClass::create(['name' => 'Class 1']);
    $this->student = Student::create([
        'admission_no' => 'P-1', 'first_name' => 'Ali', 'gender' => 'male', 'guardian_name' => 'Khan Sahib',
        'guardian_phone' => '1', 'school_class_id' => $class->id,
    ]);
    $this->type = FeeType::create(['name' => 'Tuition Fee', 'default_amount' => 1000]);
    $this->fee = fp_fee($this, 1000);
});

function fp_fee($t, float $amount, float $discount = 0, string $no = null): Fee
{
    static $n = 0;
    $n++;

    return Fee::create([
        'voucher_no' => $no ?? "FPV-{$n}", 'student_id' => $t->student->id, 'fee_type_id' => $t->type->id,
        'amount' => $amount, 'discount' => $discount, 'for_month' => '2026-10-01',
    ]);
}

function fp_pay($t, Fee $fee, array $over = [])
{
    return $t->post("/fees/{$fee->id}/payments", array_merge([
        'amount' => '400', 'payment_date' => today()->toDateString(), 'method' => 'cash',
    ], $over));
}

test('guests cannot receive payments', function () {
    auth()->logout();
    $this->post("/fees/{$this->fee->id}/payments", [])->assertRedirect('/login');
});

test('a partial payment creates a receipt and makes the voucher partial', function () {
    fp_pay($this, $this->fee, ['amount' => '400', 'note' => 'Pehli qist'])->assertSessionHasNoErrors()->assertRedirect("/fees/{$this->fee->id}");

    $p = FeePayment::first();
    $year = now()->format('Y');
    expect($p->receipt_no)->toBe("RCP-{$year}-000001")
        ->and((float) $p->amount)->toBe(400.0)
        ->and($p->method)->toBe('cash')
        ->and($p->note)->toBe('Pehli qist')
        ->and($p->received_by)->toBe($this->accountant->id)
        ->and($this->fee->refresh()->status)->toBe('partial')
        ->and($this->fee->balance)->toBe(600.0);
    expect(session('success'))->toContain("RCP-{$year}-000001");
});

test('paying the rest makes it paid and receipt numbers go up', function () {
    fp_pay($this, $this->fee, ['amount' => '400']);
    fp_pay($this, $this->fee, ['amount' => '600', 'method' => 'bank']);

    $nos = FeePayment::orderBy('id')->pluck('receipt_no')->all();
    $year = now()->format('Y');
    expect($nos)->toBe(["RCP-{$year}-000001", "RCP-{$year}-000002"])
        ->and($this->fee->refresh()->status)->toBe('paid')
        ->and($this->fee->balance)->toBe(0.0);
});

test('receipt numbers run across different vouchers', function () {
    $other = fp_fee($this, 500);
    fp_pay($this, $this->fee, ['amount' => '100']);
    fp_pay($this, $other, ['amount' => '100']);
    expect(FeePayment::orderBy('id')->pluck('receipt_no')->unique()->count())->toBe(2);
});

test('the discount is taken into account for the balance', function () {
    $fee = fp_fee($this, 1000, 200);   // net 800
    fp_pay($this, $fee, ['amount' => '800'])->assertSessionHasNoErrors();
    expect($fee->refresh()->status)->toBe('paid');

    $fee2 = fp_fee($this, 1000, 200);
    fp_pay($this, $fee2, ['amount' => '800.01'])->assertSessionHasErrors('amount');
});

test('paying more than the balance is refused', function () {
    fp_pay($this, $this->fee, ['amount' => '1000.01'])->assertSessionHasErrors('amount');
    fp_pay($this, $this->fee, ['amount' => '400']);
    fp_pay($this, $this->fee, ['amount' => '600.01'])->assertSessionHasErrors('amount');
    expect(FeePayment::count())->toBe(1);
});

test('an already paid voucher cannot take another payment', function () {
    fp_pay($this, $this->fee, ['amount' => '1000']);
    fp_pay($this, $this->fee, ['amount' => '1'])->assertSessionHasErrors('amount');
    expect(FeePayment::count())->toBe(1);
});

test('decimal amounts add up exactly without floating point trouble', function () {
    $fee = fp_fee($this, 100.10);
    fp_pay($this, $fee, ['amount' => '50.05'])->assertSessionHasNoErrors();
    fp_pay($this, $fee, ['amount' => '50.05'])->assertSessionHasNoErrors();
    expect($fee->refresh()->status)->toBe('paid');

    $fee2 = fp_fee($this, 0.30);
    fp_pay($this, $fee2, ['amount' => '0.10']);
    fp_pay($this, $fee2, ['amount' => '0.20'])->assertSessionHasNoErrors();
    expect($fee2->refresh()->status)->toBe('paid');
});

test('payment validation', function () {
    fp_pay($this, $this->fee, ['amount' => ''])->assertSessionHasErrors('amount');
    fp_pay($this, $this->fee, ['amount' => '0'])->assertSessionHasErrors('amount');
    fp_pay($this, $this->fee, ['amount' => '-5'])->assertSessionHasErrors('amount');
    fp_pay($this, $this->fee, ['amount' => 'abc'])->assertSessionHasErrors('amount');
    fp_pay($this, $this->fee, ['amount' => '10.123'])->assertSessionHasErrors('amount');
    fp_pay($this, $this->fee, ['payment_date' => ''])->assertSessionHasErrors('payment_date');
    fp_pay($this, $this->fee, ['payment_date' => today()->addDay()->toDateString()])->assertSessionHasErrors('payment_date');
    fp_pay($this, $this->fee, ['method' => 'cheque'])->assertSessionHasErrors('method');
    fp_pay($this, $this->fee, ['note' => str_repeat('x', 300)])->assertSessionHasErrors('note');
    expect(FeePayment::count())->toBe(0);
});

test('a past payment date is accepted', function () {
    fp_pay($this, $this->fee, ['payment_date' => today()->subDays(4)->toDateString()])->assertSessionHasNoErrors();
    expect(FeePayment::first()->payment_date->toDateString())->toBe(today()->subDays(4)->toDateString());
});

test('the voucher page shows the receive form while a balance remains and a message when paid', function () {
    $this->get("/fees/{$this->fee->id}")->assertSee('Receive &amp; generate receipt', false);
    fp_pay($this, $this->fee, ['amount' => '1000']);
    $this->get("/fees/{$this->fee->id}")->assertSee('poora ada ho chuka hai')->assertDontSee('Receive &amp; generate receipt', false);
});

test('the voucher page lists receipts with print and pdf links', function () {
    fp_pay($this, $this->fee);
    $p = FeePayment::first();

    $this->get("/fees/{$this->fee->id}")->assertSee($p->receipt_no)
        ->assertSee("fees/{$this->fee->id}/payments/{$p->id}/receipt", false)
        ->assertSee("fees/{$this->fee->id}/payments/{$p->id}/receipt/pdf", false);
});

// ---------------- cancel payment ----------------

test('cancelling a payment restores the balance and status', function () {
    // Payment cancel karna sirf Admin ka kaam hai (fees.delete)
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    fp_pay($this, $this->fee, ['amount' => '400']);
    fp_pay($this, $this->fee, ['amount' => '600']);
    expect($this->fee->refresh()->status)->toBe('paid');

    $second = FeePayment::orderByDesc('id')->first();
    $this->delete("/fees/{$this->fee->id}/payments/{$second->id}")->assertRedirect("/fees/{$this->fee->id}")->assertSessionHas('success');
    expect($this->fee->refresh()->status)->toBe('partial')->and($this->fee->balance)->toBe(600.0);

    $first = FeePayment::first();
    $this->delete("/fees/{$this->fee->id}/payments/{$first->id}");
    expect($this->fee->refresh()->status)->toBe('unpaid')->and($this->fee->balance)->toBe(1000.0);
});

test('a cancelled receipt number is never given to another receipt', function () {
    // Payment cancel karna sirf Admin ka kaam hai (fees.delete)
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    fp_pay($this, $this->fee, ['amount' => '100']);
    fp_pay($this, $this->fee, ['amount' => '100']);
    $last = FeePayment::orderByDesc('id')->first();
    $cancelledNo = $last->receipt_no;

    $this->delete("/fees/{$this->fee->id}/payments/{$last->id}");
    fp_pay($this, $this->fee, ['amount' => '100']);

    $year = now()->format('Y');
    $newest = FeePayment::orderByDesc('id')->first();
    expect($newest->receipt_no)->not->toBe($cancelledNo)->and($newest->receipt_no)->toBe("RCP-{$year}-000003");
});

test('a payment cannot be cancelled through another vouchers url', function () {
    // Payment cancel karna sirf Admin ka kaam hai (fees.delete)
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $other = fp_fee($this, 500);
    fp_pay($this, $this->fee, ['amount' => '100']);
    $p = FeePayment::first();

    $this->delete("/fees/{$other->id}/payments/{$p->id}")->assertNotFound();
    expect(FeePayment::count())->toBe(1);
});

// ---------------- document numbers ----------------

test('voucher numbers use the year and a six digit counter', function () {
    $year = now()->format('Y');
    expect(DocumentNumber::voucher())->toBe("VCH-{$year}-000001")
        ->and(DocumentNumber::voucher())->toBe("VCH-{$year}-000002")
        ->and(DocumentNumber::receipt())->toBe("RCP-{$year}-000001")
        ->and(DocumentNumber::voucher())->toBe("VCH-{$year}-000003");
});

test('the counter continues after existing numbers when it starts', function () {
    $year = now()->format('Y');
    fp_fee($this, 10, 0, "VCH-{$year}-000041");

    expect(DocumentNumber::voucher())->toBe("VCH-{$year}-000042");
});

test('a deleted voucher number is not reused', function () {
    $year = now()->format('Y');
    $a = DocumentNumber::voucher();
    Fee::create(['voucher_no' => $a, 'student_id' => $this->student->id, 'fee_type_id' => $this->type->id, 'amount' => 10]);
    Fee::where('voucher_no', $a)->delete();

    expect(DocumentNumber::voucher())->toBe("VCH-{$year}-000002");
});

test('retry runs again on a unique clash and gives up after the limit', function () {
    $calls = 0;
    $result = DocumentNumber::retry(function () use (&$calls) {
        $calls++;
        if ($calls < 3) {
            throw new UniqueConstraintViolationException('sqlite', 'insert', [], new Exception('dup'));
        }

        return 'ok';
    });
    expect($result)->toBe('ok')->and($calls)->toBe(3);

    // NOTE: normal closure (arrow fn variables ki copy banata hai, isliye ginti bahar nazar nahi aati)
    $tries = 0;
    $thrown = null;
    try {
        DocumentNumber::retry(function () use (&$tries) {
            $tries++;
            throw new UniqueConstraintViolationException('sqlite', 'insert', [], new Exception('dup'));
        }, 2);
    } catch (UniqueConstraintViolationException $e) {
        $thrown = $e;
    }
    expect($thrown)->not->toBeNull()->and($tries)->toBe(2);
});
