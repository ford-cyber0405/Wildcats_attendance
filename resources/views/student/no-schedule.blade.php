@extends('layouts.app')

@section('title', 'No Training Scheduled | UM Attendance')

@section('content')
<style>
    .no-schedule-page {
        display: grid;
        min-height: min(68vh, 720px);
        place-items: center;
        padding: clamp(16px, 4vw, 40px);
    }

    .no-schedule-card {
        width: min(100%, 680px);
        padding: clamp(28px, 6vw, 52px);
        border: 1px solid rgba(212, 175, 55, 0.38);
        border-radius: 28px;
        background:
            radial-gradient(circle at 50% 0, rgba(212, 175, 55, 0.1), transparent 42%),
            linear-gradient(145deg, rgba(60, 10, 22, 0.96), rgba(35, 6, 14, 0.98));
        box-shadow: 0 24px 68px rgba(0, 0, 0, 0.3), 0 0 38px rgba(212, 175, 55, 0.08);
        text-align: center;
    }

    .no-schedule-icon {
        display: grid;
        width: 84px;
        height: 84px;
        margin: 0 auto 24px;
        place-items: center;
        border: 1px solid rgba(244, 197, 66, 0.42);
        border-radius: 24px;
        background: rgba(212, 175, 55, 0.1);
        color: var(--um-bright-gold);
        font-size: 2.5rem;
        box-shadow: inset 0 0 24px rgba(212, 175, 55, 0.06);
    }

    .no-schedule-label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 18px;
        padding: 8px 12px;
        border: 1px solid rgba(212, 175, 55, 0.28);
        border-radius: 999px;
        background: rgba(212, 175, 55, 0.08);
        color: var(--um-bright-gold);
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.1em;
    }

    .no-schedule-label::before {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--um-bright-gold);
        box-shadow: 0 0 10px rgba(244, 197, 66, 0.7);
        content: '';
    }

    .no-schedule-title {
        margin: 0;
        color: var(--um-white);
        font-size: clamp(1.65rem, 4vw, 2.25rem);
        line-height: 1.2;
    }

    .no-schedule-message {
        max-width: 500px;
        margin: 16px auto 0;
        color: var(--um-gray);
        font-size: 1rem;
        line-height: 1.75;
    }

    .no-schedule-actions {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 30px;
    }

    .no-schedule-actions .button {
        min-width: 170px;
        min-height: 48px;
        padding: 12px 18px;
        border-radius: 14px;
    }

    .no-schedule-dashboard-link {
        border: 1px solid rgba(255, 238, 174, 0.78);
        background: linear-gradient(135deg, #ffe58a, #d4af37);
        color: #35000f;
        font-weight: 700;
        box-shadow: 0 10px 24px rgba(212, 175, 55, 0.2);
    }

    .no-schedule-dashboard-link:hover {
        border-color: #fff1b8;
        background: linear-gradient(135deg, #ffedaa, #f4c542);
        color: #35000f;
        transform: translateY(-2px);
    }

    .no-schedule-dashboard-link:focus-visible,
    .no-schedule-actions .button-secondary:focus-visible {
        outline: 3px solid rgba(244, 197, 66, 0.8);
        outline-offset: 3px;
    }

    @media (max-width: 480px) {
        .no-schedule-card {
            padding: 30px 20px;
            border-radius: 22px;
        }

        .no-schedule-actions {
            flex-direction: column;
        }

        .no-schedule-actions .button {
            width: 100%;
        }
    }
</style>

<div class="page-panel no-schedule-page">
    <section class="no-schedule-card" aria-labelledby="no-schedule-title">
        <div class="no-schedule-icon" aria-hidden="true">
            <i class="bi bi-calendar2-x"></i>
        </div>
        <span class="no-schedule-label">NO ACTIVE SCHEDULE</span>
        <h1 class="no-schedule-title" id="no-schedule-title">No Training Scheduled Today</h1>
        <p class="no-schedule-message">{{ $message }}</p>
        <div class="no-schedule-actions">
            <a href="/student/dashboard" class="button no-schedule-dashboard-link">Back to Dashboard</a>
            <a href="/student/schedule" class="button button-secondary">View Schedule</a>
        </div>
    </section>
</div>
@endsection
