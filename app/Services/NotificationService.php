<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Coach;
use App\Models\ExcuseRequest;
use App\Models\Notification;
use App\Models\SpecialTrainingRequest;
use App\Models\Student;
use App\Models\TrainingSchedule;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    public function notifySchedule(TrainingSchedule $schedule): void
    {
        $date = $schedule->training_date->format('M j, Y');
        $time = $schedule->start_time->format('g:i A').' - '.$schedule->end_time->format('g:i A');
        $location = $schedule->location ?: 'TBA';
        $status = $schedule->status === 'training' ? 'Training' : 'No Training';

        $this->forUsers(
            Student::where('sport_id', $schedule->sport_id)->pluck('user_id'),
            'schedule_posted',
            'Training schedule updated',
            "New training schedule posted for {$date} - {$location}, {$time} ({$status}).",
            'training_schedule',
            $schedule->id,
            $schedule->sport_id,
        );
    }

    public function notifyAnnouncement(Announcement $announcement): void
    {
        $this->forUsers(
            Student::where('sport_id', $announcement->sport_id)->pluck('user_id'),
            'new_announcement',
            $announcement->title,
            $announcement->message,
            'announcement',
            $announcement->id,
            $announcement->sport_id,
        );
    }

    public function notifyAnnouncementForSport(?int $sportId, string $title, string $message, int $relatedId = 0): void
    {
        $recipients = $sportId === null
            ? Student::pluck('user_id')
            : Student::where('sport_id', $sportId)->pluck('user_id');

        $this->forUsers($recipients, 'new_announcement', $title, $message, 'announcement', $relatedId, $sportId);
    }

    public function notifyExcuseSubmitted(ExcuseRequest $request): void
    {
        $this->forSportReviewers($request->student->sport_id, 'new_excuse_request', 'New excuse request', 'A student submitted an excuse request for review.', 'excuse_request', $request->id);
    }

    public function notifySpecialTrainingSubmitted(SpecialTrainingRequest $request): void
    {
        $this->forSportReviewers($request->student->sport_id, 'new_special_training_request', 'New special training request', 'A student submitted a special training request for review.', 'special_training_request', $request->id);
    }

    public function notifyExcuseDecision(ExcuseRequest $request): void
    {
        $date = $request->trainingSchedule->training_date->format('M j, Y');
        $status = $request->status;
        $type = $status === 'approved' ? 'excuse_request_approved' : 'excuse_request_rejected';
        $title = 'Excuse request '.ucfirst($status);
        $message = "Your excuse request for {$date} was {$status}. Reason: {$request->reason}";

        $this->forUsers([$request->student->user_id], $type, $title, $message, 'excuse_request', $request->id, $request->student->sport_id);
    }

    public function notifySpecialTrainingDecision(SpecialTrainingRequest $request): void
    {
        $startDate = $request->start_date->format('M j, Y');
        $endDate = $request->end_date->format('M j, Y');
        $dateRange = $startDate === $endDate ? $startDate : $startDate.' - '.$endDate;
        $status = $request->status;
        $type = $status === 'approved' ? 'special_training_approved' : 'special_training_rejected';
        $title = 'Special training request '.ucfirst($status);
        $message = "Your special training request for {$dateRange} was {$status}. Reason: {$request->reason}";

        $this->forUsers([$request->student->user_id], $type, $title, $message, 'special_training_request', $request->id, $request->student->sport_id);
    }

    public function unreadFor(User $user): Collection
    {
        return Notification::where('user_id', $user->getAuthIdentifier())
            ->where('is_read', false)
            ->latest()
            ->get();
    }

    public function unreadCount(User $user): int
    {
        return Notification::where('user_id', $user->getAuthIdentifier())
            ->where('is_read', false)
            ->count();
    }

    public function markRead(User $user, int $notificationId): bool
    {
        return (bool) Notification::where('user_id', $user->getAuthIdentifier())
            ->whereKey($notificationId)
            ->update(['is_read' => true]);
    }

    private function forSportReviewers(int $sportId, string $type, string $title, string $message, string $relatedType, int $relatedId): void
    {
        $this->forUsers(
            Coach::where('sport_id', $sportId)->pluck('user_id')->merge(User::where('role', 'super_admin')->pluck('id')),
            $type,
            $title,
            $message,
            $relatedType,
            $relatedId,
            $sportId,
        );
    }

    private function forUsers(iterable $userIds, string $type, string $title, string $message, string $relatedType, int $relatedId, ?int $sportId = null): void
    {
        $rows = collect($userIds)->unique()->map(fn ($userId) => [
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'sport_id' => $sportId,
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        if ($rows) {
            Notification::insert($rows);
        }
    }
}