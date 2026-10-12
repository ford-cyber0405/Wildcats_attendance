@extends('layouts.app')

@section('title', 'Special Training | UM Attendance')

@section('content')
<div class="page-panel">
    <div class="large-box">
        <h2 class="page-title">Request Special Training</h2>
        <p class="hero-copy">Request a special training permit for a date range. Once approved, the system will mark your attendance as special training for each day in that range without affecting your normal team status.</p>

        <form method="POST" action="/student/special-training" enctype="multipart/form-data" class="form-panel" style="margin-top: 24px;">
            @csrf
            <div class="field-row">
                <div class="field-group">
                    <label for="start_date">Start Date</label>
                    <input id="start_date" type="date" name="start_date" value="{{ old('start_date', now()->toDateString()) }}" required class="form-control" />
                </div>
                <div class="field-group">
                    <label for="end_date">End Date</label>
                    <input id="end_date" type="date" name="end_date" value="{{ old('end_date', now()->toDateString()) }}" required class="form-control" />
                </div>
            </div>
            <div class="field-group">
                <label for="reason">Reason</label>
                <textarea id="reason" name="reason" placeholder="Explain why you need special training permission" required class="form-textarea">{{ old('reason') }}</textarea>
            </div>
            <div class="hero-actions" style="justify-content: flex-start; margin-top: 16px;">
                <button type="submit" class="button button-primary">Send Request</button>
                <a href="/student/dashboard" class="button button-secondary">Back to Dashboard</a>
            </div>
        </form>
    </div>
</div>
@endsection
