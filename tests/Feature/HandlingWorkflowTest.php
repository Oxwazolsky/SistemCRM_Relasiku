<?php

namespace Tests\Feature;

use App\Models\{HandlingNote, Inquiry, Service, Ticket, User, WhatsappBotConversation};
use App\Services\{HandlingWorkflow, WhatsappConsultationBot};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class HandlingWorkflowTest extends TestCase
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
    private function inquiry(User $cs, string $intent = 'consultation'): Inquiry
    {
        $service = Service::create(['name'=>'Handling test', 'category'=>'Test', 'active'=>true]);
        return Inquiry::create(['service_id'=>$service->id, 'submitted_name'=>'Handling customer', 'requirement'=>'Test request', 'consent_at'=>now(), 'assigned_to'=>$cs->id, 'status'=>'ditugaskan', 'intent'=>$intent]);
    }
    private function data(string $action = 'resolved'): array
    {
        return ['action'=>$action, 'body'=>'Customer received explanation', 'submission_id'=>(string) Str::uuid()];
    }
    public function test_closure_is_attributed_timestamped_idempotent_and_preserves_verification_status(): void
    {
        $cs = User::factory()->create(['role'=>'cs', 'active'=>true]);
        $inquiry = $this->inquiry($cs);
        $data = $this->data();
        $this->actingAs($cs)->post(route('inquiries.handling', $inquiry), $data)->assertRedirect();
        $this->post(route('inquiries.handling', $inquiry), $data)->assertRedirect();
        $note = HandlingNote::where('subject_id', $inquiry->id)->where('subject_type', Inquiry::class)->sole();
        $this->assertEquals($cs->id, $note->user_id);
        $this->assertNotNull($note->created_at);
        $this->assertSame('resolved', $inquiry->fresh()->handling_status);
        $this->assertSame('ditugaskan', $inquiry->fresh()->status);
        $this->get(route('inquiries.show', $inquiry))->assertOk()->assertSee($cs->name)->assertSee($note->body);
        $this->get(route('inbox'))->assertOk()->assertDontSee('Handling customer');
        $this->get(route('inbox', ['handling'=>'resolved']))->assertOk()->assertSee('Handling customer');
        $this->post(route('inquiries.handling', $inquiry), $this->data('waiting'))->assertSessionHasErrors('action');
    }
    public function test_other_cs_and_admin_cannot_resolve_and_note_is_required(): void
    {
        $cs = User::factory()->create(['role'=>'cs', 'active'=>true]);
        $inquiry = $this->inquiry($cs);
        foreach (['cs', 'admin'] as $role) {
            $other = User::factory()->create(['role'=>$role, 'active'=>true]);
            $this->actingAs($other)->post(route('inquiries.handling', $inquiry), $this->data())->assertForbidden();
        }
        $this->actingAs($cs)->post(route('inquiries.handling', $inquiry), array_replace($this->data(), ['body'=>'   ']))->assertSessionHasErrors('body');
        $this->assertSame('active', $inquiry->fresh()->handling_status);
        $owner = User::factory()->create(['role'=>'owner', 'active'=>true]);
        $this->actingAs($owner)->post(route('inquiries.handling', $inquiry), $this->data())->assertRedirect();
    }
    public function test_order_requests_cannot_fake_conversion_and_complaints_use_ticket_canonical_status(): void
    {
        $cs = User::factory()->create(['role'=>'cs', 'active'=>true]);
        $this->actingAs($cs);
        $order = $this->inquiry($cs, 'order');
        $this->post(route('inquiries.handling', $order), $this->data())->assertSessionHasErrors('action');
        $this->post(route('inquiries.handling', $order), $this->data('waiting'))->assertRedirect();
        $this->post(route('inquiries.handling', $order), $this->data('cancelled'))->assertRedirect();
        $complaint = $this->inquiry($cs, 'complaint');
        $ticket = Ticket::create(['inquiry_id'=>$complaint->id, 'assigned_to'=>$cs->id, 'subject'=>'Complaint', 'status'=>'ditangani']);
        $this->post(route('inquiries.handling', $complaint), $this->data())->assertSessionHasErrors('action');
        $this->post(route('inquiries.handling', $complaint), $this->data('note'))->assertRedirect();
        $this->put(route('tickets.update', $ticket), ['status'=>'selesai'])->assertSessionHasErrors('resolution');
        $this->put(route('tickets.update', $ticket), ['status'=>'selesai', 'resolution'=>'Fixed'])->assertRedirect();
        $this->assertSame('resolved', app(HandlingWorkflow::class)->state($complaint));
        $this->assertSame('active', $complaint->fresh()->handling_status);
    }
    public function test_direct_handoff_needs_no_inquiry_consent_and_menu_preserves_outcomes_without_reopening_old_inquiry(): void
    {
        $cs = User::factory()->create(['role'=>'cs', 'active'=>true]);
        $bot = app(WhatsappConsultationBot::class);
        $phone = '628'.random_int(100000000, 999999999);
        $bot->receive($phone, (string) Str::uuid(), 'CS');
        $conversation = WhatsappBotConversation::where('phone', $phone)->sole();
        $conversation->update(['assigned_to'=>$cs->id]);
        $this->assertNull($conversation->inquiry_id);
        $this->actingAs($cs)->get(route('inbox'))->assertOk()->assertSee('https://wa.me/'.$phone);
        $this->assertSame('active', $conversation->fresh()->handling_status);
        $this->post(route('whatsapp.handling', $conversation), $this->data())->assertRedirect();
        $this->assertSame('', $bot->receive($phone, (string) Str::uuid(), 'hello'));
        $bot->receive($phone, (string) Str::uuid(), 'MENU');
        $this->assertSame(1, HandlingNote::where('subject_type', WhatsappBotConversation::class)->where('subject_id', $conversation->id)->count());
        $old = $this->inquiry($cs);
        $old->update(['handling_status'=>'resolved']);
        $conversation->update(['inquiry_id'=>$old->id]);
        $bot->receive($phone, (string) Str::uuid(), 'CS');
        $this->assertNull($conversation->fresh()->inquiry_id);
        $this->assertSame('resolved', $old->fresh()->handling_status);
        $this->assertSame(1, HandlingNote::where('subject_type', WhatsappBotConversation::class)->where('subject_id', $conversation->id)->count());
    }
    public function test_converted_request_uses_order_as_canonical_and_requires_customer_confirmation(): void
    {
        $cs = User::factory()->create(['role'=>'cs', 'active'=>true]);
        $inquiry = $this->inquiry($cs, 'order');
        $contact = \App\Models\Contact::create(['name'=>'Verified', 'owner_id'=>$cs->id, 'stage'=>'lead']);
        $inquiry->update(['contact_id'=>$contact->id, 'status'=>'terverifikasi']);
        $this->actingAs($cs)->post(route('inquiries.confirm-order', $inquiry), ['final_value'=>100])->assertSessionHasErrors('customer_confirmed');
        $this->post(route('inquiries.confirm-order', $inquiry), ['final_value'=>100, 'customer_confirmed'=>1])->assertRedirect();
        $this->assertSame('converted', app(HandlingWorkflow::class)->state($inquiry->fresh()));
        $this->post(route('inquiries.handling', $inquiry), $this->data('cancelled'))->assertSessionHasErrors('action');
        $this->assertSame('baru', \App\Models\Order::where('opportunity_id', $inquiry->fresh()->opportunity_id)->sole()->status);
        $conversation = WhatsappBotConversation::create(['phone'=>'628'.random_int(100000000, 999999999), 'handed_off'=>true, 'assigned_to'=>$cs->id, 'inquiry_id'=>$inquiry->id]);
        $this->post(route('whatsapp.handling', $conversation), $this->data())->assertSessionHasErrors('action');
    }

    public function test_waiting_filter_and_counts_are_role_scoped(): void
    {
        $cs = User::factory()->create(['role'=>'cs', 'active'=>true]);
        $other = User::factory()->create(['role'=>'cs', 'active'=>true]);
        $mine = $this->inquiry($cs);
        $hidden = $this->inquiry($other);
        $hidden->update(['submitted_name'=>'Hidden outcome']);
        $this->actingAs($cs)->post(route('inquiries.handling', $mine), $this->data('waiting'))->assertRedirect();
        $this->get(route('inbox', ['handling'=>'waiting']))->assertOk()->assertSee('Handling customer')->assertDontSee('Hidden outcome')
            ->assertViewHas('handlingCounts', fn ($counts) => $counts['waiting'] === 1);
        $this->get(route('inquiries.show', $hidden))->assertForbidden();
    }
}
