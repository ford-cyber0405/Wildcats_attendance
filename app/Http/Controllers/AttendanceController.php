<?php

namespace App\Http\Controllers;

use App\Models\Sport;
use App\Models\Attendance;
use App\Models\ExcuseRequest;
use App\Models\ExcuseRequestPhoto;
use App\Models\Student;
use App\Models\TrainingSchedule;
use App\Models\User;
use App\Services\AttendanceCalculator;
use App\Services\AttendanceStore;
use App\Services\NotificationService;
use App\Services\AttendancePerformance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function welcome()
    {
        return view('landing');
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'identifier' => 'required|string|max:255',
            'password' => 'required|string',
        ]);

        $identifier = trim($validated['identifier']);
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', strtolower($identifier))
                ->whereIn('role', ['coach', 'super_admin'])
                ->first();
        } else {
            $user = User::where('student_id_number', $identifier)
                ->where('role', 'student')
                ->first();
        }

        if ($user && Hash::check($validated['password'], $user->password)) {
            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            return match (Auth::user()->role) {
                'student' => redirect('/student/dashboard'),
                'coach' => redirect('/coach/dashboard'),
                'super_admin' => redirect('/admin/dashboard'),
            };
        }

        return back()->withErrors(['identifier' => 'Login credentials are incorrect.'])->withInput();
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|string|max:50',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'year_level' => 'required|string|max:10',
            'course' => 'required|string|max:120',
            'contact' => 'required|string|max:30',
            'sport' => 'required|in:taekwondo,karatedo',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $studentId = trim($validated['student_id']);
        if (User::where('student_id_number', $studentId)->exists()) {
            return back()->withErrors(['student_id' => 'This Student ID has already been registered.'])->withInput();
        }

        $sport = Sport::whereRaw('LOWER(name) = ?', [strtolower($validated['sport'])])->first();
        if (!$sport) {
            return back()->withErrors(['sport' => 'The selected sport is not available.'])->withInput();
        }

        $user = DB::transaction(function () use ($validated, $studentId, $sport) {
            $user = User::create([
                'role' => 'student',
                'student_id_number' => $studentId,
                'password' => $validated['password'],
            ]);

            $user->student()->create([
                'sport_id' => $sport->id,
                'first_name' => $validated['first_name'],
                'middle_initial' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'course' => $validated['course'],
                'year_level' => $validated['year_level'],
            ]);

            return $user;
        });

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        return redirect('/student/dashboard')->with('success', 'Student account created successfully with your university-issued ID.');
    }

    public function logout()
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect('/');
    }

    public function notifications(NotificationService $notificationService)
    {
        $user = Auth::user();
        abort_unless($user, 401);

        return view('notifications.index', [
            'notifications' => $notificationService->unreadFor($user),
        ]);
    }

    public function markNotificationRead(int $notificationId, NotificationService $notificationService)
    {
        $user = Auth::user();
        abort_unless($user, 401);

        abort_unless($notificationService->markRead($user, $notificationId), 404);

        if (request()->expectsJson()) {
            return response()->json([
                'unread_count' => $notificationService->unreadCount($user),
            ]);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    public function showProfile()
    {
        $user = AttendanceStore::currentUser();
        if (!$user) {
            return redirect('/login');
        }

        return view('student.profile', [
            'user' => $user,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = AttendanceStore::currentUser();
        if (!$user) {
            return redirect('/login');
        }

        $rules = [
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'contact' => 'required|string|max:30',
            'avatar' => 'nullable|image|max:4096',
        ];

        if ($user['role'] === 'coach') {
            $rules['email'] = 'required|email|max:255';
            $rules['title'] = 'nullable|string|max:120';
            $rules['sports_handled'] = 'nullable|array';
            $rules['sports_handled.*'] = 'in:taekwondo,karatedo';
        } else {
            $rules['email'] = 'nullable|email|max:255';
            $rules['year_level'] = 'required|string|max:10';
            $rules['course'] = 'required|string|max:120';
        }

        $request->validate($rules);

        $updates = [
            'first_name' => $request->input('first_name'),
            'middle_name' => $request->input('middle_name') ?: '',
            'last_name' => $request->input('last_name'),
            'contact' => $request->input('contact'),
        ];

        if ($user['role'] === 'coach') {
            $updates['email'] = $request->input('email');
            $updates['title'] = $request->input('title') ?: '';
            $sportsHandled = $request->input('sports_handled', []);
            $updates['sports_handled'] = is_array($sportsHandled) ? array_values($sportsHandled) : [];
        } else {
            $updates['year_level'] = $request->input('year_level');
            $updates['course'] = $request->input('course');
        }

        if ($user['role'] === 'coach') {
            $updates['title'] = $request->input('title') ?: '';
            $sportsHandled = $request->input('sports_handled', []);
            $updates['sports_handled'] = is_array($sportsHandled) ? array_values($sportsHandled) : [];
        } else {
            $request->validate([
                'year_level' => 'required|string|max:10',
                'course' => 'required|string|max:120',
            ]);
            $updates['year_level'] = $request->input('year_level');
            $updates['course'] = $request->input('course');
        }

        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $updates['avatar'] = '/storage/' . $avatarPath;
        }

        $savedUser = AttendanceStore::updateUser($user['id'], $updates);
        if ($savedUser) {
            session(['user' => $savedUser]);
        }

        return back()->with('success', 'Your profile has been updated.');
    }

    protected function authorizeStudent()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'student' || !$user->student) {
            return null;
        }

        $student = $user->student;
        $userData = array_merge($user->toArray(), [
            'student_id' => $user->student_id_number,
            'first_name' => $student->first_name,
            'middle_name' => $student->middle_initial,
            'last_name' => $student->last_name,
            'year_level' => $student->year_level,
            'course' => $student->course,
            'sport' => strtolower($student->sport->name),
        ]);

        if (!empty($userData['student_id']) && !empty($userData['sport'])) {
            AttendanceStore::autoMarkAbsentIfNeeded($userData['student_id'], $userData['sport']);
        }

        return $userData;
    }

    public function studentDashboard(Request $request, AttendancePerformance $performance)
    {
        $user = $this->authorizeStudent();
        if (!$user) {
            return redirect('/login');
        }

        if ($request->session()->has('attendance_notice')) {
            return view('student.no-schedule', [
                'user' => $user,
                'message' => $request->session()->get('attendance_notice'),
            ]);
        }

        $year = $request->query('year', now()->year);
        $month = $request->query('month', now()->month);
        $year = (int) $year;
        $month = (int) $month;

        $student = Auth::user()->student;
        $calendar = $performance->calendar($student, $year, $month);
        $counts = $performance->counts($student, $year, $month);
        $today = now()->format('Y-m-d');
        $todayAttendance = Attendance::with('trainingSchedule')
            ->where('student_id', $student->id)
            ->whereHas('trainingSchedule', fn ($query) => $query->whereDate('training_date', $today))
            ->first()?->toArray();
        $startDate = AttendanceStore::systemStartDate();
        $isBeforeStartDate = strtotime($today) < strtotime($startDate);
        $isTodaySunday = date('w', strtotime($today)) === 0;
        $monthKey = sprintf('%d-%02d', $year, $month);
        $latestSchedule = AttendanceStore::getLatestSchedule($user['sport'], $monthKey);

        return view('student.dashboard', [
            'user' => $user,
            'calendar' => $calendar,
            'counts' => $counts,
            'year' => $year,
            'month' => $month,
            'monthName' => date('F', strtotime("{$year}-{$month}-01")),
            'latestSchedule' => $latestSchedule,
            'todayAttendance' => $todayAttendance,
            'isTodaySunday' => $isTodaySunday,
            'isBeforeStartDate' => $isBeforeStartDate,
            'attendanceStartDate' => $startDate,
        ]);
    }

    public function studentAttend(Request $request, AttendanceCalculator $attendanceCalculator)
    {
        $user = $this->authorizeStudent();
        if (!$user) {
            return redirect('/login');
        }

        $student = Auth::user()->student;
        $now = now();

        $schedule = TrainingSchedule::whereDate('training_date', $now->toDateString())
            ->where('sport_id', $student->sport_id)
            ->where('status', 'training')
            ->first();

        if (!$schedule) {
            $message = 'There is no active training schedule for your sport today. You can check the schedule page for upcoming training sessions.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                ], 422);
            }

            return redirect('/student/dashboard')->with('attendance_notice', $message);
        }

        $status = $attendanceCalculator->determineStatus($schedule, $now);
        if ($status === 'closed') {
            return response()->json([
                'message' => 'Attendance is closed for this training schedule.',
            ], 422);
        }

        if (Attendance::where('student_id', $student->id)
            ->where('training_schedule_id', $schedule->id)
            ->exists()) {
            return response()->json([
                'message' => 'Attendance has already been recorded for this training schedule.',
            ], 409);
        }

        try {
            $attendance = DB::transaction(fn () => Attendance::create([
                'student_id' => $student->id,
                'training_schedule_id' => $schedule->id,
                'time_in' => $now,
                'status' => $status,
            ]));
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'Attendance has already been recorded for this training schedule.',
            ], 409);
        }

        return response()->json([
            'status' => $attendance->status,
            'time_in' => $attendance->time_in->format('Y-m-d H:i:s'),
        ], 201);
    }

    public function showExcuse()
    {
        $user = $this->authorizeStudent();
        if (!$user) {
            return redirect('/login');
        }

        return view('student.excuse', [
            'user' => $user,
        ]);
    }

    public function sendExcuse(Request $request, NotificationService $notificationService)
    {
        $user = $this->authorizeStudent();
        if (!$user) {
            return redirect('/login');
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'reason' => 'required|string|max:400',
            'photos.*' => 'nullable|image|max:4096',
        ]);

        $student = Auth::user()->student;
        $schedule = TrainingSchedule::whereDate('training_date', $validated['date'])
            ->where('sport_id', $student->sport_id)
            ->where('status', 'training')
            ->first();

        if (!$schedule) {
            return back()->withErrors(['date' => 'No training schedule exists for your sport on that date.'])->withInput();
        }

        $excuse = DB::transaction(function () use ($student, $schedule, $validated, $request) {
            $excuse = ExcuseRequest::create([
                'student_id' => $student->id,
                'training_schedule_id' => $schedule->id,
                'reason' => $validated['reason'],
                'status' => 'pending',
            ]);

            foreach ($request->file('photos', []) as $photo) {
                $excuse->photos()->create([
                    'photo_path' => $photo->store('excuse-requests', 'public'),
                ]);
            }

            return $excuse;
        });
        $notificationService->notifyExcuseSubmitted($excuse->fresh('student'));

        return redirect('/student/dashboard')->with('success', 'Your excuse request was sent to the coach for review.');
    }

    public function showSpecialTraining()
    {
        $user = $this->authorizeStudent();
        if (!$user) {
            return redirect('/login');
        }

        return view('student.special_training', [
            'user' => $user,
        ]);
    }

    public function sendSpecialTraining(Request $request, NotificationService $notificationService)
    {
        $user = $this->authorizeStudent();
        if (!$user) {
            return redirect('/login');
        }

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:400',
        ]);

        $specialTrainingRequest = \App\Models\SpecialTrainingRequest::create([
            'student_id' => Auth::user()->student->id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);
        $notificationService->notifySpecialTrainingSubmitted($specialTrainingRequest->fresh('student'));

        return redirect('/student/special-training')->with('success', 'Your special training request was sent to your coach for approval.');
    }

    public function studentRoster(Request $request, AttendancePerformance $performance)
    {
        $user = $this->authorizeStudent();
        if (!$user) {
            return redirect('/login');
        }

        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);
        $currentMonth = \DateTime::createFromFormat('!Y-m', sprintf('%04d-%02d', $year, $month));
        if (!$currentMonth) {
            $currentMonth = new \DateTime();
            $year = (int) $currentMonth->format('Y');
            $month = (int) $currentMonth->format('m');
        }

        $students = $performance->students(Auth::user()->student->sport_id, $year, $month)->all();
        $search = $request->query('search', '');
        if ($search) {
            $students = array_filter($students, function ($student) use ($search) {
                return stripos($student['first_name'] . ' ' . $student['last_name'], $search) !== false;
            });
        }

        $prevMonth = (clone $currentMonth)->modify('-1 month');
        $nextMonth = (clone $currentMonth)->modify('+1 month');

        return view('student.roster', [
            'user' => $user,
            'students' => $students,
            'search' => $search,
            'year' => $year,
            'month' => $month,
            'currentMonthLabel' => $currentMonth->format('F Y'),
            'prevMonthYear' => (int) $prevMonth->format('Y'),
            'prevMonthValue' => (int) $prevMonth->format('m'),
            'nextMonthYear' => (int) $nextMonth->format('Y'),
            'nextMonthValue' => (int) $nextMonth->format('m'),
        ]);
    }

    public function studentSchedule(Request $request)
    {
        $user = $this->authorizeStudent();
        if (!$user) {
            return redirect('/login');
        }

        $monthKey = $request->query('month', now()->format('Y-m'));
        $selectedMonth = \DateTime::createFromFormat('!Y-m', $monthKey);
        if (!$selectedMonth) {
            $selectedMonth = new \DateTime();
            $monthKey = $selectedMonth->format('Y-m');
        }

        $schedules = AttendanceStore::getSchedules($user['sport'], $monthKey);
        $schedulePublishedAt = AttendanceStore::getScheduleUpdateTime($user['sport']);

        $availableMonths = [];
        $monthAnchor = (clone $selectedMonth)->modify('-1 month');
        for ($i = 0; $i < 12; $i++) {
            $availableMonths[$monthAnchor->format('Y-m')] = $monthAnchor->format('F Y');
            $monthAnchor = $monthAnchor->modify('+1 month');
        }

        $prevMonth = (clone $selectedMonth)->modify('-1 month')->format('Y-m');
        $nextMonth = (clone $selectedMonth)->modify('+1 month')->format('Y-m');

        return view('student.schedule', [
            'user' => $user,
            'schedules' => $schedules,
            'schedulePublishedAt' => $schedulePublishedAt,
            'monthKey' => $monthKey,
            'monthLabel' => $selectedMonth->format('F Y'),
            'availableMonths' => $availableMonths,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
        ]);
    }

}
