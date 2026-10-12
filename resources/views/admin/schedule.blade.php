@extends('layouts.app')

@section('title', 'Admin Schedule | UM Attendance')

@section('content')
<div class="page-panel">
    <div class="large-box">
        <h2 class="page-title">{{ ucfirst($sport) }} Schedule Management</h2>
        <p class="hero-copy">View training schedule entries for {{ ucfirst($sport) }}.</p>
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-top: 24px;">
            <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <span style="font-weight:600; color: var(--um-gray);">Month:</span>
                <form method="GET" action="/admin/schedule/{{ $sport }}" style="margin:0; display:flex; gap:12px; align-items:center;">
                    <select name="month" id="month" onchange="this.form.submit()" class="form-control form-select" style="min-width: 240px;">
                        @foreach($availableMonths as $monthValue => $monthLabel)
                            <option value="{{ $monthValue }}" {{ $monthValue === $monthKey ? 'selected' : '' }}>{{ $monthLabel }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="/admin/schedule/{{ $sport }}?month={{ $prevMonth }}" class="button button-secondary">&laquo; Prev</a>
                <a href="/admin/schedule/{{ $sport }}?month={{ $nextMonth }}" class="button button-secondary">Next &raquo;</a>
            </div>
        </div>

        @php
            $selectedMonth = \DateTime::createFromFormat('!Y-m', $monthKey) ?: new \DateTime();
            $firstOfMonth = strtotime($selectedMonth->format('Y-m-01'));
            $daysInMonth = date('t', $firstOfMonth);
        @endphp

        <div class="month-grid" style="margin-top: 18px;">
            @for($day = 1; $day <= $daysInMonth; $day++)
                @php
                    $date = sprintf('%s-%02d', $selectedMonth->format('Y-m'), $day);
                    $item = $schedules[$date] ?? null;
                    $dayName = date('D', strtotime($date));
                    $isSunday = $dayName === 'Sun';
                @endphp
                <div class="calendar-cell month-cell {{ $item ? 'has-schedule' : ($isSunday ? 'rest-day' : 'empty-day') }}">
                    <div>
                        <h4>{{ $day }} <span style="color: var(--um-gray); font-size: 0.95rem;">{{ $dayName }}</span></h4>
                        <p>{{ date('M j, Y', strtotime($date)) }}</p>
                    </div>
                    <div style="margin-top: 12px; display:grid; gap:8px;">
                        @if($item)
                            <span class="status-chip info">{{ ucfirst(str_replace('_', ' ', $item['status'])) }}</span>
                            <span class="status-chip info">{{ $item['start_time'] }} - {{ $item['end_time'] }}</span>
                            <span class="status-chip info">Location: {{ $item['location'] ?? 'TBA' }}</span>
                        @elseif($isSunday)
                            <span class="status-chip info">No Training</span>
                        @else
                            <span class="status-chip info">No schedule</span>
                        @endif
                    </div>
                </div>
            @endfor
        </div>
    </div>
</div>
@endsection
