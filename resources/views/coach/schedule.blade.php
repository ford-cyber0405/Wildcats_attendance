@extends('layouts.app')

@section('title', 'Coach Schedule | UM Attendance')

@section('content')
<style>
    .schedule-month-picker {
        min-width: 240px;
        color-scheme: dark;
    }

    .schedule-month-picker:focus {
        border-color: var(--um-gold);
        box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
        outline: none;
    }

    .schedule-month-picker::-webkit-calendar-picker-indicator {
        filter: invert(78%) sepia(53%) saturate(542%) hue-rotate(3deg) brightness(91%);
        cursor: pointer;
    }
</style>
<div class="page-panel">
    <div class="large-box">
        <h2 class="page-title">{{ ucfirst($sport) }} Schedule</h2>
        <p class="hero-copy">Add or update training dates, times, and venues for your sport with a clear calendar-style layout.</p>

        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-top: 24px;">
            <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <span style="font-weight:600; color: var(--um-gray);">Month:</span>
                <form method="GET" action="/coach/schedule/{{ $sport }}" style="margin:0; display:flex; gap:12px; align-items:center;">
                    <input type="month" name="month" id="month" value="{{ $monthKey }}" onchange="this.form.submit()" class="form-control schedule-month-picker" aria-label="Select schedule month" />
                </form>
            </div>
            <div class="month-navigation">
                <a href="/coach/schedule/{{ $sport }}?month={{ $prevMonth }}" class="button button-secondary">&laquo; Prev</a>
                <a href="/coach/schedule/{{ $sport }}?month={{ $nextMonth }}" class="button button-secondary">Next &raquo;</a>
            </div>
        </div>

        @if(!empty($schedulePublishedAt))
            <div class="page-notice page-notice-success" style="margin-top: 18px;">
                <span class="page-notice-dot"></span>
                <div>
                    <strong>Last posted:</strong>
                    <p>{{ date('F j, Y g:i A', strtotime($schedulePublishedAt)) }}</p>
                </div>
            </div>
        @endif

        @php
            $selectedMonth = \DateTime::createFromFormat('!Y-m', $monthKey) ?: new \DateTime();
            $firstOfMonth = strtotime($selectedMonth->format('Y-m-01'));
            $daysInMonth = date('t', $firstOfMonth);
        @endphp

        <div class="month-grid" style="margin-top: 26px;">
            @for($day = 1; $day <= $daysInMonth; $day++)
                @php
                    $date = sprintf('%s-%02d', $selectedMonth->format('Y-m'), $day);
                    $item = $schedules[$date] ?? null;
                    $dayName = date('D', strtotime($date));
                    $isSunday = $dayName === 'Sun';
                @endphp
                <details class="calendar-cell month-cell {{ $item ? 'has-schedule' : ($isSunday ? 'rest-day' : 'empty-day') }}" data-schedule-card>
                    <summary style="cursor: pointer; list-style: none;">
                        <h4>{{ $day }} <span style="color: var(--um-gray); font-size: 0.95rem;">{{ $dayName }}</span></h4>
                        <p>{{ date('M j, Y', strtotime($date)) }}</p>
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
                    </summary>
                    <form method="POST" action="{{ $item ? "/coach/schedule/{$sport}/{$date}/update" : "/coach/schedule/{$sport}" }}" class="form-panel" style="margin-top: 12px;" data-schedule-form>
                        @csrf
                        <input type="hidden" name="month" value="{{ $monthKey }}" />
                        <input type="hidden" name="training_date" value="{{ $date }}" />
                        <div class="field-group">
                            <label for="status_{{ $date }}">Status</label>
                            <select id="status_{{ $date }}" name="status" required class="form-control form-select">
                                <option value="training" {{ ($item['status'] ?? 'training') === 'training' ? 'selected' : '' }}>Training</option>
                                <option value="no_training" {{ ($item['status'] ?? '') === 'no_training' ? 'selected' : '' }}>No Training</option>
                            </select>
                        </div>
                        <div class="field-row">
                            <div class="field-group">
                                <label for="start_time_{{ $date }}">Start time</label>
                                <input id="start_time_{{ $date }}" type="time" name="start_time" value="{{ $item['start_time'] ?? '09:00' }}" required class="form-control" />
                            </div>
                            <div class="field-group">
                                <label for="end_time_{{ $date }}">End time</label>
                                <input id="end_time_{{ $date }}" type="time" name="end_time" value="{{ $item['end_time'] ?? '10:00' }}" required class="form-control" />
                            </div>
                        </div>
                       <div class="field-group">
    <label for="location_{{ $date }}">Location</label>
    <select id="location_{{ $date }}" name="location" class="form-control">
        <option value="" disabled {{ empty($item['location'] ?? '') ? 'selected' : '' }}>Select location</option>
        @foreach (['TKD Room', 'Judo Room', 'FEA Gym', 'Fitness Gym', 'Big Gym', 'Oval'] as $location)
            <option value="{{ $location }}" {{ ($item['location'] ?? '') === $location ? 'selected' : '' }}>
                {{ $location }}
            </option>
        @endforeach
    </select>
</div>
<div class="action-row" style="margin-top:8px; display:flex; gap:8px; justify-content:flex-end;">
    <button type="button" class="button button-secondary" data-cancel-schedule>Cancel</button>
    <button type="submit" class="button button-primary">Save</button>
</div>
                    </form>
                    @if($item)
                        <form method="POST" action="/coach/schedule/{{ $sport }}/{{ $date }}/delete" class="confirm-delete" data-name="{{ ucfirst($sport) }} schedule for {{ date('F j, Y', strtotime($date)) }}" style="margin-top: 8px;">
                            @csrf
                            <button type="submit" class="button button-danger">Delete</button>
                        </form>
                    @endif
                </details>
            @endfor
            </div>

            <script>
                document.querySelectorAll('[data-schedule-card]').forEach((card) => {
                    card.addEventListener('toggle', () => {
                        if (card.open) {
                            document.querySelectorAll('[data-schedule-card][open]').forEach((otherCard) => {
                                if (otherCard !== card) otherCard.open = false;
                            });
                        }
                    });
                    card.querySelector('[data-cancel-schedule]').addEventListener('click', () => {
                        card.open = false;
                    });
                });
            </script>

            <form method="POST" action="/coach/schedule/{{ $sport }}/publish" style="margin-top: 20px; display:flex; gap: 12px; flex-wrap:wrap; align-items:center;">
                @csrf
                <input type="hidden" name="month" value="{{ $monthKey }}" />
                <button class="button button-primary" type="submit" {{ $pendingPublish ? '' : 'disabled' }}>Post Schedule to Students</button>
                @if(!$pendingPublish)
                    <span style="color: var(--um-gray);">No new schedule updates pending post.</span>
                @endif
            </form>
    </div>
</div>
@endsection
