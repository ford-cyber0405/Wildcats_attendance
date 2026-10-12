<?php

namespace App\Http\Controllers;

use App\Models\ExcuseRequest;
use App\Models\SpecialTrainingRequest;
use App\Models\TrainingSchedule;
use Illuminate\Support\Facades\Auth;
use App\Services\AttendanceStore;
use App\Services\NotificationService;
use App\Services\AttendancePerformance;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class CoachController extends Controller
{
    protected function authorizeCoach()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'coach') {
            return null;
        }
        return AttendanceStore::currentUser();
    }

    public function dashboard(Request $request, AttendancePerformance $performance)
    {
        $user = $this->authorizeCoach();
        if (!$user) {
            return redirect('/login');
        }

        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);
        $students = $performance->students(Auth::user()->coach->sport_id, $year, $month)->all();
        $search = $request->query('search', '');
        if ($search) {
            $students = array_filter($students, function ($student) use ($search) {
                return stripos($student['first_name'] . ' ' . $student['last_name'], $search) !== false;
            });
        }

        $pendingExcuseCount = AttendanceStore::getPendingExcuseCount($user['sport']);
        $pendingSpecialTrainingCount = AttendanceStore::getPendingSpecialTrainingRequestCount($user['sport']);

        return view('coach.dashboard', [
            'user' => $user,
            'students' => $students,
            'year' => $year,
            'month' => $month,
            'search' => $search,
            'pendingExcuseCount' => $pendingExcuseCount,
            'pendingSpecialTrainingCount' => $pendingSpecialTrainingCount,
        ]);
    }

    public function schedule(Request $request, string $sport)
    {
        $user = $this->authorizeCoach();
        if (!$user || ($user['sport'] !== $sport && $user['sport'] !== 'all')) {
            return redirect('/coach/dashboard');
        }

        $monthKey = $request->query('month', now()->format('Y-m'));
        $selectedMonth = \DateTime::createFromFormat('!Y-m', $monthKey);
        if (!$selectedMonth) {
            $selectedMonth = new \DateTime();
            $monthKey = $selectedMonth->format('Y-m');
        }

        $sportModel = Auth::user()->coach->sport;
        $schedules = TrainingSchedule::where('sport_id', $sportModel->id)
            ->whereBetween('training_date', ["{$monthKey}-01", "{$monthKey}-".date('t', strtotime("{$monthKey}-01"))])
            ->orderBy('training_date')
            ->get()
            ->keyBy(fn ($schedule) => $schedule->training_date->format('Y-m-d'))
            ->map(fn ($schedule) => [
                'id' => $schedule->id,
                'start_time' => $schedule->start_time->format('H:i'),
                'end_time' => $schedule->end_time->format('H:i'),
                'location' => $schedule->location,
                'status' => $schedule->status,
            ])->all();

        $prevMonth = (clone $selectedMonth)->modify('-1 month')->format('Y-m');
        $nextMonth = (clone $selectedMonth)->modify('+1 month')->format('Y-m');

        return view('coach.schedule', [
            'user' => $user,
            'sport' => $sport,
            'schedules' => $schedules,
            'monthKey' => $monthKey,
            'monthLabel' => $selectedMonth->format('F Y'),
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'schedulePublishedAt' => null,
            'pendingPublish' => false,
        ]);
    }

    public function storeSchedule(Request $request, string $sport, NotificationService $notificationService)
    {
        $user = $this->authorizeCoach();
        if (!$user || ($user['sport'] !== $sport && $user['sport'] !== 'all')) {
            return redirect('/coach/dashboard');
        }

        $validated = $request->validate([
            'training_date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'location' => 'nullable|string|max:191',
            'status' => 'required|in:training,no_training',
            'month' => 'nullable|string',
        ]);

        $sportModel = Auth::user()->coach->sport;
        $schedule = TrainingSchedule::create([
            'sport_id' => $sportModel->id,
            'coach_id' => Auth::user()->coach->id,
            'training_date' => date('Y-m-d', strtotime($validated['training_date'])),
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'location' => $validated['location'] ?? null,
            'status' => $validated['status'],
        ]);
        $notificationService->notifySchedule($schedule->fresh());
        $monthKey = $request->input('month', now()->format('Y-m'));

        return redirect("/coach/schedule/{$sport}?month={$monthKey}")->with('success', 'Training schedule updated.');
    }

    public function updateSchedule(Request $request, string $sport, string $date, NotificationService $notificationService)
    {
        $user = $this->authorizeCoach();
        if (!$user || ($user['sport'] !== $sport && $user['sport'] !== 'all')) {
            return redirect('/coach/dashboard');
        }

        $validated = $request->validate([
            'training_date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'location' => 'nullable|string|max:191',
            'status' => 'required|in:training,no_training',
            'month' => 'nullable|string',
        ]);

        $date = date('Y-m-d', strtotime($date));
        $schedule = TrainingSchedule::whereDate('training_date', $date)
            ->where('coach_id', Auth::user()->coach->id)
            ->where('sport_id', Auth::user()->coach->sport_id)
            ->firstOrFail();
        $schedule->update([
            'training_date' => date('Y-m-d', strtotime($validated['training_date'])),
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'location' => $validated['location'] ?? null,
            'status' => $validated['status'],
        ]);
        $notificationService->notifySchedule($schedule->fresh());
        $monthKey = $request->input('month', now()->format('Y-m'));

        return redirect("/coach/schedule/{$sport}?month={$monthKey}")->with('success', 'Schedule entry updated.');
    }

    public function publishSchedule(Request $request, string $sport)
    {
        $user = $this->authorizeCoach();
        if (!$user || ($user['sport'] !== $sport && $user['sport'] !== 'all')) {
            return redirect('/coach/dashboard');
        }

        $request->validate(['month' => 'nullable|string']);
        AttendanceStore::publishSchedule($sport);
        $monthKey = $request->input('month', now()->format('Y-m'));

        return redirect("/coach/schedule/{$sport}?month={$monthKey}")->with('success', 'Schedule posted and students notified.');
    }

    public function deleteSchedule(Request $request, string $sport, string $date)
    {
        $user = $this->authorizeCoach();
        if (!$user || ($user['sport'] !== $sport && $user['sport'] !== 'all')) {
            return redirect('/coach/dashboard');
        }

        TrainingSchedule::where('training_date', $date)
            ->where('coach_id', Auth::user()->coach->id)
            ->where('sport_id', Auth::user()->coach->sport_id)
            ->firstOrFail()
            ->delete();
        return back()->with('success', 'Schedule entry deleted.');
    }

    public function announcements(Request $request)
    {
        $user = $this->authorizeCoach();
        if (!$user) {
            return redirect('/login');
        }

        $announcements = AttendanceStore::getAnnouncements($user['sport']);
        return view('coach.announcements', [
            'user' => $user,
            'announcements' => $announcements,
        ]);
    }

    public function updateAnnouncement(Request $request, string $announcementId)
    {
        $user = $this->authorizeCoach();
        if (!$user) {
            return redirect('/login');
        }

        $request->validate([
            'title' => 'required|string|max:120',
            'body' => 'required|string|max:500',
            'sport' => 'required|in:all,taekwondo,karatedo',
        ]);

        AttendanceStore::updateAnnouncement($announcementId, $request->input('sport'), $request->input('title'), $request->input('body'));
        return back()->with('success', 'Announcement updated successfully.');
    }

    public function deleteAnnouncement(string $announcementId)
    {
        $user = $this->authorizeCoach();
        if (!$user) {
            return redirect('/login');
        }

        AttendanceStore::deleteAnnouncement($announcementId);
        return back()->with('success', 'Announcement deleted successfully.');
    }

    public function createAnnouncement(Request $request, NotificationService $notificationService)
    {
        $user = $this->authorizeCoach();
        if (!$user) {
            return redirect('/login');
        }

        $request->validate([
            'title' => 'required|string|max:120',
            'body' => 'required|string|max:500',
        ]);

        AttendanceStore::addAnnouncement($user['sport'], $request->input('title'), $request->input('body'));
        $sportId = Auth::user()->coach->sport_id;
        $notificationService->notifyAnnouncementForSport($sportId, $request->input('title'), $request->input('body'));
        return back()->with('success', 'Announcement posted to your team.');
    }

    public function excuseList()
    {
        $user = $this->authorizeCoach();
        if (!$user) {
            return redirect('/login');
        }

        $excuses = ExcuseRequest::with(['student.user', 'trainingSchedule', 'photos'])
            ->whereHas('student', fn ($query) => $query->where('sport_id', Auth::user()->coach->sport_id))
            ->where('status', 'pending')
            ->latest()
            ->get();
        $excuses = $excuses->map(fn ($excuse) => [
            'id' => $excuse->id,
            'title' => 'Excuse Request',
            'student_id' => $excuse->student->user->student_id_number,
            'student_display' => trim($excuse->student->first_name.' '.$excuse->student->last_name).' · '.$excuse->student->user->student_id_number,
            'date' => $excuse->trainingSchedule->training_date->toDateString(),
            'sport' => strtolower($excuse->trainingSchedule->sport->name ?? ''),
            'reason' => $excuse->reason,
            'status' => $excuse->status,
            'attachments' => $excuse->photos->pluck('photo_path')->all(),
        ])->all();
        return view('coach.excuses', [
            'user' => $user,
            'excuses' => $excuses,
        ]);
    }

    public function showExcuseRequest(string $id)
    {
        $user = $this->authorizeCoach();
        if (!$user) {
            return redirect('/login');
        }

        $excuse = ExcuseRequest::with(['student.user', 'student.sport', 'trainingSchedule', 'photos'])
            ->whereKey($id)
            ->whereHas('student', fn ($query) => $query->where('sport_id', Auth::user()->coach->sport_id))
            ->first();
        if (!$excuse) {
            return redirect('/coach/excuses')->withErrors(['excuse' => 'Excuse request not found.']);
        }
        $excuse = array_merge($excuse->toArray(), [
            'title' => 'Excuse Request',
            'student_id' => $excuse->student->user->student_id_number,
            'student_display' => trim($excuse->student->first_name.' '.$excuse->student->last_name).' · '.$excuse->student->user->student_id_number,
            'date' => $excuse->trainingSchedule->training_date->toDateString(),
            'sport' => strtolower($excuse->student->sport->name),
            'attachments' => $excuse->photos->pluck('photo_path')->all(),
        ]);

        return view('coach.excuse_detail', [
            'user' => $user,
            'excuse' => $excuse,
        ]);
    }

    public function specialTrainingList()
    {
        $user = $this->authorizeCoach();
        if (!$user) {
            return redirect('/login');
        }

        $requests = SpecialTrainingRequest::with('student.user')
            ->whereHas('student', fn ($query) => $query->where('sport_id', Auth::user()->coach->sport_id))
            ->where('status', 'pending')
            ->latest()
            ->get();
        $requests = $requests->map(fn ($request) => [
            'id' => $request->id,
            'title' => 'Special Training Request',
            'student_id' => $request->student->user->student_id_number,
            'student_display' => trim($request->student->first_name.' '.$request->student->last_name).' · '.$request->student->user->student_id_number,
            'start_date' => $request->start_date->toDateString(),
            'end_date' => $request->end_date->toDateString(),
            'reason' => $request->reason,
            'status' => $request->status,
        ])->all();

        return view('coach.special_training', [
            'user' => $user,
            'requests' => $requests,
        ]);
    }

    public function showSpecialTrainingRequest(string $id)
    {
        $user = $this->authorizeCoach();
        if (!$user) {
            return redirect('/login');
        }

        $request = SpecialTrainingRequest::with(['student.user', 'student.sport'])
            ->whereKey($id)
            ->whereHas('student', fn ($query) => $query->where('sport_id', Auth::user()->coach->sport_id))
            ->first();
        if (!$request) {
            return redirect('/coach/special-training')->withErrors(['request' => 'Special training request not found.']);
        }
        $request = array_merge($request->toArray(), [
            'title' => 'Special Training Request',
            'student_id' => $request->student->user->student_id_number,
            'student_display' => trim($request->student->first_name.' '.$request->student->last_name).' · '.$request->student->user->student_id_number,
            'date_range' => $request->start_date->toFormattedDateString().($request->start_date->isSameDay($request->end_date) ? '' : ' – '.$request->end_date->toFormattedDateString()),
            'sport' => strtolower($request->student->sport->name),
        ]);

        return view('coach.special_training_detail', [
            'user' => $user,
            'request' => $request,
        ]);
    }

    public function approveExcuse($id, NotificationService $notificationService)
    {
        $user = $this->authorizeCoach();
        if (!$user) {
            return redirect('/login');
        }

        $excuse = ExcuseRequest::whereKey($id)
            ->whereHas('student', fn ($query) => $query->where('sport_id', Auth::user()->coach->sport_id))
            ->where('status', 'pending')->firstOrFail();
        $excuse->update(['status' => 'approved', 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);
        $notificationService->notifyExcuseDecision($excuse->fresh(['student', 'trainingSchedule']));
        return back()->with('success', 'Excuse request has been approved.');
    }

    public function rejectExcuse(string $id, NotificationService $notificationService)
    {
        $user = $this->authorizeCoach();
        if (!$user) return redirect('/login');
        $excuse = ExcuseRequest::whereKey($id)
            ->whereHas('student', fn ($query) => $query->where('sport_id', Auth::user()->coach->sport_id))
            ->where('status', 'pending')->firstOrFail();
        $excuse->update(['status' => 'rejected', 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);
        $notificationService->notifyExcuseDecision($excuse->fresh(['student', 'trainingSchedule']));
        return back()->with('success', 'Excuse request has been rejected.');
    }

    public function approveSpecialTraining($id, NotificationService $notificationService)
    {
        $user = $this->authorizeCoach();
        if (!$user) {
            return redirect('/login');
        }

        $request = SpecialTrainingRequest::whereKey($id)
            ->whereHas('student', fn ($query) => $query->where('sport_id', Auth::user()->coach->sport_id))
            ->where('status', 'pending')->firstOrFail();
        $request->update(['status' => 'approved', 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);
        $notificationService->notifySpecialTrainingDecision($request->fresh('student'));
        return back()->with('success', 'Special training request has been approved.');
    }

    public function rejectSpecialTraining(string $id, NotificationService $notificationService)
    {
        $user = $this->authorizeCoach();
        if (!$user) return redirect('/login');
        $request = SpecialTrainingRequest::whereKey($id)
            ->whereHas('student', fn ($query) => $query->where('sport_id', Auth::user()->coach->sport_id))
            ->where('status', 'pending')->firstOrFail();
        $request->update(['status' => 'rejected', 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);
        $notificationService->notifySpecialTrainingDecision($request->fresh('student'));
        return back()->with('success', 'Special training request has been rejected.');
    }

    public function athleteProfile(Request $request, string $studentId, AttendancePerformance $performance)
    {
        $user = $this->authorizeCoach();
        if (!$user) {
            return redirect('/login');
        }

        $studentModel = \App\Models\Student::with(['user', 'sport'])
            ->whereHas('user', fn ($query) => $query->where('student_id_number', $studentId))
            ->where('sport_id', Auth::user()->coach->sport_id)
            ->first();
        if (!$studentModel) {
            return redirect('/coach/dashboard');
        }

        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);
        $calendar = $performance->calendar($studentModel, $year, $month);
        $counts = $performance->counts($studentModel, $year, $month);
        $student = $performance->summary($studentModel, $year, $month);

        return view('coach.athlete', [
            'user' => $user,
            'student' => $student,
            'calendar' => $calendar,
            'counts' => $counts,
            'year' => $year,
            'month' => $month,
        ]);
    }
}
