<?php

namespace Tests\Feature;

use App\Http\Services\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppServiceTest extends TestCase
{
    public function test_it_normalizes_phone_with_zero_after_indonesia_country_code(): void
    {
        config()->set('services.whatsapp.url', 'http://whatsapp.test');
        config()->set('services.whatsapp.device_id', 'device-id');
        Http::fake([
            'http://whatsapp.test/send/message' => Http::response(['ok' => true]),
        ]);

        $sent = app(WhatsAppService::class)->sendMessage('62082219449223', 'Pesan test');

        $this->assertTrue($sent);
        Http::assertSent(fn ($request): bool => $request['phone'] === '6282219449223');
    }
}
