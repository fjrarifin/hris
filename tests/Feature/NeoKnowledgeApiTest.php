<?php

namespace Tests\Feature;

use App\Models\FrontendMenu;
use App\Models\NeoKnowledge;
use App\Models\User;
use App\Models\WhatsappBotFaq;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NeoKnowledgeApiTest extends TestCase
{
    private User $itUser;
    private User $hrUser;
    private User $staffUser;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('t_neo_knowledge_revisions');
        Schema::dropIfExists('t_neo_knowledge');
        Schema::dropIfExists('t_whatsapp_bot_faq');
        Schema::dropIfExists('t_wa_agent_unresolved_queries');
        Schema::dropIfExists('frontend_menu_user_access');
        Schema::dropIfExists('frontend_menus');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedTinyInteger('level')->default(3);
            $table->timestamps();
        });

        Schema::create('frontend_menus', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('path');
            $table->string('allowed_levels')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('frontend_menu_user_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('frontend_menu_id');
            $table->boolean('has_access')->default(true);
            $table->timestamps();
        });

        Schema::create('t_neo_knowledge', function (Blueprint $table) {
            $table->id();
            $table->string('section_key', 150)->unique();
            $table->string('title', 255);
            $table->longText('content');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('t_neo_knowledge_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('neo_knowledge_id');
            $table->string('title', 255);
            $table->longText('content');
            $table->string('changed_by', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('t_whatsapp_bot_faq', function (Blueprint $table) {
            $table->id();
            $table->string('topic')->nullable();
            $table->text('keywords');
            $table->text('answer');
            $table->integer('priority')->default(0);
            $table->unsignedTinyInteger('max_user_level')->default(3);
            $table->string('match_type', 20)->default('contains');
            $table->boolean('is_active')->default(true);
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('t_wa_agent_unresolved_queries', function (Blueprint $table) {
            $table->id();
            $table->string('sender_phone', 50);
            $table->string('karyawan_nik', 50)->nullable();
            $table->string('sender_name', 255)->nullable();
            $table->text('question');
            $table->text('bot_response')->nullable();
            $table->string('category', 100)->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('admin_notes')->nullable();
            $table->unsignedInteger('ask_count')->default(1);
            $table->timestamp('last_asked_at')->nullable();
            $table->timestamps();
        });

        FrontendMenu::create([
            'key' => 'it-neo-knowledge',
            'label' => 'Neo Knowledge',
            'path' => '/it/neo-knowledge',
            'allowed_levels' => '0,2',
            'is_active' => true,
        ]);

        $this->itUser = User::create([
            'username' => 'IT001',
            'name' => 'IT Admin',
            'email' => 'it@example.com',
            'password' => 'secret',
            'level' => 0,
        ]);

        $this->hrUser = User::create([
            'username' => 'HR001',
            'name' => 'HR Staff',
            'email' => 'hr@example.com',
            'password' => 'secret',
            'level' => 2,
        ]);

        $this->staffUser = User::create([
            'username' => 'STF001',
            'name' => 'Staff Biasa',
            'email' => 'staff@example.com',
            'password' => 'secret',
            'level' => 3,
        ]);
    }

    public function test_it_and_hr_can_list_and_create_neo_knowledge(): void
    {
        Sanctum::actingAs($this->itUser);

        $createRes = $this->postJson('/api/neo/knowledge', [
            'title' => 'Seksi Uji Coba SOP',
            'content' => '## Aturan SOP Baru\n- Poin 1\n- Poin 2',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $createRes->assertCreated()
            ->assertJsonPath('data.title', 'Seksi Uji Coba SOP');

        $this->assertDatabaseHas('t_neo_knowledge', [
            'title' => 'Seksi Uji Coba SOP',
        ]);

        // List
        $listRes = $this->getJson('/api/neo/knowledge');
        $listRes->assertOk()
            ->assertJsonStructure(['data', 'kpi']);
    }

    public function test_it_can_create_and_test_faq_matching(): void
    {
        Sanctum::actingAs($this->itUser);

        $faqRes = $this->postJson('/api/neo/faqs', [
            'topic' => 'Aturan Seragam',
            'keywords' => 'seragam kantor, baju dinas',
            'answer' => 'Hari Senin-Kamis kemeja rapi, Jumat batik bebas.',
            'priority' => 100,
            'max_user_level' => 3,
            'match_type' => 'contains',
            'is_active' => true,
        ]);

        $faqRes->assertCreated();

        // Simulator
        $testRes = $this->postJson('/api/neo/faqs/test', [
            'question' => 'besok pakai seragam kantor apa ya',
            'level' => 3,
        ]);

        $testRes->assertOk()
            ->assertJsonPath('matched', true)
            ->assertJsonPath('data.topic', 'Aturan Seragam');
    }

    public function test_staff_cannot_access_neo_admin_api(): void
    {
        Sanctum::actingAs($this->staffUser);

        $res = $this->getJson('/api/neo/knowledge');
        $res->assertForbidden();
    }
}