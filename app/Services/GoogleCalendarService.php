<?php

namespace App\Services;

use App\Models\Karyawan;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentCandidateUserInterview;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleCalendarService
{
    private const CALENDAR_API_BASE = 'https://www.googleapis.com/calendar/v3';

    private const OAUTH_AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const CALENDAR_SCOPE = 'https://www.googleapis.com/auth/calendar.events';

    public function isEnabled(): bool
    {
        return (bool) config('services.google_calendar.enabled', false);
    }

    public function authMode(): string
    {
        $mode = strtolower(trim((string) config('services.google_calendar.auth_mode', 'oauth')));

        return in_array($mode, ['oauth', 'service_account'], true) ? $mode : 'oauth';
    }

    public function isConnected(): bool
    {
        if ($this->authMode() === 'oauth') {
            return ! empty(config('services.google_calendar.client_id'))
                && ! empty(config('services.google_calendar.client_secret'))
                && ! empty($this->resolveRefreshToken());
        }

        $credentials = $this->credentials();

        return ! empty($credentials['client_email']) && ! empty($credentials['private_key']);
    }

    public function defaultRedirectUri(): string
    {
        $configured = trim((string) config('services.google_calendar.redirect_uri', ''));
        if ($configured !== '') {
            return $configured;
        }

        return rtrim((string) config('app.url', 'http://127.0.0.1:8000'), '/').'/google-calendar/oauth/callback';
    }

    public function buildAuthorizationUrl(string $state, ?string $redirectUri = null): string
    {
        $clientId = trim((string) config('services.google_calendar.client_id', ''));
        $redirect = $redirectUri ?: $this->defaultRedirectUri();

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => self::CALENDAR_SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return self::OAUTH_AUTH_URL.'?'.$query;
    }

    /**
     * Exchange OAuth authorization code for tokens and persist refresh token.
     *
     * @return array{refresh_token: ?string, access_token: ?string, expires_in: int}|null
     */
    public function exchangeAuthorizationCode(string $code, ?string $redirectUri = null): ?array
    {
        $clientId = trim((string) config('services.google_calendar.client_id', ''));
        $clientSecret = trim((string) config('services.google_calendar.client_secret', ''));
        $redirect = $redirectUri ?: $this->defaultRedirectUri();

        if ($clientId === '' || $clientSecret === '') {
            Log::warning('Google Calendar OAuth client_id or client_secret is missing');

            return null;
        }

        try {
            $response = Http::timeout((int) config('services.google_calendar.timeout', 15))
                ->asForm()
                ->post(self::OAUTH_TOKEN_URL, [
                    'code' => $code,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri' => $redirect,
                    'grant_type' => 'authorization_code',
                ]);

            if ($response->failed()) {
                Log::warning('Google Calendar OAuth code exchange failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();
            $refreshToken = is_string($data['refresh_token'] ?? null) ? $data['refresh_token'] : null;
            $accessToken = is_string($data['access_token'] ?? null) ? $data['access_token'] : null;

            if ($refreshToken) {
                $this->persistRefreshToken($refreshToken);
                Cache::forget($this->oauthCacheKey($clientId, $refreshToken));
            }

            return [
                'refresh_token' => $refreshToken,
                'access_token' => $accessToken,
                'expires_in' => (int) ($data['expires_in'] ?? 3600),
            ];
        } catch (\Throwable $e) {
            Log::error('Google Calendar OAuth code exchange exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create or update a Google Calendar event for an HR interview.
     *
     * @return array{event_id: string, html_link: ?string, meet_link: ?string, synced_at: Carbon}|null
     */
    public function syncHrInterview(RecruitmentCandidate $candidate): ?array
    {
        if (! $this->isEnabled() || ! $candidate->interview_hr_date || ! $candidate->interview_hr_time) {
            return null;
        }

        $candidate->loadMissing(['vacancy', 'pic']);

        $vacancyTitle = $candidate->vacancy?->title ?: 'Umum';
        $isOnline = $candidate->interview_hr_type === 'online';
        $manualMeetLink = trim((string) ($candidate->interview_hr_meet_link ?? ''));
        $location = $isOnline
            ? ($manualMeetLink !== '' ? $manualMeetLink : null)
            : (trim((string) ($candidate->interview_hr_location ?? '')) ?: null);

        $attendees = $this->buildAttendees([
            ['email' => $candidate->email, 'displayName' => $candidate->name],
            ['email' => $candidate->pic?->email, 'displayName' => $candidate->pic?->nama_karyawan],
        ]);

        $descriptionLines = array_filter([
            "Wawancara HR - {$candidate->name}",
            "Posisi: {$vacancyTitle}",
            $candidate->pic?->nama_karyawan ? "PIC Recruitment: {$candidate->pic->nama_karyawan}" : null,
            'Tipe: '.($isOnline ? 'Online' : 'Offline'),
            $location ? ($isOnline ? "Link Meeting: {$location}" : "Lokasi: {$location}") : null,
            $candidate->phone ? "No. HP Kandidat: {$candidate->phone}" : null,
        ]);

        $shouldAutoCreateMeet = $isOnline
            && $manualMeetLink === ''
            && (bool) config('services.google_calendar.auto_create_meet', true);

        return $this->upsertEvent(
            existingEventId: $candidate->interview_hr_gcal_event_id,
            summary: "[Interview HR] {$candidate->name} - {$vacancyTitle}",
            description: implode("\n", $descriptionLines),
            date: (string) $candidate->interview_hr_date,
            time: (string) $candidate->interview_hr_time,
            location: $location,
            attendees: $attendees,
            autoCreateMeet: $shouldAutoCreateMeet,
            requestIdPrefix: "hris-hr-{$candidate->id}",
        );
    }

    /**
     * Create or update a Google Calendar event for a User interview round.
     *
     * @param  array<int, string>  $interviewerNiks
     * @return array{event_id: string, html_link: ?string, meet_link: ?string, synced_at: Carbon}|null
     */
    public function syncUserInterview(
        RecruitmentCandidate $candidate,
        RecruitmentCandidateUserInterview $userInterview,
        array $interviewerNiks = [],
    ): ?array {
        if (! $this->isEnabled() || ! $userInterview->interview_date || ! $userInterview->interview_time) {
            return null;
        }

        $candidate->loadMissing(['vacancy', 'pic']);

        if ($interviewerNiks === [] && ! empty($userInterview->interviewer_nik)) {
            $interviewerNiks = array_values(array_filter(array_map(
                'trim',
                explode(',', (string) $userInterview->interviewer_nik)
            )));
        }

        $interviewers = $interviewerNiks !== []
            ? Karyawan::query()->whereIn('nik', $interviewerNiks)->get(['nik', 'nama_karyawan', 'email'])
            : collect();

        $vacancyTitle = $candidate->vacancy?->title ?: 'Umum';
        $isOnline = $userInterview->interview_type === 'online';
        $manualMeetLink = trim((string) ($userInterview->interview_meet_link ?? ''));
        $location = $isOnline
            ? ($manualMeetLink !== '' ? $manualMeetLink : null)
            : (trim((string) ($userInterview->interview_location ?? '')) ?: null);

        $rawAttendees = [
            ['email' => $candidate->email, 'displayName' => $candidate->name],
            ['email' => $candidate->pic?->email, 'displayName' => $candidate->pic?->nama_karyawan],
        ];
        foreach ($interviewers as $interviewer) {
            $rawAttendees[] = [
                'email' => $interviewer->email,
                'displayName' => $interviewer->nama_karyawan,
            ];
        }

        $attendees = $this->buildAttendees($rawAttendees);
        $interviewerNames = $interviewers->pluck('nama_karyawan')->filter()->implode(', ');

        $descriptionLines = array_filter([
            "Wawancara User Tahap {$userInterview->round} - {$candidate->name}",
            "Posisi: {$vacancyTitle}",
            $interviewerNames !== '' ? "Pewawancara: {$interviewerNames}" : null,
            $candidate->pic?->nama_karyawan ? "PIC Recruitment: {$candidate->pic->nama_karyawan}" : null,
            'Tipe: '.($isOnline ? 'Online' : 'Offline'),
            $location ? ($isOnline ? "Link Meeting: {$location}" : "Lokasi: {$location}") : null,
            $candidate->phone ? "No. HP Kandidat: {$candidate->phone}" : null,
        ]);

        $shouldAutoCreateMeet = $isOnline
            && $manualMeetLink === ''
            && (bool) config('services.google_calendar.auto_create_meet', true);

        return $this->upsertEvent(
            existingEventId: $userInterview->gcal_event_id,
            summary: "[Interview User Tahap {$userInterview->round}] {$candidate->name} - {$vacancyTitle}",
            description: implode("\n", $descriptionLines),
            date: (string) $userInterview->interview_date,
            time: (string) $userInterview->interview_time,
            location: $location,
            attendees: $attendees,
            autoCreateMeet: $shouldAutoCreateMeet,
            requestIdPrefix: "hris-user-{$candidate->id}-r{$userInterview->round}",
        );
    }

    public function deleteEvent(?string $eventId): bool
    {
        if (! $this->isEnabled() || empty($eventId)) {
            return false;
        }

        $accessToken = $this->accessToken();
        if (! $accessToken) {
            return false;
        }

        $calendarId = rawurlencode((string) config('services.google_calendar.calendar_id', 'primary'));
        $sendUpdates = (string) config('services.google_calendar.send_updates', 'all');
        $url = self::CALENDAR_API_BASE."/calendars/{$calendarId}/events/".rawurlencode($eventId);

        try {
            $response = Http::timeout((int) config('services.google_calendar.timeout', 15))
                ->withToken($accessToken)
                ->delete($url.'?'.http_build_query(['sendUpdates' => $sendUpdates]));

            if ($response->successful() || in_array($response->status(), [404, 410], true)) {
                return true;
            }

            Log::warning('Google Calendar event deletion failed', [
                'event_id' => $eventId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Google Calendar event deletion exception', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * @param  array<int, array{email: string, displayName?: string}>  $attendees
     * @return array{event_id: string, html_link: ?string, meet_link: ?string, synced_at: Carbon}|null
     */
    private function upsertEvent(
        ?string $existingEventId,
        string $summary,
        string $description,
        string $date,
        string $time,
        ?string $location,
        array $attendees,
        bool $autoCreateMeet,
        string $requestIdPrefix,
    ): ?array {
        $accessToken = $this->accessToken();
        if (! $accessToken) {
            Log::warning('Google Calendar sync skipped: failed to acquire access token');

            return null;
        }

        $timezone = config('app.timezone', 'Asia/Jakarta') ?: 'Asia/Jakarta';
        $durationMinutes = max(15, (int) config('services.google_calendar.default_duration_minutes', 60));

        try {
            $start = Carbon::parse(Carbon::parse($date)->toDateString().' '.$time, $timezone);
            $end = $start->copy()->addMinutes($durationMinutes);
        } catch (\Throwable $e) {
            Log::warning('Google Calendar sync skipped: invalid schedule date/time', [
                'date' => $date,
                'time' => $time,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $supportsFullFeatures = $this->authMode() === 'oauth'
            || trim((string) config('services.google_calendar.impersonate_email', '')) !== '';
        $shouldAutoCreateMeet = $autoCreateMeet && $supportsFullFeatures;

        $payload = [
            'summary' => $summary,
            'description' => $description,
            'start' => [
                'dateTime' => $start->toIso8601String(),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => $end->toIso8601String(),
                'timeZone' => $timezone,
            ],
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'email', 'minutes' => 24 * 60],
                    ['method' => 'popup', 'minutes' => 30],
                ],
            ],
        ];

        // Google API rejects attendees on Service Accounts without Workspace Domain-Wide Delegation.
        if ($supportsFullFeatures && $attendees !== []) {
            $payload['attendees'] = $attendees;
        }

        if ($location !== null) {
            $payload['location'] = $location;
        }

        if ($shouldAutoCreateMeet) {
            $payload['conferenceData'] = [
                'createRequest' => [
                    'requestId' => $requestIdPrefix.'-'.Str::lower(Str::random(10)),
                    'conferenceSolutionKey' => [
                        'type' => 'hangoutsMeet',
                    ],
                ],
            ];
        }

        $calendarId = rawurlencode((string) config('services.google_calendar.calendar_id', 'primary'));
        $query = [
            'sendUpdates' => $supportsFullFeatures
                ? (string) config('services.google_calendar.send_updates', 'all')
                : 'none',
        ];
        if ($shouldAutoCreateMeet) {
            $query['conferenceDataVersion'] = 1;
        }

        $timeout = (int) config('services.google_calendar.timeout', 15);
        $baseUrl = self::CALENDAR_API_BASE."/calendars/{$calendarId}/events";
        $queryString = http_build_query($query);

        try {
            $response = null;

            if (! empty($existingEventId)) {
                $response = Http::timeout($timeout)
                    ->withToken($accessToken)
                    ->patch($baseUrl.'/'.rawurlencode($existingEventId).'?'.$queryString, $payload);

                // If the event was manually deleted in Google Calendar, recreate it.
                if (in_array($response->status(), [404, 410], true)) {
                    $response = Http::timeout($timeout)
                        ->withToken($accessToken)
                        ->post($baseUrl.'?'.$queryString, $payload);
                }
            } else {
                $response = Http::timeout($timeout)
                    ->withToken($accessToken)
                    ->post($baseUrl.'?'.$queryString, $payload);
            }

            if ($response->failed()) {
                Log::warning('Google Calendar API sync failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'existing_event_id' => $existingEventId,
                ]);

                return null;
            }

            $data = $response->json();
            $eventId = $data['id'] ?? null;
            if (! $eventId) {
                return null;
            }

            return [
                'event_id' => (string) $eventId,
                'html_link' => isset($data['htmlLink']) ? (string) $data['htmlLink'] : null,
                'meet_link' => $this->extractMeetLink($data),
                'synced_at' => now(),
            ];
        } catch (\Throwable $e) {
            Log::error('Google Calendar API sync exception', [
                'error' => $e->getMessage(),
                'existing_event_id' => $existingEventId,
            ]);

            return null;
        }
    }

    private function extractMeetLink(array $data): ?string
    {
        if (! empty($data['hangoutLink']) && is_string($data['hangoutLink'])) {
            return $data['hangoutLink'];
        }

        $entryPoints = $data['conferenceData']['entryPoints'] ?? [];
        if (is_array($entryPoints)) {
            foreach ($entryPoints as $entry) {
                if (($entry['entryPointType'] ?? null) === 'video' && ! empty($entry['uri'])) {
                    return (string) $entry['uri'];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{email: ?string, displayName?: ?string}>  $candidates
     * @return array<int, array{email: string, displayName?: string}>
     */
    private function buildAttendees(array $candidates): array
    {
        $seen = [];
        $attendees = [];

        foreach ($candidates as $item) {
            $email = strtolower(trim((string) ($item['email'] ?? '')));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            if (isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;

            $attendee = ['email' => $email];
            $displayName = trim((string) ($item['displayName'] ?? ''));
            if ($displayName !== '') {
                $attendee['displayName'] = $displayName;
            }
            $attendees[] = $attendee;
        }

        return $attendees;
    }

    private function accessToken(): ?string
    {
        if ($this->authMode() === 'oauth') {
            return $this->oauthAccessToken();
        }

        return $this->serviceAccountAccessToken();
    }

    private function oauthAccessToken(): ?string
    {
        $clientId = trim((string) config('services.google_calendar.client_id', ''));
        $clientSecret = trim((string) config('services.google_calendar.client_secret', ''));
        $refreshToken = $this->resolveRefreshToken();

        if ($clientId === '' || $clientSecret === '' || ! $refreshToken) {
            Log::warning('Google Calendar OAuth credentials or refresh token are missing');

            return null;
        }

        $cacheKey = $this->oauthCacheKey($clientId, $refreshToken);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::timeout((int) config('services.google_calendar.timeout', 15))
                ->asForm()
                ->post(self::OAUTH_TOKEN_URL, [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refreshToken,
                    'grant_type' => 'refresh_token',
                ]);

            if ($response->failed()) {
                Log::warning('Google Calendar OAuth refresh token request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $token = $response->json('access_token');
            if (! is_string($token) || $token === '') {
                return null;
            }

            Cache::put($cacheKey, $token, 50 * 60);

            return $token;
        } catch (\Throwable $e) {
            Log::error('Google Calendar OAuth refresh token exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function oauthCacheKey(string $clientId, string $refreshToken): string
    {
        return 'google_calendar_oauth_access_token_'.md5($clientId.'|'.$refreshToken);
    }

    private function resolveRefreshToken(): ?string
    {
        $envToken = trim((string) config('services.google_calendar.refresh_token', ''));
        if ($envToken !== '') {
            return $envToken;
        }

        $tokenFile = (string) config('services.google_calendar.token_file', storage_path('app/google-calendar-oauth.json'));
        if ($tokenFile !== '' && is_file($tokenFile) && is_readable($tokenFile)) {
            $decoded = json_decode((string) file_get_contents($tokenFile), true);
            $stored = trim((string) ($decoded['refresh_token'] ?? ''));
            if ($stored !== '') {
                return $stored;
            }
        }

        return null;
    }

    private function persistRefreshToken(string $refreshToken): void
    {
        $tokenFile = (string) config('services.google_calendar.token_file', storage_path('app/google-calendar-oauth.json'));
        if ($tokenFile === '') {
            return;
        }

        $dir = dirname($tokenFile);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        file_put_contents($tokenFile, json_encode([
            'refresh_token' => $refreshToken,
            'updated_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT));
    }

    private function serviceAccountAccessToken(): ?string
    {
        $credentials = $this->credentials();
        if (! $credentials || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            Log::warning('Google Calendar credentials are missing or invalid');

            return null;
        }

        $impersonateEmail = trim((string) config('services.google_calendar.impersonate_email', ''));
        $cacheKey = 'google_calendar_access_token_'.md5($credentials['client_email'].'|'.$impersonateEmail);

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $now = time();
        $claims = [
            'iss' => $credentials['client_email'],
            'scope' => self::CALENDAR_SCOPE,
            'aud' => self::OAUTH_TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        if ($impersonateEmail !== '') {
            $claims['sub'] = $impersonateEmail;
        }

        $jwt = $this->jwt($claims, (string) $credentials['private_key']);
        if (! $jwt) {
            Log::error('Google Calendar failed to sign JWT assertion');

            return null;
        }

        try {
            $response = Http::timeout((int) config('services.google_calendar.timeout', 15))
                ->asForm()
                ->post(self::OAUTH_TOKEN_URL, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

            if ($response->failed()) {
                Log::warning('Google Calendar OAuth token request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $token = $response->json('access_token');
            if (! is_string($token) || $token === '') {
                return null;
            }

            Cache::put($cacheKey, $token, 50 * 60);

            return $token;
        } catch (\Throwable $e) {
            Log::error('Google Calendar OAuth token exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function credentials(): ?array
    {
        $json = config('services.google_calendar.credentials_json');
        $path = config('services.google_calendar.credentials_file');

        if (is_string($json) && trim($json) !== '') {
            $decoded = json_decode($json, true);

            return is_array($decoded) ? $decoded : null;
        }

        if (is_string($path) && trim($path) !== '') {
            $resolvedPath = is_file($path) ? $path : base_path($path);
            if (is_file($resolvedPath) && is_readable($resolvedPath)) {
                $decoded = json_decode((string) file_get_contents($resolvedPath), true);

                return is_array($decoded) ? $decoded : null;
            }

            Log::error('Google Calendar credentials file is not readable', [
                'path' => $path,
            ]);
        }

        return null;
    }

    private function jwt(array $claims, string $privateKey): ?string
    {
        $header = $this->base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64Url((string) json_encode($claims));
        $unsigned = "{$header}.{$payload}";
        $signature = '';

        if (! @openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        return $unsigned.'.'.$this->base64Url($signature);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
