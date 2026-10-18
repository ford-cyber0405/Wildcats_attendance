<?php

namespace Tests\Feature;

use App\Models\Coach;
use App\Models\ExcuseRequest;
use App\Models\Notification;
use App\Models\SpecialTrainingRequest;
use App\Models\Sport;
use App\Models\Student;
use App\Models\TrainingSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationAndDashboardAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('sports', function ($table) {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('role');
            $table->string('email')->nullable();
            $table->string('student_id_number')->nullable();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('students', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('sport_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('course')->nullable();
            $table->string('year_level')->nullable();
            $table->timestamps();
        });

        Schema::create('coaches', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('sport_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->timestamps();
        });

        Schema::create('training_schedules', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('sport_id');
            $table->unsignedInteger('coach_id');
            $table->date('training_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('location')->nullable();
            $table->string('status')->default('training');
            $table->timestamps();
        });

        Schema::create('excuse_requests', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('student_id');
            $table->unsignedInteger('training_schedule_id');
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('special_training_requests', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('student_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attendances', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('student_id');
            $table->unsignedInteger('training_schedule_id')->nullable();
            $table->timestamp('time_in')->nullable();
            $table->string('status');
            $table->unsignedInteger('corrected_by')->nullable();
            $table->timestamps();
        });

        Schema::create('training_schedules', function ($table) {
            $table->increments('id');
            $table->date('training_date');
            $table->timestamps();
        });

        Schema::create('notifications', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->string('related_type')->nullable();
            $table->unsignedInteger('related_id')->nullable();
            $table->unsignedInteger('sport_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    public function test_coach_dashboard_pending_counts_match_database_rows(): void
    {
        $sport = Sport::create(['name' => 'taekwondo']);
        $coachUser = User::create([
            'role' => 'coach',
            'email' => 'coach@example.com',
            'password' => bcrypt('secret'),
        ]);
        $coach = Coach::create([
            'user_id' => $coachUser->id,
            'sport_id' => $sport->id,
            'first_name' => 'Coach',
            'last_name' => 'One',
        ]);
        $coachUser->coach()->save($coach);

        $studentUser = User::create([
            'role' => 'student',
            'student_id_number' => '2024001',
            'password' => bcrypt('secret'),
        ]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'sport_id' => $sport->id,
            'first_name' => 'Test',
            'last_name' => 'Student',
            'course' => 'BSIT',
            'year_level' => '3rd',
        ]);

        ExcuseRequest::create([
            'student_id' => $student->id,
            'training_schedule_id' => 1,
            'reason' => 'Need excuse',
            'status' => 'pending',
        ]);

        SpecialTrainingRequest::create([
            'student_id' => $student->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-05',
            'reason' => 'Need training',
            'status' => 'pending',
        ]);

        $this->actingAs($coachUser)
            ->get('/coach/dashboard')
            ->assertOk()
            ->assertSee('1')
            ->assertSee('1');
    }

    public function test_special_training_decision_creates_notification_for_student(): void
    {
        $sport = Sport::create(['name' => 'taekwondo']);
        $coachUser = User::create([
            'role' => 'coach',
            'email' => 'coach@example.com',
            'password' => bcrypt('secret'),
        ]);
        $coach = Coach::create([
            'user_id' => $coachUser->id,
            'sport_id' => $sport->id,
            'first_name' => 'Coach',
            'last_name' => 'One',
        ]);
        $coachUser->coach()->save($coach);

        $studentUser = User::create([
            'role' => 'student',
            'student_id_number' => '2024001',
            'password' => bcrypt('secret'),
        ]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'sport_id' => $sport->id,
            'first_name' => 'Test',
            'last_name' => 'Student',
            'course' => 'BSIT',
            'year_level' => '3rd',
        ]);

        $request = SpecialTrainingRequest::create([
            'student_id' => $student->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-05',
            'reason' => 'Need training',
            'status' => 'pending',
        ]);

        $this->actingAs($coachUser)
            ->post('/coach/special-training/' . $request->id . '/approve')
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $studentUser->id,
            'related_type' => 'special_training_request',
        ]);
        $this->assertSame(1, Notification::where('user_id', $studentUser->id)->where('is_read', false)->count());
    }

    public function test_admin_can_delete_student_from_system_settings(): void
    {
        $sport = Sport::create(['name' => 'taekwondo']);
        $admin = User::create([
            'role' => 'super_admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret'),
        ]);
        $studentUser = User::create([
            'role' => 'student',
            'student_id_number' => '2024001',
            'password' => bcrypt('secret'),
        ]);
        Student::create([
            'user_id' => $studentUser->id,
            'sport_id' => $sport->id,
            'first_name' => 'Test',
            'last_name' => 'Student',
            'course' => 'BSIT',
            'year_level' => '3rd',
        ]);

        $originalStoragePath = storage_path();
        $temporaryStoragePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'um-attendance-delete-' . uniqid();
        $this->app->useStoragePath($temporaryStoragePath);

        try {
            $this->actingAs($admin)
                ->post('/admin/student/2024001/delete')
                ->assertRedirect();

            $this->assertDatabaseMissing('users', ['id' => $studentUser->id]);
            $this->assertDatabaseMissing('students', ['user_id' => $studentUser->id]);

            $this->actingAs($admin)
                ->get('/admin/system-settings')
                ->assertOk()
                ->assertDontSee('Test Student');
        } finally {
            $temporaryDataPath = $temporaryStoragePath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'attendance_data.json';
            $temporaryLockPath = $temporaryDataPath . '.lock';
            if (file_exists($temporaryDataPath)) {
                unlink($temporaryDataPath);
            }
            if (file_exists($temporaryLockPath)) {
                unlink($temporaryLockPath);
            }
            $temporaryAppPath = dirname($temporaryDataPath);
            if (is_dir($temporaryAppPath)) {
                rmdir($temporaryAppPath);
            }
            if (is_dir($temporaryStoragePath)) {
                rmdir($temporaryStoragePath);
            }
            $this->app->useStoragePath($originalStoragePath);
        }
    }
}
