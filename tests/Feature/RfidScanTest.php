<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\RfidTag;

class RfidScanTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_a_tag_when_scanned()
    {
        $response = $this->postJson('/api/rfid/scan', ['tag' => 'TAG123']);
        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('rfid_tags', ['tag' => 'TAG123']);
    }

    /** @test */
    public function it_can_assign_tag_to_existing_user()
    {
        $user = User::factory()->create();
        $response = $this->postJson('/api/rfid/scan', ['tag' => 'TAG999', 'user_id' => $user->id]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('rfid_tags', ['tag' => 'TAG999', 'user_id' => $user->id]);
    }
}
