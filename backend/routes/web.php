<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\FeeController;
use App\Http\Controllers\FeeDocumentController;
use App\Http\Controllers\FeePaymentController;
use App\Http\Controllers\FeeTypeController;
use App\Http\Controllers\MarkController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TeacherAssignmentController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Login ke baad ke sab pages
|--------------------------------------------------------------------------
| auth                 = login zaroori
| EnsureUserIsActive   = band (deactivated) account foran logout
| can:xyz              = config/roles.php me us role ko permission "xyz" honi chahiye, warna 403
*/
Route::middleware(['auth', EnsureUserIsActive::class])->group(function () {

    // ---- Sab roles: dashboard + apna profile ----
    Route::get('/dashboard', function () {

        $students = Student::count();
        $teachers = Teacher::count();
        $subjects = Subject::count();

        return view('dashboard', compact('students', 'teachers', 'subjects'));

    })->middleware('verified')->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ---- Users (sirf admin) ----
    Route::middleware('can:users.manage')->group(function () {
        Route::resource('users', UserController::class)->except('show');
    });

    // ---- Settings (sirf admin): school ka naam, logo, address, currency, pass percentage ----
    Route::middleware('can:settings.manage')->group(function () {
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    });

    // ---- Academics (sirf admin): classes, sections, subjects, teachers, exams ----
    Route::middleware('can:academics.manage')->group(function () {

        // parameters(): URL ka {classes} model binding ke liye $schoolClass ban jata hai
        Route::resource('classes', SchoolClassController::class)
            ->parameters(['classes' => 'schoolClass']);

        // Sections sirf class ke andar manage hote hain (add / edit / delete)
        Route::resource('classes.sections', SectionController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['classes' => 'schoolClass'])
            ->scoped(); // section usi class ka hona chahiye warna 404

        Route::resource('subjects', SubjectController::class);

        Route::resource('teachers', TeacherController::class);
        Route::resource('teachers.assignments', TeacherAssignmentController::class)
            ->only(['store', 'destroy'])
            ->scoped(); // assignment usi teacher ki honi chahiye warna 404

        Route::resource('exams', ExamController::class)->except('show');
    });

    // ---- Students ---- (create wale group PEHLE, warna "create" ko {student} samajh liya jata hai)
    Route::middleware('can:students.manage')->group(function () {
        Route::resource('students', StudentController::class)->only(['create', 'store', 'edit', 'update']);
    });
    Route::middleware('can:students.delete')->group(function () {
        Route::resource('students', StudentController::class)->only('destroy');
    });
    Route::middleware('can:students.view')->group(function () {
        Route::resource('students', StudentController::class)->only(['index', 'show']);
    });

    // ---- Attendance (admin + teacher; teacher ko sirf apni classes) ----
    Route::middleware('can:attendance.manage')->group(function () {
        Route::get('attendances', [AttendanceController::class, 'index'])->name('attendances.index');
        Route::get('attendances/mark', [AttendanceController::class, 'mark'])->name('attendances.mark');
        Route::post('attendances', [AttendanceController::class, 'store'])->name('attendances.store');
    });

    // ---- Marks / Results (admin + teacher; teacher ko sirf apni classes/subjects) ----
    Route::middleware('can:marks.manage')->group(function () {
        Route::get('marks', [MarkController::class, 'index'])->name('marks.index');           // result sheet
        Route::get('marks/entry', [MarkController::class, 'entry'])->name('marks.entry');     // marks enter karna
        Route::post('marks', [MarkController::class, 'store'])->name('marks.store');
        Route::get('marks/card/{student}', [MarkController::class, 'card'])->name('marks.card'); // printable result card
    });

    // ---- Fee types (admin + accountant) ----
    Route::middleware('can:fee-types.manage')->group(function () {
        Route::resource('fee-types', FeeTypeController::class)->except('show')
            ->parameters(['fee-types' => 'feeType']);
    });

    // ---- Fees: banana/badalna/payment lena (admin + accountant) ---- bulk/create PEHLE aane chahiye
    Route::middleware('can:fees.manage')->group(function () {
        Route::get('fees/bulk', [FeeController::class, 'bulkCreate'])->name('fees.bulk.create');
        Route::post('fees/bulk', [FeeController::class, 'bulkStore'])->name('fees.bulk.store');
        Route::resource('fees', FeeController::class)->only(['create', 'store', 'edit', 'update']);
        Route::post('fees/{fee}/payments', [FeePaymentController::class, 'store'])->name('fees.payments.store');
    });

    // ---- Fees: delete aur payment cancel (sirf admin) ----
    Route::middleware('can:fees.delete')->group(function () {
        Route::resource('fees', FeeController::class)->only('destroy');
        Route::delete('fees/{fee}/payments/{payment}', [FeePaymentController::class, 'destroy'])
            ->scopeBindings()->name('fees.payments.destroy');
    });

    // ---- Fees: dekhna, print, PDF (admin + accountant) ----
    Route::middleware('can:fees.view')->group(function () {
        Route::resource('fees', FeeController::class)->only(['index', 'show']);

        Route::get('fees/{fee}/voucher', [FeeDocumentController::class, 'voucher'])->name('fees.voucher');
        Route::get('fees/{fee}/voucher/pdf', [FeeDocumentController::class, 'voucherPdf'])->name('fees.voucher.pdf');
        Route::get('fees/{fee}/payments/{payment}/receipt', [FeeDocumentController::class, 'receipt'])
            ->scopeBindings()->name('fees.receipt');
        Route::get('fees/{fee}/payments/{payment}/receipt/pdf', [FeeDocumentController::class, 'receiptPdf'])
            ->scopeBindings()->name('fees.receipt.pdf');
    });

    // ---- Reports ----
    // Home page: academic ya fees me se koi ek permission chahiye (check controller me hota hai)
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

    // Academic reports (admin + teacher; teacher ko sirf apni classes ke students)
    Route::middleware('can:reports.academic')->prefix('reports')->name('reports.')->group(function () {
        Route::get('students', [ReportController::class, 'students'])->name('students');
        Route::get('attendance', [ReportController::class, 'attendance'])->name('attendance');
        Route::get('results', [ReportController::class, 'results'])->name('results');
    });

    // Fee reports (admin + accountant)
    Route::middleware('can:reports.fees')->prefix('reports')->name('reports.')->group(function () {
        Route::get('fees', [ReportController::class, 'fees'])->name('fees');
        Route::get('daily-collection', [ReportController::class, 'daily'])->name('daily');
        Route::get('monthly-collection', [ReportController::class, 'monthly'])->name('monthly');
    });

    // Naye modules (Notices, Timetable ...) agle steps me yahin, sahi permission group me add honge.
});

require __DIR__.'/auth.php';
