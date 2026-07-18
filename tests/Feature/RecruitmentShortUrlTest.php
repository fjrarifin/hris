<?php

namespace Tests\Feature;

use App\Models\RecruitmentShortUrl;
use App\Services\RecruitmentShortUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecruitmentShortUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_shorten_and_redirect_url(): void
    {
        $longUrl = 'http://localhost:5173/public/offering/review/sample-token';
        
        $service = app(RecruitmentShortUrlService::class);
        $shortUrl = $service->shorten($longUrl);

        $this->assertStringContainsString('/s/', $shortUrl);
        
        $code = substr($shortUrl, strrpos($shortUrl, '/') + 1);
        
        $this->assertDatabaseHas('recruitment_short_urls', [
            'code' => $code,
            'destination_url' => $longUrl,
            'clicks_count' => 0,
        ]);

        $response = $this->get("/s/{$code}");

        $response->assertRedirect($longUrl);
        $response->assertStatus(302);

        $this->assertDatabaseHas('recruitment_short_urls', [
            'code' => $code,
            'clicks_count' => 1,
        ]);
    }

    public function test_it_returns_410_for_expired_urls(): void
    {
        $longUrl = 'http://localhost:5173/public/offering/review/sample-token';
        
        $service = app(RecruitmentShortUrlService::class);
        $shortUrl = $service->shorten($longUrl, now()->subMinutes(1)); // expired 1 minute ago

        $code = substr($shortUrl, strrpos($shortUrl, '/') + 1);

        $response = $this->get("/s/{$code}");

        $response->assertStatus(410);
    }

    public function test_it_returns_404_for_non_existent_urls(): void
    {
        $response = $this->get('/s/nonExist');

        $response->assertStatus(404);
    }
}
