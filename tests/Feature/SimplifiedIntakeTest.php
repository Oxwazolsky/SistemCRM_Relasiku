<?php

namespace Tests\Feature;

use App\Models\{Inquiry, Service, Ticket, User, WhatsappBotConversation};
use App\Services\WhatsappConsultationBot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SimplifiedIntakeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! str_ends_with(DB::connection()->getDatabaseName(), '_test')) $this->markTestSkipped('Dedicated test database required.');
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if (DB::connection()->transactionLevel()) DB::rollBack();
        parent::tearDown();
    }

    public function test_complaint_creates_one_unlinked_ticket_only_after_consent(): void
    {
        Service::create(['name' => 'Test', 'category' => 'Test', 'active' => true]);
        $bot = app(WhatsappConsultationBot::class);
        $phone = '628'.random_int(100000000, 999999999);
        foreach (['MENU', '4', '1', 'Pelanggan', '-', '2026-10-28', '-', 'Keluhan pesanan saya'] as $text) $bot->receive($phone, (string) Str::uuid(), $text);
        $this->assertFalse(Inquiry::where('submitted_phone', $phone)->exists());
        $id = (string) Str::uuid();
        $reply = $bot->receive($phone, $id, 'SETUJU');
        $this->assertSame($reply, $bot->receive($phone, $id, 'SETUJU'));
        $inquiry = Inquiry::where('submitted_phone', $phone)->sole();
        $ticket = Ticket::where('inquiry_id', $inquiry->id)->sole();
        $this->assertNull($ticket->contact_id);
        $this->assertNull($ticket->order_id);
        $this->assertNull($ticket->assigned_to);
        $this->assertSame('Keluhan pesanan saya', $ticket->description);
        $this->assertSame('handoff', WhatsappBotConversation::where('phone', $phone)->firstOrFail()->step);
        $this->assertTrue(WhatsappBotConversation::where('phone', $phone)->firstOrFail()->handed_off);
    }

    public function test_verified_request_confirms_once_without_opportunity_form(): void
    {
        $cs = User::factory()->create(['role' => 'cs', 'active' => true]);
        $contact = \App\Models\Contact::create(['name' => 'Verified', 'owner_id' => $cs->id, 'stage' => 'lead']);
        $service = Service::create(['name' => 'Test', 'category' => 'Test', 'active' => true]);
        $inquiry = Inquiry::create(['contact_id' => $contact->id, 'assigned_to' => $cs->id, 'service_id' => $service->id,
            'requirement' => 'Print invitations', 'consent_at' => now(), 'verified_at' => now(), 'status' => 'terverifikasi', 'intent' => 'order']);
        $this->actingAs($cs);
        foreach ([1000, 2000] as $value) $this->post(route('inquiries.confirm-order', $inquiry), ['final_value' => $value, 'customer_confirmed' => 1])->assertRedirect();
        $order = \App\Models\Order::where('opportunity_id', $inquiry->fresh()->opportunity_id)->sole();
        $this->assertEquals(1000, $order->final_value);
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $this->actingAs($admin)->post(route('inquiries.confirm-order', $inquiry), ['final_value' => 1000, 'customer_confirmed' => 1])->assertForbidden();
    }

    public function test_complaint_queue_sources_nullable_customers_and_scoped_filters(): void
    {
        $cs = User::factory()->create(['role' => 'cs', 'active' => true]);
        $other = User::factory()->create(['role' => 'cs', 'active' => true]);
        $service = Service::create(['name' => 'Queue test', 'category' => 'Test', 'active' => true]);
        $inquiry = Inquiry::create(['service_id' => $service->id, 'submitted_name' => 'Unverified sender', 'submitted_phone' => '628123456789', 'requirement' => 'Intake complaint', 'source' => 'whatsapp', 'status' => 'baru', 'assigned_to' => $cs->id]);
        $auto = Ticket::create(['inquiry_id' => $inquiry->id, 'subject' => 'Automatic complaint', 'status' => 'baru', 'assigned_to' => $cs->id]);
        Ticket::create(['subject' => 'Manual complaint', 'status' => 'ditangani', 'assigned_to' => $cs->id]);
        $hidden = Ticket::create(['subject' => 'Hidden complaint', 'status' => 'baru', 'assigned_to' => $other->id]);
        $this->actingAs($cs)->get(route('tickets.index'))->assertOk()
            ->assertSee('Otomatis via permintaan')->assertSee('Manual / data lama')
            ->assertSee('Unverified sender')->assertSee('Pelanggan belum diketahui')
            ->assertSee('Catat keluhan manual')->assertDontSee('Hidden complaint')
            ->assertDontSee('href="'.route('tasks.index').'"', false)
            ->assertViewHas('statusCounts', fn ($counts) => (int) $counts['baru'] === 1 && (int) $counts['ditangani'] === 1);
        $this->get(route('tickets.index', ['status' => 'baru']))->assertOk()->assertSee('Automatic complaint')->assertDontSee('Manual complaint')->assertDontSee('Hidden complaint');
        $this->get(route('tickets.show', $auto))->assertOk()->assertSee('Identitas belum ditautkan')->assertSee('https://wa.me/628123456789');
        $this->get(route('tickets.show', $hidden))->assertForbidden();
        $this->put(route('tickets.update', $auto), ['status' => 'selesai'])->assertSessionHasErrors('status');
        $this->put(route('tickets.update', $auto), ['status' => 'ditangani'])->assertRedirect();
        $this->put(route('tickets.update', $auto), ['status' => 'selesai'])->assertSessionHasErrors('resolution');
        $this->put(route('tickets.update', $auto), ['status' => 'selesai', 'resolution' => 'Resolved'])->assertRedirect();
        $this->get(route('dashboard'))->assertOk()->assertDontSee('href="'.route('tasks.index').'"', false);
    }

    public function test_admin_cannot_confirm_orders_or_cs_manage_faqs(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $this->actingAs($admin)->get('/inbox')->assertOk();
        $cs = User::factory()->create(['role' => 'cs', 'active' => true]);
        $this->actingAs($cs)->get('/admin/faqs')->assertForbidden();
    }
}
