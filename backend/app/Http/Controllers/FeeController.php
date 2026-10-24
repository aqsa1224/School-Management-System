<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkFeeRequest;
use App\Http\Requests\FeeRequest;
use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\DocumentNumber;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeeController extends Controller
{
    public function index(Request $request)
    {
        $search  = trim((string) $request->query('q', ''));
        $status  = (string) $request->query('status', '');
        $classId = (string) $request->query('class_id', '');
        $typeId  = (string) $request->query('fee_type_id', '');
        $month   = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month', '')) ? $request->query('month') : '';

        $filtered = Fee::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('voucher_no', 'like', "%{$search}%")
                        ->orWhereHas('student', function ($s) use ($search) {
                            foreach (preg_split('/\s+/', $search) as $term) {
                                $s->where(fn ($x) => $x
                                    ->where('first_name', 'like', "%{$term}%")
                                    ->orWhere('last_name', 'like', "%{$term}%")
                                    ->orWhere('admission_no', 'like', "%{$term}%")
                                    ->orWhere('guardian_phone', 'like', "%{$term}%"));
                            }
                        });
                });
            })
            ->when(in_array($status, ['unpaid', 'partial', 'paid'], true), fn ($q) => $q->where('status', $status))
            // Overdue = abhi poori ada nahi hui aur due date guzar chuki
            ->when($status === 'overdue', fn ($q) => $q->where('status', '!=', 'paid')->whereDate('due_date', '<', today()))
            ->when($classId !== '', fn ($q) => $q->whereHas('student', fn ($s) => $s->where('school_class_id', $classId)))
            ->when($typeId !== '', fn ($q) => $q->where('fee_type_id', $typeId))
            ->when($month !== '', fn ($q) => $q->whereDate('for_month', $month . '-01'));

        $totals = [
            'billed'    => (float) (clone $filtered)->sum(DB::raw('fees.amount - fees.discount')),
            'collected' => (float) FeePayment::whereIn('fee_id', (clone $filtered)->select('fees.id'))->sum('amount'),
        ];
        $totals['balance'] = max(0, $totals['billed'] - $totals['collected']);

        $fees = (clone $filtered)
            ->with(['student.schoolClass', 'student.section', 'feeType'])
            ->withSum('payments', 'amount')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('fees.index', [
            'fees'     => $fees,
            'totals'   => $totals,
            'classes'  => SchoolClass::orderBy('sort_order')->orderBy('name')->get(),
            'feeTypes' => FeeType::orderBy('name')->get(),
            'search' => $search, 'status' => $status, 'classId' => $classId, 'typeId' => $typeId, 'month' => $month,
        ]);
    }

    public function create(Request $request)
    {
        return view('fees.create', $this->formData() + [
            'selectedStudent' => (int) $request->query('student_id'),
        ]);
    }

    public function store(FeeRequest $request)
    {
        $data = $this->prepare($request);

        $fee = DocumentNumber::retry(fn () => Fee::create($data + [
            'student_id' => $request->integer('student_id'),
            'voucher_no' => DocumentNumber::voucher(),
            'status'     => Fee::STATUS_UNPAID,
        ]));

        return redirect()->route('fees.show', $fee)
            ->with('success', "Voucher {$fee->voucher_no} ban gaya.");
    }

    public function show(Fee $fee)
    {
        $fee->load(['student.schoolClass', 'student.section', 'feeType', 'payments.receivedBy']);

        return view('fees.show', compact('fee'));
    }

    public function edit(Fee $fee)
    {
        $fee->load(['student.schoolClass', 'student.section']);

        return view('fees.edit', [
            'fee'      => $fee,
            'feeTypes' => FeeType::orderBy('name')->get(),
        ]);
    }

    public function update(FeeRequest $request, Fee $fee)
    {
        $fee->update($this->prepare($request));
        $fee->refreshStatus(); // amount/discount badalne se status (paid/partial) badal sakta hai

        return redirect()->route('fees.show', $fee)->with('success', 'Voucher updated successfully.');
    }

    public function destroy(Fee $fee)
    {
        // Payments (receipts) wale voucher ko delete karne se hisaab kharab hota hai
        if ($fee->payments()->exists()) {
            return redirect()->route('fees.show', $fee)
                ->with('error', 'Is voucher par payments (receipts) maujood hain, isliye delete nahi ho sakta. Pehle payments cancel karein.');
        }

        $no = $fee->voucher_no;
        $fee->delete();

        return redirect()->route('fees.index')->with('success', "Voucher {$no} deleted.");
    }

    // ---------------- bulk: poori class ke vouchers ----------------

    public function bulkCreate()
    {
        return view('fees.bulk', [
            'classes'  => SchoolClass::with('sections')->orderBy('sort_order')->orderBy('name')->get(),
            'feeTypes' => FeeType::orderBy('name')->get(),
        ]);
    }

    public function bulkStore(BulkFeeRequest $request)
    {
        $students = Student::query()
            ->where('status', 'active')
            ->where('school_class_id', $request->integer('school_class_id'))
            ->when($request->filled('section_id'), fn ($q) => $q->where('section_id', $request->integer('section_id')))
            ->orderBy('first_name')->get(['id']);

        if ($students->isEmpty()) {
            return back()->withInput()->with('error', 'Is class/section me koi active student nahi hai.');
        }

        $month  = $request->input('for_month') . '-01';
        $typeId = $request->integer('fee_type_id');

        // Jin students ka is month ka yehi fee type pehle se bana hua hai unhein chhor do
        $already = Fee::where('fee_type_id', $typeId)->whereDate('for_month', $month)
            ->whereIn('student_id', $students->pluck('id'))->pluck('student_id')->all();

        $created = 0;
        DB::transaction(function () use ($students, $already, $request, $month, $typeId, &$created) {
            foreach ($students as $student) {
                if (in_array($student->id, $already, true)) {
                    continue;
                }

                DocumentNumber::retry(fn () => Fee::create([
                    'voucher_no'  => DocumentNumber::voucher(),
                    'student_id'  => $student->id,
                    'fee_type_id' => $typeId,
                    'for_month'   => $month,
                    'amount'      => $request->input('amount'),
                    'discount'    => $request->input('discount') ?: 0,
                    'due_date'    => $request->input('due_date') ?: null,
                    'status'      => Fee::STATUS_UNPAID,
                ]));
                $created++;
            }
        });

        $skipped = count($already);
        $msg = "{$created} vouchers ban gaye" . ($skipped ? ", {$skipped} students ke voucher pehle se maujood the (chhor diye)" : '') . '.';

        return redirect()->route('fees.index', [
            'class_id'    => $request->input('school_class_id'),
            'fee_type_id' => $typeId,
            'month'       => $request->input('for_month'),
        ])->with('success', $msg);
    }

    // ---------------- helpers ----------------

    private function formData(): array
    {
        return [
            'classes'  => SchoolClass::orderBy('sort_order')->orderBy('name')->get(),
            'students' => Student::where('status', 'active')->orderBy('first_name')->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'admission_no', 'school_class_id']),
            'feeTypes' => FeeType::orderBy('name')->get(),
        ];
    }

    private function prepare(FeeRequest $request): array
    {
        return [
            'fee_type_id' => $request->integer('fee_type_id'),
            'for_month'   => $request->filled('for_month') ? Carbon::createFromFormat('Y-m-d', $request->input('for_month') . '-01')->toDateString() : null,
            'amount'      => $request->input('amount'),
            'discount'    => $request->input('discount') ?: 0,
            'due_date'    => $request->input('due_date') ?: null,
        ];
    }
}
