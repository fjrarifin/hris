<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GaWorkOrderPublicApprovalPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ga_ems.url' => 'http://ga.test',
            'services.ga_ems.secret' => 'test-secret',
            'services.public_approval.base_url' => 'https://api-hr.hompimplay.id',
        ]);
    }

    private function payload(string $stage = 'initial'): array
    {
        return [
            'work_order' => [
                'id' => 77,
                'excel_no' => null,
                'area_name' => 'HOMPIM PLAY',
                'job_type' => 'WORK ORDER',
                'project_description' => 'Perbaikan jaringan LAN area tiket',
                'request_date' => '2026-11-26',
                'target_date' => '2026-11-28',
                'status' => $stage === 'rab' ? 'RAB_PENDING' : 'WAITING_APPROVAL',
                'notes' => null,
                'rab_total' => $stage === 'rab' ? 4000000 : 0,
                'rab_notes' => $stage === 'rab' ? 'Penggantian switch' : null,
                'photo_path' => null,
                'photo_paths' => [],
                'request_document_path' => null,
                'spl_document_path' => null,
            ],
            'requester' => ['id' => 1, 'name' => 'Karyawan IT', 'nik' => 'IT001'],
            'approver' => ['id' => 2, 'name' => 'Approver IT', 'nik' => 'IT002'],
            'approval' => [
                'id' => 5,
                'stage' => $stage,
                'status' => 'pending',
                'comment' => null,
                'acted_at' => null,
            ],
            'approvals' => [
                [
                    'id' => 5,
                    'stage' => $stage,
                    'status' => 'pending',
                    'comment' => null,
                    'acted_at' => null,
                    'approver_name' => 'Approver IT',
                    'approver_nik' => 'IT002',
                ],
            ],
            'rab_items' => $stage === 'rab' ? [[
                'item_name' => 'Switch 24 Port',
                'specification' => 'Managed',
                'qty' => 1,
                'unit' => 'PCS',
                'unit_price' => 4000000,
                'subtotal' => 4000000,
            ]] : [],
            'can_act' => true,
        ];
    }

    public function test_public_page_renders_work_order_detail_from_ga_service(): void
    {
        Http::fake([
            'http://ga.test/api/internal/hris/work-orders/public/tokenABC123456' => Http::response($this->payload(), 200),
        ]);

        $this->get('/wo/tokenABC123456')
            ->assertOk()
            ->assertSee('Persetujuan Work Order')
            ->assertSee('Perbaikan jaringan LAN area tiket')
            ->assertSee('Karyawan IT')
            ->assertSee('Setujui');
    }

    public function test_rab_stage_page_shows_budget_items_and_revision_action(): void
    {
        Http::fake([
            'http://ga.test/api/internal/hris/work-orders/public/tokenRAB1234567' => Http::response($this->payload('rab'), 200),
        ]);

        $this->get('/wo/tokenRAB1234567')
            ->assertOk()
            ->assertSee('Persetujuan RAB Work Order')
            ->assertSee('Switch 24 Port')
            ->assertSee('Minta Revisi');
    }

    public function test_public_page_shows_invalid_state_when_token_is_unknown(): void
    {
        Http::fake([
            'http://ga.test/*' => Http::response(['message' => 'Tautan persetujuan tidak valid.'], 404),
        ]);

        $this->get('/wo/unknownToken123')
            ->assertOk()
            ->assertSee('Tautan Tidak Valid')
            ->assertSee('Tautan persetujuan tidak valid');
    }

    public function test_reject_requires_comment_before_calling_ga_service(): void
    {
        Http::fake();

        $this->post('/wo/tokenABC123456/decide', ['decision' => 'reject'])
            ->assertRedirect('/wo/tokenABC123456')
            ->assertSessionHasErrors('comment');

        Http::assertNothingSent();
    }

    public function test_approve_forwards_decision_to_ga_service_and_redirects_with_status(): void
    {
        Http::fake([
            'http://ga.test/api/internal/hris/work-orders/public/tokenABC123456/decide' => Http::response([
                'message' => 'Persetujuan berhasil dicatat.',
                'status' => 'PENDING_GA',
            ], 200),
        ]);

        $this->post('/wo/tokenABC123456/decide', [
            'decision' => 'approve',
            'comment' => 'Setuju',
        ])->assertRedirect('/wo/tokenABC123456')
            ->assertSessionHas('status', 'Persetujuan berhasil dicatat.');

        Http::assertSent(fn ($request) => $request->url() === 'http://ga.test/api/internal/hris/work-orders/public/tokenABC123456/decide'
            && $request->method() === 'POST'
            && $request->hasHeader('X-GA-Service-Secret', 'test-secret'));
    }
}
