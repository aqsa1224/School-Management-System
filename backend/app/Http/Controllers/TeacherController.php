<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeacherRequest;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $status = $request->query('status', '');

        $teachers = Teacher::query()
            ->withCount('assignments')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->when(in_array($status, ['active', 'left'], true), fn ($q) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('teachers.index', compact('teachers', 'search', 'status'));
    }

    public function create()
    {
        return view('teachers.create');
    }

    public function store(TeacherRequest $request)
    {
        $data = $request->validated();

        $teacher = DB::transaction(function () use ($data, $request) {
            $teacher = Teacher::create($this->teacherFields($data));

            if ($request->boolean('create_login')) {
                $this->createLogin($teacher, $data['password']);
            }

            return $teacher;
        });

        return redirect()->route('teachers.show', $teacher)
            ->with('success', "Teacher \"{$teacher->name}\" added. Ab class/subject assign kar sakte hain.");
    }

    public function show(Teacher $teacher)
    {
        $teacher->load(['user', 'assignments.schoolClass', 'assignments.section', 'assignments.subject']);

        // Assign form ke liye: classes, un ke sections aur subjects
        $classes = SchoolClass::with(['sections', 'subjects'])->orderBy('sort_order')->orderBy('name')->get();

        return view('teachers.show', compact('teacher', 'classes'));
    }

    public function edit(Teacher $teacher)
    {
        return view('teachers.edit', compact('teacher'));
    }

    public function update(TeacherRequest $request, Teacher $teacher)
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request, $teacher) {
            $teacher->update($this->teacherFields($data));

            if ($teacher->user) {
                // Login account ka naam/email sath sath update, password sirf tab jab likha ho
                $teacher->user->name  = $teacher->name;
                $teacher->user->email = $teacher->email;
                if (! empty($data['password'])) {
                    $teacher->user->password = $data['password'];
                }
                $teacher->user->save();
            } elseif ($request->boolean('create_login')) {
                $this->createLogin($teacher, $data['password']);
            }
        });

        return redirect()->route('teachers.show', $teacher)->with('success', 'Teacher updated successfully.');
    }

    public function destroy(Teacher $teacher)
    {
        $name = $teacher->name;
        $user = $teacher->user;

        DB::transaction(function () use ($teacher, $user) {
            $teacher->delete(); // assignments khud delete ho jate hain

            // Is teacher ka login account bhi hata dein (admin/doosre ka account kabhi nahi)
            if ($user && $user->role === User::ROLE_TEACHER && $user->id !== auth()->id()) {
                $user->delete();
            }
        });

        return redirect()->route('teachers.index')->with('success', "Teacher \"{$name}\" deleted.");
    }

    private function teacherFields(array $data): array
    {
        return collect($data)->only([
            'name', 'email', 'phone', 'gender', 'qualification', 'joining_date', 'address', 'status',
        ])->all();
    }

    private function createLogin(Teacher $teacher, string $password): void
    {
        $user = User::create([
            'name'              => $teacher->name,
            'email'             => $teacher->email,
            'role'              => User::ROLE_TEACHER,
            'password'          => $password,
            'email_verified_at' => now(),
        ]);

        $teacher->update(['user_id' => $user->id]);
    }
}
