<?php

namespace Tests\Feature;

use App\Models\{Inquiry, Service, Ticket, User};
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InboxAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!str_ends_with(DB::connection()->getDatabaseName(), '_test')) $this->markTestSkipped('Dedicated test database required.');
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if (DB::connection()->transactionLevel()) DB::rollBack();
        parent::tearDown();
    }

    public function test_inbox_workspace_has_simplified_header_and_role_safe_actions(): void
    {
        foreach (['cs', 'owner', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role, 'active' => true]);
            $response = $this->actingAs($user)->get(route('inbox'))->assertOk()
                ->assertSee('data-workspace="inbox"', false)
                ->assertSee('<h1>Inbox WhatsApp</h1>', false)
                ->assertSee('aria-labelledby="inbox-requests"', false)
                ->assertSee('aria-labelledby="inbox-handoffs-title"', false)
                ->assertSee('aria-label="Status inbox"', false)
                ->assertSee('for="inbox-handling"', false)
                ->assertDontSee('href="#inbox-handoffs"', false)
                ->assertDontSee('Detail koneksi WhatsApp')
                ->assertDontSee('class="inbox-notice"', false)
                ->assertDontSee('Ruang kerja / Komunikasi');
            $html = $response->getContent();
            $this->assertSame(1, substr_count($html, '<h1>'));
            $this->assertSame(1, substr_count($html, 'class="crumb"'));
            if ($role === 'admin') {
                $response->assertDontSee('href="'.route('inquiries.create').'"', false);
            } else {
                $response->assertSee('href="'.route('inquiries.create').'"', false)
                    ->assertSee('Tambahkan permintaan manual');
            }
        }
    }

    private function inquiry(?User $cs = null): Inquiry
    {
        $service = Service::create(['name'=>'Inbox regression', 'category'=>'Test', 'active'=>true]);
        return Inquiry::create(['service_id'=>$service->id, 'submitted_name'=>'Inbox '.uniqid(), 'requirement'=>'Discuss requirements', 'consent_at'=>now(), 'assigned_to'=>$cs?->id, 'status'=>$cs ? 'ditugaskan' : 'baru', 'intent'=>'consultation']);
    }

    public function test_cs_actions_match_detail_authorization_in_both_lists(): void
    {
        $cs = User::factory()->create(['role'=>'cs', 'active'=>true]);
        $other = User::factory()->create(['role'=>'cs', 'active'=>true]);
        $unassigned = $this->inquiry();
        $assigned = $this->inquiry($cs);
        $private = $this->inquiry($other);
        foreach (['inbox', 'inquiries.index'] as $route) {
            $response = $this->actingAs($cs)->get(route($route))->assertOk();
            $response->assertSee('href="'.route('inquiries.show', $unassigned).'"', false);
            $response->assertSee('href="'.route('inquiries.show', $assigned).'"', false);
            $response->assertDontSee($private->submitted_name);
        }
        $this->get(route('inquiries.show', $unassigned))->assertOk();
        $this->get(route('inquiries.show', $assigned))->assertOk();
        $this->get(route('inquiries.show', $private))->assertForbidden();
        $this->put(route('inquiries.assign', $unassigned), ['assigned_to'=>$cs->id])->assertForbidden();
        $this->post(route('inquiries.handling', $unassigned), ['action'=>'note', 'body'=>'CS took ownership', 'submission_id'=>(string) \Illuminate\Support\Str::uuid()])->assertRedirect();
        $this->assertSame($cs->id, $unassigned->fresh()->assigned_to);
        $this->get(route('inquiries.show', $unassigned))->assertOk();
    }

    public function test_managers_have_real_review_and_assignment_actions(): void
    {
        foreach (['owner', 'admin'] as $role) {
            $manager = User::factory()->create(['role'=>$role, 'active'=>true]);
            $cs = User::factory()->create(['role'=>'cs', 'active'=>true]);
            $inquiry = $this->inquiry();
            $this->actingAs($manager)->get(route('inbox'))->assertOk()
                ->assertSee('href="'.route('inquiries.show', $inquiry).'"', false)->assertSee('Tinjau &amp; tangani', false);
            $this->get(route('inquiries.show', $inquiry))->assertOk()->assertSee('action="'.route('inquiries.assign', $inquiry).'"', false);
            $this->put(route('inquiries.assign', $inquiry), ['assigned_to'=>$cs->id])->assertRedirect();
            $this->assertEquals($cs->id, $inquiry->fresh()->assigned_to);
        }
    }

    public function test_inbox_badge_ignores_resolved_complaints_and_counts_reopened_ticket(): void
    {
        $owner = User::factory()->create(['role'=>'owner', 'active'=>true]);
        $service = Service::create(['name'=>'Complaint badge', 'category'=>'Test', 'active'=>true]);
        $inquiry = Inquiry::create(['service_id'=>$service->id, 'submitted_name'=>'Badge complaint',
            'requirement'=>'Issue', 'consent_at'=>now(), 'status'=>'baru', 'intent'=>'complaint', 'source'=>'whatsapp']);
        $ticket = Ticket::create(['inquiry_id'=>$inquiry->id, 'subject'=>'Badge ticket', 'description'=>'Issue', 'status'=>'selesai']);

        $response = $this->actingAs($owner)->get(route('dashboard'))->assertOk();
        $response->assertDontSee('aria-label="1 item belum selesai"', false);

        $ticket->update(['status'=>'ditangani']);
        $response = $this->get(route('dashboard'))->assertOk();
        $response->assertSee('aria-label="1 item belum selesai"', false);
    }

    public function test_waiting_filter_pagination_and_notes_remain_functional(): void
    {
        $cs = User::factory()->create(['role'=>'cs', 'active'=>true]);
        $active = $this->inquiry($cs);
        $waiting = collect(range(1, 21))->map(function () use ($cs) {
            $inquiry = $this->inquiry($cs);
            $inquiry->update(['handling_status'=>'waiting']);
            return $inquiry;
        });
        $first = $this->actingAs($cs)->get(route('inbox', ['handling'=>'waiting']))->assertOk();
        $first->assertDontSee($active->submitted_name)->assertSee('handling=waiting', false)->assertSee('inquiries_page=2', false);
        $second = $this->get(route('inbox', ['handling'=>'waiting', 'inquiries_page'=>2]))->assertOk();
        $this->assertCount(20, $first->viewData('inquiries'));
        $this->assertCount(1, $second->viewData('inquiries'));
        $ids = $first->viewData('inquiries')->pluck('id')->merge($second->viewData('inquiries')->pluck('id'));
        $this->assertEqualsCanonicalizing($waiting->pluck('id')->all(), $ids->all());
        $this->post(route('inquiries.handling', $active), ['action'=>'note', 'body'=>'Follow-up recorded', 'submission_id'=>(string) \Illuminate\Support\Str::uuid()])->assertRedirect();
        $this->get(route('inquiries.show', $active))->assertOk()->assertSee('Follow-up recorded');
    }
}
