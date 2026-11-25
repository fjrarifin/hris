<?php

namespace Tests\Feature\Api;

use App\Models\Karyawan;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentCandidateUserInterview;
use App\Models\RecruitmentVacancy;
use App\Services\GoogleCalendarService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GoogleCalendarInterviewSyncTest extends TestCase
{
    private const TEST_PRIVATE_KEY_PEM = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDkE7ogdfBu5lc9
1leae1t8YtRvb4C2DoOfLXe7DmIO91Mkc5PBSsS7UtLCZOiHIzLqznSAHO7aWK35
8IbRZK6dm7dES3QRTABhTpcyqY6XwMnSrZng8jvzxqTvKGW/AQFUGR5rTg+MgM+N
U/AkxVKQVWb0fT8A+EVItQOPf6aXRBRIF8hMkaIHBTq+ix4vUG5Nu7UqNMJDVtJ7
iKiU2WOdDHDKxBoltZXF9iLlxNYLQn6l3bQOKewk80I0OyDEQ9Bar7W3rgc05fo4
we1IzNPoXG3AXZphV2gnYEoOVJRQ6gZhCYf0OXe/0CIglsvzjpK9HtVtKu+m41A/
MsCPYlk1AgMBAAECggEANv1One7xljtxA1a/bl6Zkybc4zU2Ci77qxAyUuZH3CXj
ZOF82VimkCeK6xWbPqHM7/HxAsZ3DCvfXmiQiMHGm/dkO1IZ3OW/HbbuG2foGkyA
HIyAkky1CbR2JbdOuLuMT3Ny8xoyA4U3BXMcbjEpE+WTWhUscJZrVflWr21ug/+Q
ZaDNk8Q7+IwXbJ8z5yTjtPjVFtOYx29f+RHl6KwhGA+g13ImTYe8MAl1ACO5MLNN
53fhxgvrthD1dnCNb2QrP2DXDJXeouCM76oibz2xc9Mj+1jWENbei4Or/QBQzfZQ
VB4bWvje4CgfUqNBQQ0sFP9FQIbq8OT3g2IecDOuwQKBgQD8saSodlSeAXTQVN6g
5LEH/ElG4qXdh/zlC43P0/HdsYsFvbnzDF/YHVsFtyq14NYOb8gAgn9RmDhDNoHR
Zn8AmO7F1TyfUuFx7zOxPFYCA/bVDuNP8dkgTynM7sQ2KRMkpFy5iF9NbUV/teeI
yph/VlfTi4YMDTDQ9jBKfsWWMQKBgQDnD6I8KgDQrsWKdslu2a9xC7kiFuCzhwBw
oUh8EllHFmEU37lkbUY5cRr1n98KoIZJQBWmGXeKXVitP4Bx3DY2NNC6AfTL4fdQ
obT0Xfzw4JR0UCA0JcLjRo7LhxYt3uHQJsFkCno9GXrYrfGkRFGUaUyRiyAT5m3Y
Rj6/+JE+RQKBgAzg0M91GGLlGBr1+FKi1RLfHKzsKB/eKllR+Ww6FP2Dat5kCPIG
E2xkMptb9mVGjCI4OC0ueWajJ1rlkEAnxRk2shSLBYfpJWw1EKFbvdZkdVPuak9M
uBAZeLvk/0LzXPJlHgSmmpTLmNslXiNuj88Ni3RQjAhZM7+yjEt/onpBAoGBAJex
6d757IMU2tfm2KQQAmCDQ84fmvICRcWr/TyHh1lmVeIwZzSJkTsRCJQNm48MiFz6
17uuqI6MbjV/pUIbQvQfhJE5y483EPk80OJF+lPh/VTHbf0OdX5kykbQrzz6QOI5
L2oQfuQyIDa1LBmeSLjY0M4UusCV3bPJmlTKSgDJAoGAfw/+Lp7EOGTP+f+U91Mj
7q/2NOuDFw2OmEk5OL9PjWjpHkbhj+xZPGHt/TNcLz0cXdQTAdgh0YFw1E3nwOM6
laCdJYL0yDR+aCAUQmKWEp9rlT5w6r7Q26xiEhfGh5uLij6vPgo8nL+0sy8yfkng
vzmlPKWOeoadYAnT+pvXneA=
-----END PRIVATE KEY-----
PEM;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        foreach ([
            'recruitment_user_interview_evaluations',
            'recruitment_candidate_user_interviews',
            'recruitment_candidates',
            'recruitment_vacancies',
            'm_karyawan',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('m_karyawan', function (Blueprint $table): void {
            $table->string('nik', 30)->primary();
            $table->string('nama_karyawan', 150);
            $table->string('email', 150)->nullable();
            $table->string('no_hp', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('recruitment_vacancies', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 150);
            $table->string('department', 100)->nullable();
            $table->string('unit', 100)->nullable();
            $table->string('status', 30)->default('open');
            $table->timestamps();
        });

        Schema::create('recruitment_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vacancy_id')->nullable();
            $table->string('name', 150);
            $table->string('email', 150);
            $table->string('phone', 30)->nullable();
            $table->string('status', 50)->default('interview_hr');
            $table->string('pic_nik', 30)->nullable();
            $table->date('interview_hr_date')->nullable();
            $table->string('interview_hr_time', 20)->nullable();
            $table->string('interview_hr_type', 20)->nullable();
            $table->string('interview_hr_location', 255)->nullable();
            $table->string('interview_hr_meet_link', 255)->nullable();
            $table->string('interview_hr_gcal_event_id', 255)->nullable();
            $table->string('interview_hr_gcal_event_link', 500)->nullable();
            $table->timestamp('interview_hr_gcal_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('recruitment_candidate_user_interviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('candidate_id');
            $table->unsignedTinyInteger('round')->default(1);
            $table->date('interview_date')->nullable();
            $table->string('interview_time', 20)->nullable();
            $table->string('interviewer_nik', 255)->nullable();
            $table->string('interview_type', 20)->nullable();
            $table->string('interview_location', 255)->nullable();
            $table->string('interview_meet_link', 255)->nullable();
            $table->string('gcal_event_id', 255)->nullable();
            $table->string('gcal_event_link', 500)->nullable();
            $table->timestamp('gcal_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('recruitment_user_interview_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('candidate_id');
            $table->unsignedTinyInteger('round')->default(1);
            $table->string('interviewer_nik', 30)->nullable();
            $table->timestamps();
        });
    }

    private function enableGoogleCalendar(): void
    {
        config([
            'services.google_calendar.enabled' => true,
            'services.google_calendar.auth_mode' => 'service_account',
            'services.google_calendar.calendar_id' => 'primary',
            'services.google_calendar.impersonate_email' => 'hrbp@hompimplay.id',
            'services.google_calendar.credentials_json' => json_encode([
                'client_email' => 'hris-calendar@hompimplay-hris.iam.gserviceaccount.com',
                'private_key' => self::TEST_PRIVATE_KEY_PEM,
            ]),
            'services.google_calendar.default_duration_minutes' => 60,
            'services.google_calendar.send_updates' => 'all',
            'services.google_calendar.auto_create_meet' => true,
        ]);
    }

    public function test_sync_is_skipped_when_disabled(): void
    {
        config(['services.google_calendar.enabled' => false]);
        Http::fake();

        $candidate = RecruitmentCandidate::query()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.test',
            'interview_hr_date' => '2026-12-01',
            'interview_hr_time' => '10:00',
            'interview_hr_type' => 'online',
        ]);

        $result = app(GoogleCalendarService::class)->syncHrInterview($candidate);

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_sync_hr_interview_creates_event_with_impersonation_and_auto_meet(): void
    {
        $this->enableGoogleCalendar();

        Karyawan::query()->create([
            'nik' => 'HR001',
            'nama_karyawan' => 'Rina HRBP',
            'email' => 'hrbp@hompimplay.id',
        ]);

        $vacancy = RecruitmentVacancy::query()->create([
            'title' => 'Backend Engineer',
            'department' => 'IT',
        ]);

        $candidate = RecruitmentCandidate::query()->create([
            'vacancy_id' => $vacancy->id,
            'name' => 'Budi Santoso',
            'email' => 'budi@example.test',
            'phone' => '08123456789',
            'pic_nik' => 'HR001',
            'interview_hr_date' => '2026-12-01',
            'interview_hr_time' => '10:00',
            'interview_hr_type' => 'online',
            'interview_hr_meet_link' => null,
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'fake-gcal-token',
                'expires_in' => 3600,
            ], 200),
            'https://www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
                'id' => 'gcal-hr-evt-123',
                'htmlLink' => 'https://calendar.google.com/calendar/event?eid=hr123',
                'hangoutLink' => 'https://meet.google.com/abc-defg-hij',
            ], 200),
        ]);

        $result = app(GoogleCalendarService::class)->syncHrInterview($candidate);

        $this->assertNotNull($result);
        $this->assertSame('gcal-hr-evt-123', $result['event_id']);
        $this->assertSame('https://calendar.google.com/calendar/event?eid=hr123', $result['html_link']);
        $this->assertSame('https://meet.google.com/abc-defg-hij', $result['meet_link']);

        Http::assertSent(function (HttpClientRequest $request): bool {
            if ($request->url() !== 'https://oauth2.googleapis.com/token') {
                return false;
            }
            $assertion = $request->data()['assertion'] ?? '';
            $parts = explode('.', $assertion);
            $claims = json_decode(base64_decode(strtr($parts[1] ?? '', '-_', '+/')), true);

            return ($claims['sub'] ?? null) === 'hrbp@hompimplay.id'
                && ($claims['scope'] ?? null) === 'https://www.googleapis.com/auth/calendar.events';
        });

        Http::assertSent(function (HttpClientRequest $request): bool {
            if (! str_starts_with($request->url(), 'https://www.googleapis.com/calendar/v3/calendars/primary/events')) {
                return false;
            }
            $data = $request->data();
            $attendeeEmails = array_column($data['attendees'] ?? [], 'email');

            return $request->method() === 'POST'
                && str_contains($request->url(), 'conferenceDataVersion=1')
                && str_contains($request->url(), 'sendUpdates=all')
                && ($data['summary'] ?? '') === '[Interview HR] Budi Santoso - Backend Engineer'
                && in_array('budi@example.test', $attendeeEmails, true)
                && in_array('hrbp@hompimplay.id', $attendeeEmails, true)
                && (($data['conferenceData']['createRequest']['conferenceSolutionKey']['type'] ?? null) === 'hangoutsMeet');
        });
    }

    public function test_sync_hr_interview_patches_existing_event_on_reschedule(): void
    {
        $this->enableGoogleCalendar();

        $candidate = RecruitmentCandidate::query()->create([
            'name' => 'Siti Aminah',
            'email' => 'siti@example.test',
            'interview_hr_date' => '2026-12-05',
            'interview_hr_time' => '14:30',
            'interview_hr_type' => 'offline',
            'interview_hr_location' => 'Ruang Meeting Lt. 2',
            'interview_hr_gcal_event_id' => 'existing-evt-999',
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token'], 200),
            'https://www.googleapis.com/calendar/v3/calendars/primary/events/existing-evt-999*' => Http::response([
                'id' => 'existing-evt-999',
                'htmlLink' => 'https://calendar.google.com/calendar/event?eid=999',
            ], 200),
        ]);

        $result = app(GoogleCalendarService::class)->syncHrInterview($candidate);

        $this->assertNotNull($result);
        $this->assertSame('existing-evt-999', $result['event_id']);

        Http::assertSent(fn (HttpClientRequest $request): bool => $request->method() === 'PATCH'
            && str_contains($request->url(), '/events/existing-evt-999')
            && ($request->data()['location'] ?? null) === 'Ruang Meeting Lt. 2');
    }

    public function test_sync_user_interview_invites_candidate_pic_and_multiple_interviewers(): void
    {
        $this->enableGoogleCalendar();

        Karyawan::query()->create(['nik' => 'HR001', 'nama_karyawan' => 'Rina HRBP', 'email' => 'hrbp@hompimplay.id']);
        Karyawan::query()->create(['nik' => 'USR01', 'nama_karyawan' => 'Andi Tech Lead', 'email' => 'andi@hompimplay.id']);
        Karyawan::query()->create(['nik' => 'USR02', 'nama_karyawan' => 'Dewi Manager', 'email' => 'dewi@hompimplay.id']);

        $vacancy = RecruitmentVacancy::query()->create(['title' => 'Product Designer']);
        $candidate = RecruitmentCandidate::query()->create([
            'vacancy_id' => $vacancy->id,
            'name' => 'Raka Pratama',
            'email' => 'raka@example.test',
            'pic_nik' => 'HR001',
        ]);

        $userInterview = RecruitmentCandidateUserInterview::query()->create([
            'candidate_id' => $candidate->id,
            'round' => 2,
            'interview_date' => '2026-12-10',
            'interview_time' => '13:00',
            'interviewer_nik' => 'USR01,USR02',
            'interview_type' => 'online',
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token'], 200),
            'https://www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
                'id' => 'gcal-user-evt-456',
                'htmlLink' => 'https://calendar.google.com/calendar/event?eid=user456',
                'hangoutLink' => 'https://meet.google.com/xyz-uvwx-yz1',
            ], 200),
        ]);

        $result = app(GoogleCalendarService::class)->syncUserInterview($candidate, $userInterview, ['USR01', 'USR02']);

        $this->assertNotNull($result);
        $this->assertSame('gcal-user-evt-456', $result['event_id']);
        $this->assertSame('https://meet.google.com/xyz-uvwx-yz1', $result['meet_link']);

        Http::assertSent(function (HttpClientRequest $request): bool {
            if (! str_starts_with($request->url(), 'https://www.googleapis.com/calendar/v3/calendars/primary/events')) {
                return false;
            }
            $data = $request->data();
            $emails = array_column($data['attendees'] ?? [], 'email');

            return ($data['summary'] ?? '') === '[Interview User Tahap 2] Raka Pratama - Product Designer'
                && count($emails) === 4
                && in_array('raka@example.test', $emails, true)
                && in_array('hrbp@hompimplay.id', $emails, true)
                && in_array('andi@hompimplay.id', $emails, true)
                && in_array('dewi@hompimplay.id', $emails, true);
        });
    }

    public function test_delete_event_sends_delete_request(): void
    {
        $this->enableGoogleCalendar();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token'], 200),
            'https://www.googleapis.com/calendar/v3/calendars/primary/events/evt-to-delete*' => Http::response('', 204),
        ]);

        $deleted = app(GoogleCalendarService::class)->deleteEvent('evt-to-delete');

        $this->assertTrue($deleted);
        Http::assertSent(fn (HttpClientRequest $request): bool => $request->method() === 'DELETE'
            && str_contains($request->url(), '/events/evt-to-delete'));
    }

    public function test_oauth_mode_refreshes_token_and_creates_event_with_attendees_and_meet(): void
    {
        config([
            'services.google_calendar.enabled' => true,
            'services.google_calendar.auth_mode' => 'oauth',
            'services.google_calendar.calendar_id' => 'primary',
            'services.google_calendar.client_id' => 'test-client-id.apps.googleusercontent.com',
            'services.google_calendar.client_secret' => 'test-client-secret',
            'services.google_calendar.refresh_token' => 'test-refresh-token-123',
            'services.google_calendar.default_duration_minutes' => 60,
            'services.google_calendar.send_updates' => 'all',
            'services.google_calendar.auto_create_meet' => true,
        ]);

        Karyawan::query()->create([
            'nik' => 'HR001',
            'nama_karyawan' => 'Rina HRBP',
            'email' => 'hrbp@hompimplay.id',
        ]);

        $candidate = RecruitmentCandidate::query()->create([
            'name' => 'Ayu Lestari',
            'email' => 'ayu@example.test',
            'pic_nik' => 'HR001',
            'interview_hr_date' => '2026-12-15',
            'interview_hr_time' => '09:30',
            'interview_hr_type' => 'online',
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'oauth-access-token-xyz',
                'expires_in' => 3600,
            ], 200),
            'https://www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
                'id' => 'oauth-evt-777',
                'htmlLink' => 'https://calendar.google.com/calendar/event?eid=oauth777',
                'hangoutLink' => 'https://meet.google.com/aaa-bbbb-ccc',
            ], 200),
        ]);

        $result = app(GoogleCalendarService::class)->syncHrInterview($candidate);

        $this->assertNotNull($result);
        $this->assertSame('oauth-evt-777', $result['event_id']);
        $this->assertSame('https://meet.google.com/aaa-bbbb-ccc', $result['meet_link']);

        Http::assertSent(fn (HttpClientRequest $request): bool => $request->url() === 'https://oauth2.googleapis.com/token'
            && ($request->data()['grant_type'] ?? null) === 'refresh_token'
            && ($request->data()['refresh_token'] ?? null) === 'test-refresh-token-123');

        Http::assertSent(function (HttpClientRequest $request): bool {
            if (! str_starts_with($request->url(), 'https://www.googleapis.com/calendar/v3/calendars/primary/events')) {
                return false;
            }
            $emails = array_column($request->data()['attendees'] ?? [], 'email');

            return str_contains($request->url(), 'conferenceDataVersion=1')
                && str_contains($request->url(), 'sendUpdates=all')
                && in_array('ayu@example.test', $emails, true)
                && in_array('hrbp@hompimplay.id', $emails, true);
        });
    }
}
