@extends('layouts.app')

@section('title', 'Notifications | UM Attendance')

@section('content')
<div class="page-panel">
    <div class="large-box">
        <h2 class="page-title">Notifications</h2>
        <p class="hero-copy">Updates relevant to your account and sport.</p>

        <div class="table-grid" style="margin-top: 24px;" data-notification-list>
            @forelse($notifications as $notification)
                <div class="form-card" style="padding: 22px;" data-notification-item>
                    <h3 style="margin-top: 0;">{{ $notification->title }}</h3>
                    <p>{{ $notification->message }}</p>
                    <small>{{ $notification->created_at->format('M j, Y g:i A') }}</small>
                    <form method="POST" action="/notifications/{{ $notification->id }}/read" style="margin-top: 14px;" data-acknowledge-notification>
                        @csrf
                        <button type="submit" class="button button-secondary">Acknowledge</button>
                    </form>
                </div>
            @empty
                <div class="form-card" data-notification-empty><p>No unread notifications.</p></div>
            @endforelse
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var list = document.querySelector('[data-notification-list]');

        function updateBadge(count) {
            var badge = document.querySelector('[data-notification-badge]');
            if (count < 1) {
                if (badge) badge.remove();
                return;
            }

            if (!badge) return;
            badge.textContent = count;
            badge.setAttribute('aria-label', count + ' unread notifications');
        }

        document.querySelectorAll('[data-acknowledge-notification]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();

                var button = form.querySelector('button[type="submit"]');
                button.disabled = true;

                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).then(function (response) {
                    if (!response.ok) throw new Error('Unable to acknowledge notification');
                    return response.json();
                }).then(function (data) {
                    form.closest('[data-notification-item]').remove();
                    updateBadge(data.unread_count);

                    if (!list.querySelector('[data-notification-item]')) {
                        list.insertAdjacentHTML('beforeend', '<div class="form-card" data-notification-empty><p>No unread notifications.</p></div>');
                    }
                }).catch(function () {
                    form.submit();
                });
            });
        });
    });
</script>
@endsection