@extends('layouts.app')

@section('title', 'Student Dashboard | UM Attendance')

@section('content')
<style>
    .student-attendance-action {
        display: flex;
        justify-content: center;
        width: 100%;
        margin: 28px 0 32px;
        padding: 0 12px;
    }

    .student-attendance-form {
        display: flex;
        justify-content: center;
        width: 100%;
        margin: 0;
    }

    .student-attendance-button {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 10px;
        width: min(220px, 70vw);
        aspect-ratio: 1;
        height: auto;
        min-height: 0;
        padding: 24px;
        border: 2px solid rgba(255, 238, 174, 0.9);
        border-radius: 50%;
        background: linear-gradient(145deg, #ffe58a 0%, #f4c542 48%, #d4af37 100%);
        color: #35000f;
        box-shadow: 0 14px 34px rgba(212, 175, 55, 0.28), inset 0 2px 0 rgba(255, 255, 255, 0.45);
        font-size: 1.02rem;
        font-weight: 700;
        line-height: 1.3;
        text-align: center;
        animation: none;
        transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
    }

    .student-attendance-button i {
        font-size: 2rem;
        line-height: 1;
    }

    .student-attendance-button:hover:not(:disabled) {
        transform: translateY(-3px);
        box-shadow: 0 19px 40px rgba(212, 175, 55, 0.36), inset 0 2px 0 rgba(255, 255, 255, 0.5);
        filter: brightness(1.03);
    }

    .student-attendance-button:active:not(:disabled) {
        transform: translateY(0) scale(0.98);
    }

    .student-attendance-button:focus-visible {
        outline: 3px solid #fff1b8;
        outline-offset: 4px;
    }

    .student-attendance-button:disabled {
        cursor: not-allowed;
        filter: saturate(0.65);
    }

    @media (prefers-reduced-motion: reduce) {
        .student-attendance-button {
            transition: none;
        }
    }
</style>
<div class="page-panel">
    <div class="grid-two">
        <div class="large-box">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
                <div>
                    <h2 class="page-title">Welcome, {{ $user['first_name'] }} </h2>
                    <p class="hero-copy">Your sport: {{ ucfirst($user['sport']) }} · Student ID: {{ $user['student_id'] }}</p>
                </div>
                <div style="display: grid; gap: 10px; text-align: right;">
                    <span class="badge">Year {{ $user['year_level'] }}</span>
                    <span class="badge">{{ $user['course'] }}</span>
                </div>
            </div>

            <div class="status-key">
                <span><span class="dot green"></span> Present</span>
                <span><span class="dot yellow"></span> Late</span>
                <span><span class="dot red"></span> Absent</span>
                <span><span class="dot blue"></span> Excuse</span>
                <span><span class="dot teal"></span> Special Training</span>
                <span><span class="dot gray"></span> No Training</span>
            </div>

            <div class="student-attendance-action">
                <form method="POST" action="/student/attend" class="student-attendance-form">
                    @csrf
                    <button type="submit" class="button button-present student-attendance-button" {{ $isTodaySunday || $isBeforeStartDate || (!empty($todayAttendance) && $todayAttendance['status'] === 'no_training') ? 'disabled' : '' }}>
                        <i class="bi bi-calendar-check" aria-hidden="true"></i>
                        <span>Mark Attendance</span>
                    </button>
                </form>
            </div>

            @php
                $prevMonth = $month - 1;
                $prevYear = $year;
                if ($prevMonth < 1) {
                    $prevMonth = 12;
                    $prevYear--;
                }
                $nextMonth = $month + 1;
                $nextYear = $year;
                if ($nextMonth > 12) {
                    $nextMonth = 1;
                    $nextYear++;
                }
                $isCurrentMonth = $month === now()->month && $year === now()->year;
            @endphp

            <div class="dashboard-status-row" style="margin-top: 28px;">
                <span class="badge">Present: {{ $counts['present'] }}</span>
                <span class="badge">Late: {{ $counts['late'] }}</span>
                <span class="badge">Absent: {{ $counts['absent'] }}</span>
                <span class="badge">Excuse: {{ $counts['excuse'] }}</span>
                <span class="badge">Special Training: {{ $counts['special_training'] }}</span>
            </div>

            <div class="dashboard-nav-row" style="margin-top: 14px;">
                <span class="badge">Month: {{ $monthName }} {{ $year }}</span>

                <div class="dashboard-nav-controls">
                    <a href="/student/dashboard?year={{ $prevYear }}&month={{ $prevMonth }}" class="button button-secondary" style="padding: 10px 14px; font-size: 0.9rem;"><i class="bi bi-chevron-left"></i> Previous</a>
                    @if (!$isCurrentMonth)
                        <a href="/student/dashboard" class="button button-primary" style="padding: 10px 14px; font-size: 0.9rem;">Today</a>
                    @endif
                    <a href="/student/dashboard?year={{ $nextYear }}&month={{ $nextMonth }}" class="button button-secondary" style="padding: 10px 14px; font-size: 0.9rem;">Next <i class="bi bi-chevron-right"></i></a>
                </div>
            </div>

            <div class="calendar-grid">
                @foreach($calendar as $date => $entry)
                    @php
                        $day = date('j', strtotime($date));
                        $status = $entry['status'] ?? 'none';
                        $time = $entry['time'] ?? '--';
                        if (is_string($time) && preg_match('/^\d{1,2}:\d{2}$/', $time)) {
                            $time = date('g:i a', strtotime($time));
                        }
                    @endphp
                    <div class="calendar-cell">
                        <div>
                            <h4>{{ $day }} <span style="color: var(--um-gray); font-size: 0.95rem;">{{ date('D', strtotime($date)) }}</span></h4>
                            <p>{{ $date }}</p>
                        </div>
                        @if($status !== 'none')
                            <span class="status-chip {{ $status }}">{{ ucfirst($status) }} · {{ $time }}</span>
                        @else
                            <span class="status-chip info">No attendance</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

    </div>
</div>
@endsection
