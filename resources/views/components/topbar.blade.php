@php
    use App\Services\NotificationService;

    $user = Auth::user();
    $pageHeading = trim($__env->yieldContent('pageHeading') ?: str_replace(' | UM Attendance', '', $__env->yieldContent('title')));
    $avatarLetters = strtoupper(substr(($user->first_name ?? 'U'), 0, 1).substr(($user->last_name ?? ''), 0, 1));
    $notificationCount = $user ? app(NotificationService::class)->unreadCount($user) : 0;
@endphp

<header class="topbar">
    <div class="topbar-left">
        <button class="mobile-nav-toggle topbar-icon" type="button" data-mobile-toggle aria-label="Open sidebar"><i class="bi bi-list"></i></button>
        <a href="/notifications" class="icon-btn topbar-icon notification-btn" aria-label="Notifications" data-notification-link>
            <i class="bi bi-bell"></i>
            @if($notificationCount > 0)
                <span class="icon-badge" data-notification-badge aria-label="{{ $notificationCount }} unread notifications">{{ $notificationCount }}</span>
            @endif
        </a>
    </div>

    <a href="{{ route('profile.show') }}" class="profile-chip" aria-label="Open profile">
        <span class="avatar">{{ $avatarLetters }}</span>
        <div class="topbar-profile-copy">
            <div>{{ trim(($user->first_name ?? 'UM').' '.($user->last_name ?? '')) }}</div>
            <small>{{ isset($user->role) ? ucfirst($user->role) : 'Member' }}</small>
        </div>
    </a>
</header>
