<?php

namespace App\Services;

use App\Models\RecruitmentShortUrl;
use Illuminate\Support\Str;
use Carbon\Carbon;

class RecruitmentShortUrlService
{
    /**
     * Shorten a long destination URL.
     *
     * @param string $destinationUrl
     * @param Carbon|null $expiresAt
     * @return string
     */
    public function shorten(string $destinationUrl, ?Carbon $expiresAt = null, ?string $customBaseUrl = null): string
    {
        do {
            $code = Str::random(6);
        } while (RecruitmentShortUrl::where('code', $code)->exists());

        RecruitmentShortUrl::create([
            'code' => $code,
            'destination_url' => $destinationUrl,
            'expires_at' => $expiresAt,
        ]);

        $baseUrl = $customBaseUrl
            ?: (config('services.public_approval.base_url')
            ?: (request()?->getSchemeAndHttpHost()
            ?: (config('app.frontend_url')
            ?: (config('app.url') ?: 'http://localhost:8000'))));

        return rtrim((string) $baseUrl, '/') . '/s/' . $code;
    }
}
