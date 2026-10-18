<?php

namespace App\Http\Controllers;

use App\Models\Coach;
use App\Models\ExcuseRequest;
use App\Models\Sport;
use App\Models\SpecialTrainingRequest;
use App\Models\TrainingSchedule;
use App\Models\User;
use App\Services\AttendanceStore;
use App\Services\NotificationService;
use App\Services\AttendancePerformance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    protected function authorizeAdmin()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'super_admin') {
            return null;
        }
        return $user;
    }

    public function dashboard(Request $request, AttendancePerformance $performance)
    {
        $user = $this->authorizeAdmin();
        if (!$user) {
            return redirect('/login');
        }

        $period = $request->query('period');
        $periodDate = is_string($period)
            ? \DateTime::createFromFormat('!Y-m', $period)
            : false;
        $year = $periodDate ? (int) $periodDate->format('Y') : (int) now()->year;
        $month = $periodDate ? (int) $periodDate->format('m') : (int) now()->month;
        $taekwondoId = Sport::whereRaw('LOWER(name) = ?', ['taekwondo'])->value('id');
        $karatedoId = Sport::whereRaw('LOWER(name) = ?', ['karatedo'])->value('id');
        $taekwondoStudents = $performance->students($taekwondoId, $year, $month)->all();
        $karatedoStudents = $performance->students($karatedoId, $year, $month)->all();
        $search = $request->query('search', '');
        if ($search) {
            $taekwondoStudents = array_filter($taekwondoStudents, function ($student) use ($search) {
                return stripos($student['first_name'] . ' ' . $student['last_name'], $search) !== false;
            });
            $karatedoStudents = array_filter($karatedoStudents, function ($student) use ($search) {
                return stripos($student['first_name'] . ' ' . $student['last_name'], $search) !== false;
            });
        }

        return view('admin.dashboard', [
            'user' => $user,
            'taekwondoStudents' => $taekwondoStudents,
            'karatedoStudents' => $karatedoStudents,
            'year' => $year,
            'month' => $month,
            'search' => $search,
        ]);
    }

    public function showCreateCoach()
    {
        $user = $this->authorizeAdmin();
        if (!$user) {
            return redirect('/login');
        }

        return view('admin.create_coach', ['user' => $user]);
    }

    public function coaches()
    {
        $user = $this->authorizeAdmin();
        if (!$user) {
            return redirect('/login');
        }

        $coaches = Coach::with(['user', 'sport'])
            ->get()
            ->map(fn (Coach $coach) => [
                'id' => $coach->user_id,
                'coach_id' => $coach->id,
                'first_name' => $coach->first_name,
                'last_name' => $coach->last_name,
                'email' => $coach->user->email,
                'sport' => strtolower($coach->sport->name),
            ])
            ->all();
        return view('admin.coaches', [
            'user' => $user,
            'coaches' => $coaches,
        ]);
    }

    public function editCoach(string $id)
    {
        $user = $this->authorizeAdmin();
        if (!$user) {
            return redirect('/login');
        }

        $coachModel = Coach::with(['user', 'sport'])->where('user_id', $id)->first();
        if (!$coachModel || $coachModel->user->role !== 'coach') {
            return redirect('/admin/coaches');
        }

        $coach = [
            'id' => $coachModel->user_id,
            'first_name' => $coachModel->first_name,
            'middle_name' => $coachModel->middle_initial,
            'last_name' => $coachModel->last_name,
            'email' => $coachModel->user->email,
            'sport' => strtolower($coachModel->sport->name),
            'contact' => null,
        ];

        return view('admin.edit_coach', ['user' => $user, 'coach' => $coach]);
    }

    public function updateCoach(Request $request, string $id)
    {
        $user = $this->authorizeAdmin();
        if (!$user) {
            return redirect('/login');
        }

        $request->validate([
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:10',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:191|unique:users,email,'.$id,
            'sport' => 'required|in:taekwondo,karatedo',
            'contact' => 'nullable|string|max:40',
            'password' => 'nullable|string|min:6',
        ]);

        $userModel = User::with('coach')->whereKey($id)->where('role', 'coach')->firstOrFail();
        $sport = Sport::whereRaw('LOWER(name) = ?', [$request->input('sport')])->firstOrFail();

        DB::transaction(function () use ($request, $userModel, $sport) {
            $userModel->update(['email' => strtolower($request->input('email'))]);
            if ($request->filled('password')) {
                $userModel->update(['password' => $request->input('password')]);
            }
            $userModel->coach->update([
                'sport_id' => $sport->id,
                'first_name' => $request->input('first_name'),
                'middle_initial' => $request->input('middle_name') ?: null,
                'last_name' => $request->input('last_name'),
            ]);
        });
        return redirect('/admin/coaches')->with('success', 'Coach updated successfully.');
    }

    public function deleteCoach(string $id)
    {
        $user = $this->authorizeAdmin();
        if (!$user) {
            return redirect('/login');
        }

        User::whereKey($id)->where('role', 'coach')->firstOrFail()->delete();
        return redirect('/admin/coaches')->with('success', 'Coach account deleted.');
    }

    public function createCoach(Request $request)
    {
        $user = $this->authorizeAdmin();
        if (!$user) {
            return redirect('/login');
        }

        $validated = $request->validate([
            'email' => 'required|email|max:191|unique:users,email',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'sport' => 'required|in:taekwondo,karatedo',
            'password' => 'required|string|min:6',
            'avatar' => 'nullable|image|max:4096',
        ]);

        $sport = Sport::whereRaw('LOWER(name) = ?', [$validated['sport']])->first();
        if (!$sport) {
            return back()->withErrors(['sport' => 'The selected sport is not available.'])->withInput();
        }

        DB::transaction(function () use ($validated, $sport) {
            $user = User::create([
                'role' => 'coach',
                'email' => strtolower($validated['email']),
                'password' => $validated['password'],
            ]);

            $user->coach()->create([
                'sport_id' => $sport->id,
                'first_name' => $validated['first_name'],
                'middle_initial' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
            ]);
        });

        return back()->with('success', 'Coach account created successfully.');
    }

    public function editStudent(string $studentId)
    {
        $user = $this->authorizeAdmin();
        if (!$user) {
            return redirect('/login');
        }

        $studentModel = \App\Models\Student::with(['user', 'sport'])
            ->whereHas('user', fn ($query) => $query->where('student_id_number', $studentId))
            ->first();
        if (!$studentModel) {
            return redirect('/admin/dashboard');
        }

        $student = [
            'student_id' => $studentModel->user->student_id_number,
            'first_name' => $studentModel->first_name,
            'last_name' => $studentModel->last_name,
            'sport' => strtolower($studentModel->sport->name),
        ];

        return view('admin.edit_student', ['user' => $user, 'student' => $student]);
    }

    public function updateStudentAttendance(Request $request, string $studentId)
    {
        $user = $this->authorizeAdmin();
        if (!$user) {
            return redirect('/login');
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'status' => 'required|in:present,late,absent,excused',
            'time' => 'nullable|date_format:H:i',
        ]);

        $student = \App\Models\Student::with('user')
            ->whereHas('user', fn ($query) => $query->where('student_id_number', $studentId))
            ->firstOrFail();
        $schedule = TrainingSchedule::whereDate('training_date', $validated['date'])
            ->where('sport_id', $student->sport_id)
            ->first();

        if (!$schedule) {
            return back()->withErrors(['date' => 'No training schedule exists for this student on the selected date.'])->withInput();
        }

        Attendance::updateOrCreate(
            [
                'student_id' => $student->id,
                'training_schedule_id' => $schedule->id,
            ],
            [
                'time_in' => $validated['time'] ? $validated['date'].' '.$validated['time'].':00' : null,
                'status' => $validated['status'],
                'corrected_by' => Auth::id(),
            ],
        );
        return back()->with('success', 'Student attendance updated.');
    }

    public function schedule(Request $request, string $sport)
    {
        $user = $this->authorizeAdmin();
        if (!$user) {
            return redirect('/login');
        }

        $monthKey = $request->query('month', now()->format('Y-m'));
        $selectedMonth = \DateTime::createFromFormat('!Y-m', $monthKey);
        if (!$selectedMonth) {
            $selectedMonth = new \DateTime();
            $monthKey = $selectedMonth->format('Y-m');
        }

        $sportModel = Sport::whereRaw('LOWER(name) = ?', [strtolower($sport)])->firstOrFail();
        $schedules = TrainingSchedule::where('sport_id', $sportModel->id)
            ->whereBetween('training_date', ["{$monthKey}-01", "{$monthKey}-".date('t', strtotime("{$monthKey}-01"))])
            ->orderBy('training_date')
            ->get()
            ->keyBy(fn ($schedule) => $schedule->training_date->toDateString())
            ->map(fn ($schedule) => [
                'id' => $schedule->id,
                'start_time' => $schedule->start_time->format('H:i'),
                'end_time' => $schedule->end_time->format('H:i'),
                'location' => $schedule->location,
                'status' => $schedule->status,
            ])->all();

        $availableMonths = [];
        $monthAnchor = (clone $selectedMonth)->modify('-1 month');
        for ($i = 0; $i < 12; $i++) {
            $availableMonths[$monthAnchor->format('Y-m')] = $monthAnchor->format('F Y');
            $monthAnchor = $monthAnchor->modify('+1 month');
        }

        $prevMonth = (clone $selectedMonth)->modify('-1 month')->format('Y-m');
        $nextMonth = (clone $selectedMonth)->modify('+1 month')->format('Y-m');

        return view('admin.schedule', [
            'user' => $user,
            'sport' => $sport,
            'schedules' => $schedules,
            'monthKey' => $monthKey,
            'monthLabel' => $selectedMonth->format('F Y'),
            'availableMonths' => $availableMonths,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'schedulePublishedAt' => null,
            'pendingPublish' => false,
        ]);
    }

    public function storeSchedule(Request $request, string $sport)
    {
        $user = $this->authorizeAdmin();
        if (!$user) {
            return redirect('/login');
        }

        $request->validate([
            'date' => 'required|date',
            'venue' => 'required|string|max:120',
            'time' => 'required|string|max:30',
        ]);

        // Prevent double-booking across sports
        $conflict = AttendanceStore::findScheduleConflict($sport, $request->input('date'), $request->input('time'), $request->input('venue'));
        if ($conflict) {
            return back()->withErrors(['date' => "Schedule conflict: {$conflict['sport']} already has a booking at {$request->input('venue')} on {$request->input('date')}."])->withInput();
        }

        AttendanceStore::setSchedule($sport, $request->input('date'), $request->input('time'), $request->input('venue'));
        return back()->with('success', 'Training schedule updated.');
    }

    public function updateSchedule(Request $request, string $sport, string $date)
    {
        $user = $this->authorizeAdmin();
        if (!$user) {
            return redirect('/login');
        }

        $request->validate([
            'date' => 'required|date',
            'venue' => 'required|string|max:120',
            'time' => 'required|string|max:30',
        ]);

        // Prevent double-booking when updating
        $conflict = AttendanceStore::findScheduleConflict($sport, $request->input('date'), $request->input('time'), $request->input('venue'));
        if ($conflict) {
            return back()->withErrors(['date' => "Schedule conflict: {$conflict['sport']} already has a booking at {$request->input('venue')} on {$request->input('date')}."])->withInput();
        }

        AttendanceStore::updateSchedule($sport, $date, $request->input('date'), $request->input('time'), $request->input('venue'));
        return back()->with('success', 'Schedule entry updated.');
    }

    public function deleteSchedule(Request $request, string $sport, string $date)
    {
        $user = $this->authorizeAdmin();
        if (!$user) {
            return redirect('/login');
        }

        AttendanceStore::deleteSchedule($sport, $date);
        return back()->with('success', 'Schedule entry deleted.');
    }

    public function excuses()
    {
        $user = $this->authorizeAdmin();
        if (!$user) {
            return redirect('/login');
        }

        $excuses = ExcuseRequest::with(['student.user', 'student.sport', 'trainingSchedule', 'photos'])
            ->where('status', 'pending')->latest()->get()
            ->map(fn ($excuse) => [
                'id' => $excuse->id,
                'title' => 'Excuse Request',
                'student_id' => $excuse->student->user->student_id_number,
                'student_display' => trim($excuse->student->first_name.' '.$excuse->student->last_name).' · '.$excuse->student->user->student_id_number,
                'date' => $excuse->trainingSchedule->training_date->toDateString(),
                'sport' => strtolower($excuse->student->sport->name),
                'reason' => $excuse->reason,
                'attachments' => $excuse->photos->pluck('photo_path')->all(),
            ])->all();
        return view('admin.excuses', [
            'user' => $user,
            'excuses' => $excuses,
        ]);
    }

    public function showExcuseRequest(string $id)
    {
        $user = $this->authorizeAdmin();
        if (!$user) {
            return redirect('/login');
        }

        $excuse = ExcuseRequest::with(['student.user', 'student.sport', 'trainingSchedule', 'photos'])->find($id);
        if (!$excuse) {
            return redirect('/admin/excuses')->withErrors(['excuse' => 'Excuse request not found.']);
        }

        $excuse = array_merge($excuse->toArray(), [
            'title' => 'Excuse Request',
            'student_id' => $excuse->student->user->student_id_number,
            'student_display' => trim($excuse->student->first_name.' '.$excuse->student->last_name).' · '.$excuse->student->user->student_id_number,
            'date' => $excuse->trainingSchedule->training_date->toDateString(),
            'sport' => strtolower($excuse->student->sport->name),
            'attachments' => $excuse->photos->pluck('photo_path')->all(),
        ]);

        return view('admin.excuse_detail', [
            'user' => $user,
            'excuse' => $excuse,
        ]);
    }

    public function specialTrainingList()
    {
        $user = $this->authorizeAdmin();
        if (!$user) {
            return redirect('/login');
        }

        $requests = SpecialTrainingRequest::with('student.user')
            ->where('status', 'pending')->latest()->get()
            ->map(fn ($request) => [
                'id' => $request->id,
                'title' => 'Special Training Request',
                'student_id' => $request->student->user->student_id_number,
                'student_display' => trim($request->student->first_name.' '.$request->student->last_name).' · '.$request->student->user->student_id_number,
                'start_date' => $request->start_date->toDateString(),
                'end_date' => $request->end_date->toDateString(),
                'reason' => $request->reason,
            ])->all();

        return view('admin.special_training', [
            'user' => $user,
            'requests' => $requests,
        ]);
    }

    public function showSpecialTrainingRequest(string $id)
    {
        $user = $this->authorizeAdmin();
        if (!$user) {
            return redirect('/login');
        }

        $request = SpecialTrainingRequest::with(['student.user', 'student.sport'])->find($id);
        if (!$request) {
            return redirect('/admin/special-training')->withErrors(['request' => 'Special training request not found.']);
        }

        $request = array_merge($request->toArray(), [
            'title' => 'Special Training Request',
            'student_id' => $request->student->user->student_id_number,
            'student_display' => trim($request->student->first_name.' '.$request->student->last_name).' · '.$request->student->user->student_id_number,
            'date_range' => $request->start_date->toFormattedDateString().($request->start_date->isSameDay($request->end_date) ? '' : ' – '.$request->end_date->toFormattedDateString()),
            'sport' => strtolower($request->student->sport->name),
        ]);

        return view('admin.special_training_detail', [
            'user' => $user,
            'request' => $request,
        ]);
    }

    public function approveExcuse(string $excuseId, NotificationService $notificationService)
    {
        $user = $this->authorizeAdmin();
        if (!$user) {
            return redirect('/login');
        }

        $excuse = ExcuseRequest::whereKey($excuseId)->where('status', 'pending')->firstOrFail();
        $excuse->update(['status' => 'approved', 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);
        $notificationService->notifyExcuseDecision($excuse->fresh('student'));
        return back()->with('success', 'Excuse approved successfully.');
    }

    public function rejectExcuse(string $excuseId, NotificationService $notificationService)
    {
        $user = $this->authorizeAdmin();
        if (!$user) return redirect('/login');
        $excuse = ExcuseRequest::whereKey($excuseId)->where('status', 'pending')->firstOrFail();
        $excuse->update(['status' => 'rejected', 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);
        $notificationService->notifyExcuseDecision($excuse->fresh('student'));
        return back()->with('success', 'Excuse rejected successfully.');
    }

    public function approveSpecialTraining(string $requestId, NotificationService $notificationService)
    {
        $user = $this->authorizeAdmin();
        if (!$user) {
            return redirect('/login');
        }

        $request = SpecialTrainingRequest::whereKey($requestId)->where('status', 'pending')->firstOrFail();
        $request->update(['status' => 'approved', 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);
        $notificationService->notifySpecialTrainingDecision($request->fresh('student'));
        return back()->with('success', 'Special training request approved successfully.');
    }

    public function rejectSpecialTraining(string $requestId, NotificationService $notificationService)
    {
        $user = $this->authorizeAdmin();
        if (!$user) return redirect('/login');
        $request = SpecialTrainingRequest::whereKey($requestId)->where('status', 'pending')->firstOrFail();
        $request->update(['status' => 'rejected', 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);
        $notificationService->notifySpecialTrainingDecision($request->fresh('student'));
        return back()->with('success', 'Special training request rejected successfully.');
    }

    public function announcements()
    {
        $user = $this->authorizeAdmin();
        if (!$user) {
            return redirect('/login');
        }

        $announcements = AttendanceStore::getAnnouncements('all');
        return view('admin.announcements', [
            'user' => $user,
            'announcements' => $announcements,
        ]);
    }

    public function createAnnouncement(Request $request, NotificationService $notificationService)
    {
        $user = $this->authorizeAdmin();
        if (!$user) {
            return redirect('/login');
        }

        $request->validate([
            'title' => 'required|string|max:120',
            'body' => 'required|string|max:500',
            'sport' => 'required|in:taekwondo,karatedo,all',
        ]);

        AttendanceStore::addAnnouncement($request->input('sport'), $request->input('title'), $request->input('body'));
        $sport = $request->input('sport');
        $sportId = $sport === 'all'
            ? null
            : Sport::whereRaw('LOWER(name) = ?', [$sport])->value('id');
        $notificationService->notifyAnnouncementForSport($sportId, $request->input('title'), $request->input('body'));
        return back()->with('success', 'Announcement posted successfully.');
    }

    public function systemSettings(Request $request, AttendancePerformance $performance)
    {
        $user = $this->authorizeAdmin();
        if (!$user) {
            return redirect('/login');
        }

        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);
        $students = $performance->students(null, $year, $month)->all();

        $search = $request->query('search', '');
        if ($search) {
            $students = array_filter($students, function ($student) use ($search) {
                return stripos($student['first_name'] . ' ' . $student['last_name'], $search) !== false
                    || stripos($student['student_id'] ?? '', $search) !== false;
            });
        }

        $attendanceStartDate = AttendanceStore::systemStartDate();
        $attendanceStartDateLabel = \Illuminate\Support\Carbon::parse($attendanceStartDate, config('app.timezone'))->format('F j, Y');

        return view('admin.system_settings', [
            'user' => $user,
            'students' => $students,
            'search' => $search,
            'year' => $year,
            'month' => $month,
            'attendanceStartDate' => $attendanceStartDate,
            'attendanceStartDateLabel' => $attendanceStartDateLabel,
        ]);
    }

    public function deleteStudent(string $studentId)
    {
        $user = $this->authorizeAdmin();
        if (!$user) {
            return redirect('/login');
        }

        $studentUser = User::where('role', 'student')
            ->where('student_id_number', $studentId)
            ->firstOrFail();

        DB::transaction(function () use ($studentUser) {
            $studentUser->student()->delete();
            $studentUser->delete();
        });

        AttendanceStore::deleteUser($studentId);
        return back()->with('success', 'Student record deleted successfully.');
    }
}
