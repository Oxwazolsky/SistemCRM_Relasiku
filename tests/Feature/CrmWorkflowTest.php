<?php

namespace Tests\Feature;

use App\Models\{Contact, Inquiry, Opportunity, Order, Service, Task, Ticket, User, WhatsappBotConversation};
use App\Services\{OpportunityConversion, WhatsappConsultationBot};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Run against a migrated, dedicated *_test database. Never transact against the local CRM database.
 * All fixtures and HTTP writes are rolled back after each test (no RefreshDatabase).
 */
class CrmWorkflowTest extends TestCase
{
    private bool $transactionStarted = false;

    protected function setUp(): void
    {
        parent::setUp();
        $connection = DB::connection();
        $database = (string) $connection->getDatabaseName();
        if (! preg_match('/(?:_test|_testing)$/i', $database) && ! ($connection->getDriverName() === 'sqlite' && $database === ':memory:')) {
            $this->markTestSkipped('CRM feature tests require a migrated dedicated *_test database; refusing to touch '.$database);
        }
        if (! Schema::hasTable('users') || ! Schema::hasTable('services') || ! Schema::hasTable('opportunities')
            || ! Schema::hasColumn('inquiries', 'submitted_name') || ! Schema::hasColumn('orders', 'cancellation_reason')
            || ! Schema::hasColumn('tasks', 'cancellation_reason') || ! Schema::hasColumn('whatsapp_bot_messages', 'delivery_status')) {
            $this->markTestSkipped('Dedicated test database must include the latest CRM migrations before running feature tests.');
        }
        $connection->beginTransaction();
        $this->transactionStarted = true;
    }

    protected function tearDown(): void
    {
        if ($this->transactionStarted) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function user(string $role = 'cs', bool $active = true): User
    {
        return User::create(['name' => 'Test '.$role, 'email' => Str::uuid().'@example.test', 'password' => 'test-password', 'role' => $role, 'active' => $active]);
    }

    private function service(bool $active = true): Service
    {
        return Service::create(['name' => 'Test service '.Str::uuid(), 'category' => 'event', 'active' => $active]);
    }

    private function contact(?User $owner = null, ?string $phone = null): Contact
    {
        return Contact::create(['name' => 'Test contact '.Str::uuid(), 'phone' => $phone ?? '0812'.random_int(10000000, 99999999), 'stage' => 'lead', 'owner_id' => $owner?->id]);
    }

    private function opportunity(Contact $contact, Service $service, User $owner, array $changes = []): Opportunity
    {
        return Opportunity::create(array_merge(['contact_id' => $contact->id, 'service_id' => $service->id, 'owner_id' => $owner->id, 'title' => 'Test opportunity '.Str::uuid(), 'stage' => 'baru'], $changes));
    }

    public function test_whatsapp_bot_collects_consent_and_creates_unverified_inquiry_only_once(): void
    {
        $service = $this->service();
        $bot = app(WhatsappConsultationBot::class);
        $phone = '62877'.random_int(100000000, 999999999);
        $menu = $bot->receive($phone, 'wamid-menu', 'MENU');
        $this->assertStringContainsString('Privasi Anda:', $menu);
        $this->assertStringContainsString('Jawaban konsultasi disimpan sebagai draf', $menu);
        $this->assertStringContainsString('baru dibuat setelah Anda membalas *SETUJU*', $menu);
        $this->assertStringContainsString('riwayat percakapan tetap tersimpan', $menu);
        $this->assertStringNotContainsString('CRM', $menu);
        $this->assertStringContainsString($service->name, $bot->receive($phone, 'wamid-choice', '1'));
        $beforeName = $bot->receive($phone, 'wamid-service', '1');
        $this->assertStringContainsString('draf percakapan', $beforeName);
        $this->assertStringContainsString('permintaan baru dibuat setelah Anda menyetujuinya', $beforeName);
        $this->assertStringContainsString('Siapa nama', $beforeName);
        $this->assertStringContainsString('email', $bot->receive($phone, 'wamid-name', 'Calon Pelanggan'));
        $this->assertStringContainsString('belum sesuai format', $bot->receive($phone, 'wamid-bad-email', 'bukan-email'));
        $this->assertStringContainsString('tanggal', $bot->receive($phone, 'wamid-email', 'calon@example.test'));
        $this->assertStringContainsString('belum sesuai', $bot->receive($phone, 'wamid-bad-date', '2026-99-99'));
        $bot->receive($phone, 'wamid-date', '2027-12-01');
        $bot->receive($phone, 'wamid-requirement', 'Butuh undangan acara keluarga');
        $this->assertSame(0, Inquiry::where('submitted_phone', $phone)->count());
        $this->assertStringContainsString('SETUJU', $bot->receive($phone, 'wamid-unknown', 'ya'));
        $reply = $bot->receive($phone, 'wamid-consent', 'SETUJU');
        $this->assertStringContainsString('berhasil dicatat', $reply);
        $this->assertSame($reply, $bot->receive($phone, 'wamid-consent', 'SETUJU'));
        $inquiry = Inquiry::where('submitted_phone', $phone)->sole();
        $this->assertSame('whatsapp', $inquiry->source);
        $this->assertSame('calon@example.test', $inquiry->submitted_email);
        $this->assertSame('baru', $inquiry->status);
        $this->assertNull($inquiry->contact_id);
        $this->assertNotNull($inquiry->consent_at);
        $this->assertSame(0, Contact::where('phone', $phone)->count());
        $this->assertSame(1, Inquiry::where('submitted_phone', $phone)->count());
        $this->assertSame('', $bot->receive($phone, 'wamid-after', 'halo'));
        $this->assertStringContainsString('selamat datang', $bot->receive($phone, 'wamid-resume', 'MENU'));
    }

    public function test_whatsapp_bot_handoff_and_refusal_do_not_create_inquiry(): void
    {
        $this->service();
        $bot = app(WhatsappConsultationBot::class);
        $phone = '62899'.random_int(100000000, 999999999);
        $bot->receive($phone, 'handoff-menu', 'MENU');
        $this->assertStringContainsString('Permintaan bantuan sudah diterima', $bot->receive($phone, 'handoff-cs', 'CS'));
        $this->assertSame('', $bot->receive($phone, 'handoff-followup', 'Saya tunggu CS'));
        $this->assertSame('', $bot->receive($phone, 'handoff-repeat', 'CS'));
        $bot->receive($phone, 'refuse-menu', 'MENU');
        $bot->receive($phone, 'refuse-choice', '1');
        $bot->receive($phone, 'refuse-service', '1');
        $bot->receive($phone, 'refuse-name', 'Tidak Setuju');
        $bot->receive($phone, 'refuse-email', '-');
        $bot->receive($phone, 'refuse-date', '-');
        $bot->receive($phone, 'refuse-need', 'Cetak undangan acara');
        $bot->receive($phone, 'refuse-no', 'TIDAK');
        $this->assertSame(0, Inquiry::where('submitted_phone', $phone)->count());
        $this->assertSame(0, Ticket::whereHas('inquiry', fn ($query) => $query->where('submitted_phone', $phone))->count());
        $conversation = WhatsappBotConversation::where('phone', $phone)->firstOrFail();
        $this->assertSame('menu', $conversation->step);
        $this->assertSame([], $conversation->answers);
    }

    public function test_message_id_replay_is_sender_scoped_and_does_not_leak_reply_or_mutate_other_sender(): void
    {
        $bot = app(WhatsappConsultationBot::class);
        $senderA = '62877'.random_int(100000000, 999999999);
        $senderB = '62888'.random_int(100000000, 999999999);
        $replyA = $bot->receive($senderA, 'shared-provider-id', 'MENU');
        try {
            $bot->receive($senderB, 'shared-provider-id', 'MENU');
            $this->fail('A provider-global message ID reused by another sender must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('Message ID belongs to a different sender.', $exception->getMessage());
        }
        $this->assertSame('menu', WhatsappBotConversation::where('phone', $senderB)->value('step') ?? 'menu');
        $this->assertSame($replyA, $bot->receive($senderA, 'shared-provider-id', 'different payload'));
        $this->assertSame(1, \App\Models\WhatsappBotMessage::where('message_id', 'shared-provider-id')->count());
    }

    public function test_removed_bot_simulator_route_is_unavailable(): void
    {
        $this->actingAs($this->user('owner'))->get('/whatsapp/demo')->assertNotFound();
        $this->post('/whatsapp/demo', ['message' => 'MENU'])->assertNotFound();
    }

    public function test_owner_assigns_whatsapp_handoff_and_only_assigned_cs_sees_it(): void
    {
        $bot = app(WhatsappConsultationBot::class);
        $phone = '62888'.random_int(100000000, 999999999);
        $bot->receive($phone, 'handoff-'.(string) \Illuminate\Support\Str::uuid(), 'CS');
        $conversation = WhatsappBotConversation::where('phone', $phone)->firstOrFail();
        $cs = $this->user();
        $other = $this->user();
        $this->actingAs($cs)->get(route('whatsapp.handoffs'))->assertOk()->assertSee($phone);
        $this->post(route('whatsapp.handling', $conversation), ['action' => 'note', 'body' => 'Direct CS handoff', 'submission_id' => (string) \Illuminate\Support\Str::uuid()])->assertRedirect();
        $this->assertSame($cs->id, $conversation->fresh()->assigned_to);
        $this->put(route('whatsapp.handoffs.assign', $conversation), ['assigned_to' => $cs->id])->assertForbidden();
        $this->actingAs($this->user('admin'))->get(route('whatsapp.handoffs'))->assertForbidden();
        $this->actingAs($this->user('owner'))->get(route('whatsapp.handoffs'))->assertOk()->assertSee($phone);
        $this->put(route('whatsapp.handoffs.assign', $conversation), ['assigned_to' => $cs->id])->assertRedirect();
        $this->actingAs($cs)->get(route('whatsapp.handoffs'))->assertSee($phone);
        $this->actingAs($other)->get(route('whatsapp.handoffs'))->assertDontSee($phone);
    }

    public function test_menu_resumes_bot_without_losing_handed_off_inquiry_or_ticket(): void
    {
        $cs = $this->user();
        $service = $this->service();
        $phone = '62877'.random_int(100000000, 999999999);
        $inquiry = Inquiry::create(['submitted_name' => 'Handed off customer', 'submitted_phone' => $phone,
            'service_id' => $service->id, 'requirement' => 'Existing complaint', 'intent' => 'complaint',
            'status' => 'ditugaskan', 'source' => 'whatsapp', 'consent_at' => now(), 'assigned_to' => $cs->id]);
        $ticket = Ticket::create(['inquiry_id' => $inquiry->id, 'assigned_to' => $cs->id,
            'subject' => 'Existing complaint', 'status' => 'baru']);
        $conversation = WhatsappBotConversation::create(['phone' => $phone, 'step' => 'handoff',
            'answers' => [], 'handed_off' => true, 'inquiry_id' => $inquiry->id, 'assigned_to' => $cs->id]);
        $bot = app(WhatsappConsultationBot::class);

        $this->assertStringContainsString('selamat datang', $bot->receive($phone, 'resume-menu', 'MENU'));
        $conversation->refresh();
        $this->assertSame($inquiry->id, $conversation->inquiry_id);
        $this->assertSame($cs->id, $conversation->assigned_to);
        $this->assertFalse($conversation->handed_off);
        $this->assertDatabaseHas('inquiries', ['id' => $inquiry->id, 'assigned_to' => $cs->id]);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'inquiry_id' => $inquiry->id, 'assigned_to' => $cs->id]);
        $this->actingAs($cs)->get(route('inquiries.show', $inquiry))->assertOk();
        $this->get(route('tickets.show', $ticket))->assertOk();
        $this->assertFalse(WhatsappBotConversation::where('handed_off', true)->whereKey($conversation->id)->exists());
        $this->assertStringContainsString('selamat datang', $bot->receive($phone, 'new-request-menu', 'MENU'));
        $this->assertStringContainsString($service->name, $bot->receive($phone, 'new-request-choice', '1'));
        $this->assertSame(1, Inquiry::where('submitted_phone', $phone)->count());
    }

    public function test_public_website_discloses_session_draft_and_decline_clears_it_without_inquiry(): void
    {
        $service = $this->service();
        $this->get(route('consultation.show'))->assertOk()
            ->assertSee('disimpan sebagai draf dalam sesi situs untuk melanjutkan konsultasi')
            ->assertSee('inquiry CRM baru dibuat setelah persetujuan eksplisit');
        $this->post(route('consultation.submit'), ['action' => 'start'])->assertRedirect();
        $this->post(route('consultation.submit'), ['service_id' => $service->id])->assertRedirect();
        $this->post(route('consultation.submit'), ['name' => 'Draft Website'])->assertRedirect();
        $this->post(route('consultation.submit'), ['phone' => '081234567890'])->assertRedirect();
        $this->post(route('consultation.submit'), ['action' => 'decline'])->assertRedirect();
        $this->get(route('consultation.show'))->assertSee('Layanan yang dibutuhkan')->assertDontSee('Draft Website');
        $this->assertSame(0, Inquiry::where('submitted_phone', '081234567890')->count());
    }

    public function test_public_website_consultation_requires_consent_and_creates_one_unverified_inquiry(): void
    {
        $service = $this->service();
        $phone = '0812'.random_int(10000000, 99999999);
        $this->get(route('consultation.show'))->assertOk()->assertSee('Layanan yang dibutuhkan');
        $this->post(route('consultation.submit'), ['action' => 'faq'])->assertRedirect();
        $this->get(route('consultation.show'))->assertSee('Berapa harga?');
        $this->post(route('consultation.submit'), ['action' => 'start'])->assertRedirect();
        $this->post(route('consultation.submit'), ['service_id' => $service->id])->assertRedirect();
        $this->post(route('consultation.submit'), ['name' => 'Calon Website'])->assertRedirect();
        $this->post(route('consultation.submit'), ['phone' => 'nomor-salah'])->assertSessionHasErrors('phone');
        $this->post(route('consultation.submit'), ['phone' => $phone])->assertRedirect();
        $this->post(route('consultation.submit'), ['event_date' => '2027-12-01'])->assertRedirect();
        $this->post(route('consultation.submit'), ['requirement' => 'Butuh 150 sertifikat'])->assertRedirect();
        $this->assertSame(0, Inquiry::where('submitted_phone', $phone)->count());
        $this->post(route('consultation.submit'), [])->assertSessionHasErrors('consent');
        $this->post(route('consultation.submit'), ['consent' => '1', 'website' => 'spam'])->assertSessionHasErrors('website');
        $this->post(route('consultation.submit'), ['consent' => '1'])->assertRedirect(route('consultation.show'));
        $this->post(route('consultation.submit'), ['consent' => '1'])->assertRedirect();
        $inquiry = Inquiry::where('submitted_phone', $phone)->sole();
        $this->assertSame('website_bot', $inquiry->source);
        $this->assertSame('baru', $inquiry->status);
        $this->assertSame('Calon Website', $inquiry->submitted_name);
        $this->assertSame('Butuh 150 sertifikat', $inquiry->requirement);
        $this->assertNull($inquiry->contact_id);
        $this->assertNotNull($inquiry->consent_at);
        $this->assertSame(1, Inquiry::where('submitted_phone', $phone)->count());
        $this->assertSame(0, Contact::where('phone', $phone)->count());
        $this->get(route('consultation.show'))->assertSee('sudah dicatat');
        $this->actingAs($this->user('owner'))->get(route('inquiries.index'))->assertViewHas('records', fn ($records) => $records->pluck('id')->contains($inquiry->id));
    }

    public function test_website_inquiry_is_visible_in_inbox_and_assignable_by_owner_and_admin(): void
    {
        $owner = $this->user('owner');
        $admin = $this->user('admin');
        $cs = $this->user();
        $other = $this->user();
        $inquiry = Inquiry::create(['submitted_name' => 'Website inbox request', 'submitted_phone' => '081299988877',
            'service_id' => $this->service()->id, 'requirement' => 'Separate website intake', 'source' => 'website_bot',
            'status' => 'baru', 'consent_at' => now()]);

        $this->actingAs($owner)->get(route('inbox'))->assertOk()
            ->assertViewHas('inquiries', fn ($records) => $records->pluck('id')->contains($inquiry->id));
        $this->get(route('inquiries.index'))->assertViewHas('records', fn ($records) => $records->pluck('id')->contains($inquiry->id));
        $this->actingAs($cs)->get(route('inquiries.index'))->assertOk()
            ->assertViewHas('records', fn ($records) => $records->pluck('id')->contains($inquiry->id));
        $this->put(route('inquiries.assign', $inquiry), ['assigned_to' => $cs->id])->assertForbidden();

        $this->actingAs($owner)->put(route('inquiries.assign', $inquiry), ['assigned_to' => $cs->id])->assertRedirect();
        $this->assertSame($cs->id, $inquiry->fresh()->assigned_to);
        $this->actingAs($cs)->get(route('inquiries.index'))->assertViewHas('records', fn ($records) => $records->pluck('id')->contains($inquiry->id));
        $this->get(route('inquiries.show', $inquiry))->assertOk();
        $this->actingAs($admin)->get(route('inquiries.show', $inquiry))->assertOk();
        $this->put(route('inquiries.assign', $inquiry), ['assigned_to' => $other->id])->assertRedirect();
        $this->actingAs($cs)->get(route('inquiries.show', $inquiry))->assertForbidden();
        $this->actingAs($other)->get(route('inquiries.show', $inquiry))->assertOk();
    }

    public function test_public_website_consultation_rejects_inactive_service_and_restart_does_not_save(): void
    {
        $service = $this->service();
        $inactive = $this->service(false);
        $this->post(route('consultation.submit'), ['name' => 'Skipping'])->assertSessionHasErrors('service_id');
        $this->post(route('consultation.submit'), ['service_id' => $inactive->id])->assertSessionHasErrors('service_id');
        $this->post(route('consultation.submit'), ['service_id' => $service->id])->assertRedirect();
        $this->post(route('consultation.submit'), ['action' => 'restart'])->assertRedirect();
        $this->get(route('consultation.show'))->assertSee('Layanan yang dibutuhkan');
        $this->assertSame(0, Inquiry::where('source', 'website_bot')->count());
    }

    public function test_public_inquiry_requires_consent_and_active_service_without_creating_a_contact(): void
    {
        $service = $this->service();
        $payload = ['name' => 'Public requester', 'phone' => '081234567890', 'service_id' => $service->id, 'requirement' => 'An event', 'event_date' => '2027-03-01', 'consent' => '1'];
        $this->post(route('inquiry.store'), array_diff_key($payload, ['consent' => true]))->assertSessionHasErrors('consent');
        $inactive = $this->service(false);
        $this->post(route('inquiry.store'), array_replace($payload, ['service_id' => $inactive->id]))->assertStatus(422);
        $this->assertSame(0, Inquiry::where('requirement', 'An event')->count());

        $this->post(route('inquiry.store'), $payload)->assertRedirect(route('inquiry.create'));
        $inquiry = Inquiry::where('requirement', 'An event')->firstOrFail();
        $this->assertSame($service->id, $inquiry->service_id);
        $this->assertNotNull($inquiry->consent_at);
        $this->assertNull($inquiry->contact_id);
        $this->assertSame('Public requester', $inquiry->submitted_name);
        $this->assertSame('081234567890', $inquiry->submitted_phone);
        $this->assertSame('baru', $inquiry->status);
        $this->assertSame(0, Contact::where('phone', '081234567890')->count());
    }

    public function test_json_inquiry_stays_on_page_with_success_and_validation_errors(): void
    {
        $service = $this->service();
        $payload = ['name' => 'Ajax customer', 'phone' => '087812345678', 'service_id' => $service->id,
            'requirement' => 'Butuh banner untuk acara', 'consent' => '1'];

        $this->postJson(route('inquiry.store'), array_diff_key($payload, ['consent' => true]))
            ->assertUnprocessable()->assertJsonValidationErrors('consent');
        $this->assertSame(0, Inquiry::where('requirement', $payload['requirement'])->count());

        $this->postJson(route('inquiry.store'), $payload)
            ->assertCreated()->assertJsonPath('message', 'Permintaan telah diterima. Tim kami akan segera menghubungi Anda.');
        $this->assertSame(1, Inquiry::where('requirement', $payload['requirement'])->count());
    }

    public function test_duplicate_phone_inquiries_remain_distinct_and_can_be_filtered(): void
    {
        $service = $this->service();
        $phone = '0899'.random_int(10000000, 99999999);
        $existing = $this->contact(null, $phone);
        foreach (['First request', 'Second request'] as $name) {
            $this->post(route('inquiry.store'), ['name' => $name, 'phone' => $phone, 'service_id' => $service->id, 'requirement' => $name, 'consent' => 'on'])->assertRedirect();
        }
        $this->assertSame(1, Contact::where('phone', $phone)->count());
        $this->assertSame(2, Inquiry::where('submitted_phone', $phone)->whereNull('contact_id')->count());
        $this->assertSame('lead', $existing->fresh()->stage);
        $this->actingAs($this->user('owner'))->get(route('inquiries.index', ['phone' => $phone]))
            ->assertOk()->assertSee('First request')->assertSee('Second request');
    }

    public function test_roles_and_record_ownership_apply_to_lists_details_writes_and_references(): void
    {
        $cs = $this->user();
        $other = $this->user();
        $owner = $this->user('owner');
        $admin = $this->user('admin');
        $inactive = $this->user('cs', false);
        $mine = $this->contact($cs);
        $theirs = $this->contact($other);
        $service = $this->service();
        $this->actingAs($cs)->get(route('contacts.index'))->assertOk()->assertSee($mine->name)->assertDontSee($theirs->name);
        $this->get(route('contacts.show', $theirs))->assertForbidden();
        $this->put(route('contacts.update', $theirs), ['name' => 'Hijacked'])->assertForbidden();
        $this->post(route('opportunities.store'), ['contact_id' => $theirs->id, 'service_id' => $service->id, 'title' => 'Foreign lead'])->assertForbidden();
        $this->assertSame($theirs->name, $theirs->fresh()->name);
        $this->actingAs($owner)->get(route('contacts.show', $theirs))->assertOk();
        $this->get(route('reports.index'))->assertOk();
        $this->actingAs($admin)->get(route('contacts.show', $theirs))->assertOk();
        $this->get(route('contacts.create'))->assertForbidden();
        $this->put(route('contacts.update', $theirs), ['name' => 'Hijacked'])->assertForbidden();
        $this->get(route('reports.index'))->assertForbidden();
        $this->actingAs($inactive)->get(route('dashboard'))->assertForbidden();
    }

    public function test_owner_transfer_moves_open_work_of_former_owner_and_preserves_helpers_and_history(): void
    {
        $old = $this->user();
        $new = $this->user();
        $helper = $this->user();
        $owner = $this->user('owner');
        $contact = $this->contact($old);
        $service = $this->service();
        $open = $this->opportunity($contact, $service, $old);
        $closed = $this->opportunity($contact, $service, $old, ['stage' => 'gagal']);
        $inquiry = Inquiry::create(['contact_id' => $contact->id, 'assigned_to' => $old->id,
            'submitted_name' => 'Transfer inquiry', 'submitted_phone' => $contact->phone,
            'service_id' => $service->id, 'requirement' => 'Test', 'status' => 'terverifikasi',
            'source' => 'langsung', 'consent_at' => now()]);
        $task = Task::create(['opportunity_id' => $open->id, 'assigned_to' => $old->id,
            'due_at' => now()->addDay(), 'status' => 'belum_dikerjakan']);
        $helperTask = Task::create(['opportunity_id' => $open->id, 'assigned_to' => $helper->id,
            'due_at' => now()->addDay(), 'status' => 'belum_dikerjakan']);
        $done = Task::create(['opportunity_id' => $open->id, 'assigned_to' => $old->id,
            'due_at' => now()->subDay(), 'status' => 'selesai']);
        $ticket = Ticket::create(['contact_id' => $contact->id, 'assigned_to' => $old->id, 'subject' => 'Open', 'status' => 'ditangani']);
        $helperTicket = Ticket::create(['contact_id' => $contact->id, 'assigned_to' => $helper->id, 'subject' => 'Helper', 'status' => 'baru']);
        $finishedTicket = Ticket::create(['contact_id' => $contact->id, 'assigned_to' => $old->id, 'subject' => 'Done', 'status' => 'selesai']);
        $this->actingAs($old)->get(route('contacts.transfer.edit', $contact))->assertForbidden();
        $this->put(route('contacts.transfer.update', $contact), ['owner_id' => $new->id, 'current_owner_id' => $old->id, 'confirm_transfer' => '1'])->assertForbidden();
        $this->actingAs($this->user('admin'))->get(route('contacts.transfer.edit', $contact))->assertForbidden();
        $this->actingAs($owner)->get(route('contacts.show', $contact))->assertSee(route('contacts.transfer.edit', $contact));
        $this->get(route('contacts.transfer.edit', $contact))->assertOk()->assertSee('CS pengganti');
        $token = $this->get(route('contacts.transfer.edit', $contact))->viewData('previewToken');
        $payload = ['owner_id' => $new->id, 'preview_token' => $token, 'confirm_transfer' => '1'];
        $this->put(route('contacts.update', $contact), ['owner_id' => $new->id])->assertSessionHasErrors('owner_id');
        $this->put(route('opportunities.update', $open), ['owner_id' => $new->id])->assertSessionHasErrors('owner_id');
        $this->put(route('contacts.transfer.update', $contact), ['owner_id' => $new->id, 'preview_token' => $token])->assertSessionHasErrors('confirm_transfer');
        $this->put(route('contacts.transfer.update', $contact), ['owner_id' => $owner->id, 'preview_token' => $token, 'confirm_transfer' => '1'])->assertSessionHasErrors('owner_id');
        $this->assertEquals($old->id, $contact->fresh()->owner_id);
        $this->put(route('contacts.transfer.update', $contact), $payload)->assertRedirect(route('contacts.show', $contact));
        $this->assertEquals($new->id, $contact->fresh()->owner_id);
        $this->assertEquals($new->id, $inquiry->fresh()->assigned_to);
        $this->assertEquals($new->id, $open->fresh()->owner_id);
        $this->assertEquals($old->id, $closed->fresh()->owner_id);
        $this->assertEquals($new->id, $task->fresh()->assigned_to);
        $this->assertEquals($helper->id, $helperTask->fresh()->assigned_to);
        $this->assertEquals($old->id, $done->fresh()->assigned_to);
        $this->assertEquals($new->id, $ticket->fresh()->assigned_to);
        $this->assertEquals($helper->id, $helperTicket->fresh()->assigned_to);
        $this->assertEquals($old->id, $finishedTicket->fresh()->assigned_to);
        $this->assertDatabaseHas('activity_logs', ['entity_type' => Contact::class, 'entity_id' => $contact->id, 'action' => 'owner_transferred', 'actor_id' => $owner->id]);
        $this->actingAs($old)->get(route('contacts.show', $contact))->assertForbidden();
        $this->get(route('inquiries.show', $inquiry))->assertForbidden();
        $this->actingAs($new)->get(route('contacts.show', $contact))->assertOk();
        $this->get(route('opportunities.show', $closed))->assertOk();
        $this->get(route('inquiries.index'))->assertViewHas('records', fn ($records) => $records->pluck('id')->contains($inquiry->id));
        $this->get(route('opportunities.index'))->assertViewHas('records', fn ($records) => $records->pluck('id')->contains($closed->id));
        $this->get(route('tasks.show', $task))->assertOk();
        $this->actingAs($helper)->get(route('tasks.show', $helperTask))->assertOk()->assertSee('Konteks pekerjaan petugas bantu')->assertSee($contact->phone);
        $this->get(route('tickets.show', $helperTicket))->assertOk()->assertSee('Konteks pekerjaan petugas bantu')->assertSee($contact->phone);
        $this->get(route('contacts.show', $contact))->assertForbidden();
        $this->get(route('opportunities.show', $open))->assertForbidden();
        $this->get(route('inquiries.show', $inquiry))->assertForbidden();
        $this->put(route('tasks.update', $helperTask), ['status' => 'selesai'])->assertRedirect();
        $this->put(route('tickets.update', $helperTicket), ['status' => 'ditangani'])->assertRedirect();
        $this->assertSame('selesai', $helperTask->fresh()->status);
        $this->assertSame('ditangani', $helperTicket->fresh()->status);
        $this->actingAs($owner)->put(route('contacts.transfer.update', $contact), $payload)->assertSessionHasErrors('preview');
    }

    public function test_transfer_rejects_changed_work_preview_without_partial_updates(): void
    {
        $old = $this->user();
        $new = $this->user();
        $contact = $this->contact($old);
        $opportunity = $this->opportunity($contact, $this->service(), $old);
        $this->actingAs($this->user('owner'));
        $token = $this->get(route('contacts.transfer.edit', $contact))->viewData('previewToken');
        $task = Task::create(['opportunity_id' => $opportunity->id, 'assigned_to' => $old->id,
            'due_at' => now()->addDay(), 'status' => 'belum_dikerjakan']);
        $this->put(route('contacts.transfer.update', $contact), ['owner_id' => $new->id,
            'preview_token' => $token, 'confirm_transfer' => '1'])->assertSessionHasErrors('preview');
        $this->assertEquals($old->id, $contact->fresh()->owner_id);
        $this->assertEquals($old->id, $task->fresh()->assigned_to);
        $freshToken = $this->get(route('contacts.transfer.edit', $contact))->viewData('previewToken');
        $this->put(route('contacts.transfer.update', $contact), ['owner_id' => $new->id,
            'preview_token' => $freshToken, 'confirm_transfer' => '1'])->assertRedirect(route('contacts.show', $contact));
        $this->assertEquals($new->id, $task->fresh()->assigned_to);
        $this->put(route('contacts.transfer.update', $contact), ['owner_id' => $old->id,
            'preview_token' => 'invalid', 'confirm_transfer' => '1'])->assertSessionHasErrors('preview');
    }

    public function test_ownership_audit_reports_legacy_mismatches_without_changing_records(): void
    {
        $old = $this->user();
        $new = $this->user();
        $contact = $this->contact($new);
        $service = $this->service();
        $inquiry = Inquiry::create(['contact_id' => $contact->id, 'assigned_to' => $old->id,
            'submitted_name' => 'Legacy', 'submitted_phone' => $contact->phone,
            'service_id' => $service->id, 'requirement' => 'Test', 'status' => 'terverifikasi',
            'source' => 'langsung', 'consent_at' => now()]);
        $opportunity = $this->opportunity($contact, $service, $old);
        $closed = $this->opportunity($contact, $service, $old, ['stage' => 'gagal']);
        $task = Task::create(['opportunity_id' => $closed->id, 'assigned_to' => $old->id,
            'due_at' => now()->subDay(), 'status' => 'belum_dikerjakan']);
        $this->artisan('crm:audit-ownership')->assertExitCode(0);
        $this->assertEquals($old->id, $inquiry->fresh()->assigned_to);
        $this->assertEquals($old->id, $opportunity->fresh()->owner_id);
        $this->assertSame('belum_dikerjakan', $task->fresh()->status);
    }

    public function test_winning_an_opportunity_creates_one_order_and_promotes_contact_once(): void
    {
        $owner = $this->user('owner');
        $cs = $this->user();
        $contact = $this->contact($cs);
        $opportunity = $this->opportunity($contact, $this->service(), $cs);
        $url = route('opportunities.update', $opportunity);
        $this->actingAs($owner)->put($url, ['stage' => 'berhasil', 'final_value' => 250000])->assertRedirect();
        $order = Order::where('opportunity_id', $opportunity->id)->firstOrFail();
        $this->assertSame('berhasil', $opportunity->fresh()->stage);
        $this->assertNotNull($opportunity->fresh()->closed_at);
        $this->assertSame('pelanggan', $contact->fresh()->stage);
        $this->assertEquals(250000, $order->final_value);
        $this->assertSame($contact->id, $order->contact_id);
        $this->assertSame($order->id, app(OpportunityConversion::class)->convert($opportunity->fresh(), 999999)->id);
        $this->assertSame(1, Order::where('opportunity_id', $opportunity->id)->count());
        $this->assertEquals(250000, $order->fresh()->final_value);
        $this->put($url, ['stage' => 'gagal', 'lost_reason' => 'Tidak jadi'])->assertSessionHasErrors('stage');
        $this->assertSame(1, Order::where('opportunity_id', $opportunity->id)->count());
    }

    public function test_winning_cancels_only_pending_sales_tasks_and_keeps_completion_history(): void
    {
        $cs = $this->user();
        $opportunity = $this->opportunity($this->contact($cs), $this->service(), $cs);
        $pending = Task::create(['opportunity_id' => $opportunity->id, 'assigned_to' => $cs->id,
            'due_at' => now()->subDay(), 'status' => 'belum_dikerjakan', 'note' => 'Tindak lanjut terlambat']);
        $completed = Task::create(['opportunity_id' => $opportunity->id, 'assigned_to' => $cs->id,
            'due_at' => now()->subDays(2), 'status' => 'selesai']);
        $this->actingAs($cs)->put(route('opportunities.update', $opportunity),
            ['stage' => 'berhasil', 'final_value' => 250000])->assertRedirect();
        $this->assertSame('dibatalkan', $pending->fresh()->status);
        $this->assertNotNull($pending->fresh()->cancelled_at);
        $this->assertStringContainsString('berhasil', $pending->fresh()->cancellation_reason);
        $this->assertSame('selesai', $completed->fresh()->status);
        $this->get(route('tasks.show', $pending))->assertOk()->assertSee('Alasan tugas dibatalkan')
            ->assertSee('Dibatalkan pada')->assertSee($pending->fresh()->cancellation_reason);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['overdue_tasks'] === 0);
        $count = \App\Models\ActivityLog::where('entity_type', Task::class)->where('entity_id', $pending->id)
            ->where('action', 'cancelled_on_opportunity_close')->count();
        $this->assertSame(1, $count);
        $this->assertSame(1, Order::where('opportunity_id', $opportunity->id)->count());
        app(OpportunityConversion::class)->convert($opportunity->fresh(), 999999);
        $this->assertSame($count, \App\Models\ActivityLog::where('entity_type', Task::class)
            ->where('entity_id', $pending->id)->where('action', 'cancelled_on_opportunity_close')->count());
    }

    public function test_losing_cancels_pending_tasks_but_does_not_create_order(): void
    {
        $cs = $this->user();
        $opportunity = $this->opportunity($this->contact($cs), $this->service(), $cs);
        $pending = Task::create(['opportunity_id' => $opportunity->id, 'assigned_to' => $cs->id,
            'due_at' => now()->subDay(), 'status' => 'belum_dikerjakan']);
        $done = Task::create(['opportunity_id' => $opportunity->id, 'assigned_to' => $cs->id,
            'due_at' => now()->subDays(2), 'status' => 'selesai']);
        $this->actingAs($cs)->put(route('opportunities.update', $opportunity),
            ['stage' => 'gagal', 'lost_reason' => 'Pelanggan membatalkan'])->assertRedirect();
        $this->assertSame('gagal', $opportunity->fresh()->stage);
        $this->assertSame('dibatalkan', $pending->fresh()->status);
        $this->assertStringContainsString('gagal', $pending->fresh()->cancellation_reason);
        $this->assertSame('selesai', $done->fresh()->status);
        $this->assertSame(0, Order::where('opportunity_id', $opportunity->id)->count());
        $this->get(route('dashboard'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['overdue_tasks'] === 0);
    }

    public function test_dashboard_prioritizes_pending_overdue_tasks_and_excludes_completed_and_future_tasks(): void
    {
        $cs = $this->user();
        $opportunity = $this->opportunity($this->contact($cs), $this->service(), $cs);
        $overdue = Task::create(['opportunity_id' => $opportunity->id, 'assigned_to' => $cs->id, 'due_at' => now()->subDays(2), 'note' => 'Urgent follow up', 'status' => 'belum_dikerjakan']);
        Task::create(['opportunity_id' => $opportunity->id, 'assigned_to' => $cs->id, 'due_at' => now()->subDay(), 'note' => 'Already done', 'status' => 'selesai']);
        Task::create(['opportunity_id' => $opportunity->id, 'assigned_to' => $cs->id, 'due_at' => now()->addWeek(), 'note' => 'Next week', 'status' => 'belum_dikerjakan']);
        $this->actingAs($cs)->get(route('dashboard'))->assertOk()->assertViewHas('tasks', function ($tasks) use ($overdue) {
            return $tasks->pluck('id')->all() === [$overdue->id] && $tasks->first()->priority_reason === 'Terlambat 2 hari';
        })->assertViewHas('stats', fn ($stats) => $stats['overdue_tasks'] === 1);
    }

    public function test_dashboard_labels_tasks_overdue_by_less_than_one_day(): void
    {
        $cs = $this->user();
        $opportunity = $this->opportunity($this->contact($cs), $this->service(), $cs);
        Task::create(['opportunity_id' => $opportunity->id, 'assigned_to' => $cs->id,
            'due_at' => now()->subHours(2), 'status' => 'belum_dikerjakan']);

        $this->actingAs($cs)->get(route('dashboard'))->assertOk()->assertViewHas('tasks',
            fn ($tasks) => $tasks->first()->priority_reason === 'Terlambat kurang dari 1 hari');
    }

    public function test_cs_can_claim_unassigned_whatsapp_complaint_without_verifying_identity(): void
    {
        $cs = $this->user();
        $service = $this->service();
        $inquiry = Inquiry::create(['submitted_name' => 'Unverified sender', 'submitted_phone' => '628123456789',
            'service_id' => $service->id, 'requirement' => 'Complaint detail', 'intent' => 'complaint',
            'status' => 'baru', 'source' => 'whatsapp', 'consent_at' => now()]);
        $ticket = Ticket::create(['inquiry_id' => $inquiry->id, 'subject' => 'WhatsApp complaint',
            'description' => 'Complaint detail', 'status' => 'baru']);

        $this->actingAs($cs)->get(route('tickets.index'))->assertOk()
            ->assertSee('WhatsApp complaint')->assertSee('Tersedia untuk ditangani');
        $this->get(route('tickets.show', $ticket))->assertOk()
            ->assertSee('Identitas belum ditautkan ke pelanggan terverifikasi')
            ->assertDontSee('Tinjau permintaan &amp; verifikasi');
        $this->put(route('tickets.update', $ticket), ['status' => 'ditangani'])->assertRedirect();

        $this->assertSame($cs->id, $ticket->fresh()->assigned_to);
        $this->assertNull($ticket->fresh()->contact_id);
        $this->assertNull($inquiry->fresh()->contact_id);
        $this->get(route('tickets.show', $ticket))->assertOk();
    }

    public function test_ticket_resolution_is_required_and_order_must_match_contact(): void
    {
        $cs = $this->user();
        $contact = $this->contact($cs);
        $other = $this->contact($cs);
        $opportunity = $this->opportunity($other, $this->service(), $cs);
        $order = app(OpportunityConversion::class)->convert($opportunity, 500);
        $this->actingAs($cs)->post(route('tickets.store'), ['contact_id' => $contact->id, 'order_id' => $order->id, 'subject' => 'Mismatch'])->assertStatus(422);
        $this->post(route('tickets.store'), ['contact_id' => $contact->id, 'subject' => 'Needs help', 'status' => 'selesai'])->assertSessionHasErrors('status');
        $this->post(route('tickets.store'), ['contact_id' => $contact->id, 'subject' => 'Needs help'])->assertRedirect();
        $ticket = Ticket::where('contact_id', $contact->id)->where('subject', 'Needs help')->firstOrFail();
        $this->put(route('tickets.update', $ticket), ['status' => 'ditangani'])->assertRedirect();
        $this->put(route('tickets.update', $ticket), ['status' => 'selesai'])->assertSessionHasErrors('resolution');
        $this->put(route('tickets.update', $ticket), ['status' => 'selesai', 'resolution' => 'Repaired'])->assertRedirect();
        $this->assertNotNull($ticket->fresh()->resolved_at);
        $this->assertSame('Repaired', $ticket->fresh()->resolution);
    }

    public function test_owner_reports_filter_records_by_period_owner_service_and_stage(): void
    {
        $owner = $this->user('owner');
        $cs = $this->user();
        $other = $this->user();
        $service = $this->service();
        $anotherService = $this->service();
        $contact = $this->contact($cs);
        $included = $this->opportunity($contact, $service, $cs, ['stage' => 'berhasil', 'closed_at' => '2035-05-12 12:00:00', 'created_at' => '2035-05-10 12:00:00']);
        $included->forceFill(['created_at' => '2035-05-10 12:00:00'])->save();
        $excluded = $this->opportunity($this->contact($other), $anotherService, $other, ['stage' => 'berhasil', 'closed_at' => '2035-05-12 12:00:00']);
        $excluded->forceFill(['created_at' => '2035-05-10 12:00:00'])->save();
        $url = route('reports.index', ['from' => '2035-05-01', 'to' => '2035-05-31', 'owner_id' => $cs->id, 'service_id' => $service->id, 'stage' => 'berhasil']);
        $this->actingAs($owner)->get($url)->assertOk()->assertViewHas('records', fn ($records) => $records->pluck('id')->all() === [$included->id])
            ->assertViewHas('metrics', fn ($metrics) => $metrics['won'] === 1 && $metrics['lost'] === 0 && $metrics['conversion_percent'] === 100.0);
        $this->get(route('reports.index', ['from' => '2035-06-01', 'to' => '2035-05-01']))->assertSessionHasErrors('to');
        $this->assertNotSame($included->id, $excluded->id);
    }

    public function test_owner_can_assign_and_cs_can_verify_existing_contact_without_automatic_merge(): void
    {
        $owner = $this->user('owner');
        $cs = $this->user('cs');
        $service = $this->service();
        $existing = $this->contact($cs);
        $existing->update(['phone' => '081234567890']);
        $this->post(route('inquiry.store'), ['name' => 'Pelanggan yang kembali', 'phone' => '081234567890',
            'service_id' => $service->id, 'requirement' => 'Cetak sertifikat acara', 'consent' => 1])->assertRedirect();
        $inquiry = Inquiry::latest('id')->firstOrFail();
        $this->assertNull($inquiry->contact_id);
        $this->assertSame(1, Contact::where('phone', '081234567890')->count());
        $this->actingAs($cs)->put(route('inquiries.assign', $inquiry), ['assigned_to' => $cs->id])->assertForbidden();
        $this->actingAs($owner)->get(route('inquiries.show', $inquiry))->assertOk();
        $this->put(route('inquiries.assign', $inquiry), ['assigned_to' => $cs->id])->assertRedirect();
        $this->get(route('inquiries.show', $inquiry))->assertSee('Kandidat dengan nomor sama');
        $this->actingAs($cs)->put(route('inquiries.verify', $inquiry), ['contact_id' => $existing->id, 'identity_confirmed' => '1'])->assertRedirect();
        $this->assertEquals($existing->id, $inquiry->fresh()->contact_id);
        $this->assertSame('terverifikasi', $inquiry->fresh()->status);
        $this->assertSame(1, Contact::where('phone', '081234567890')->count());
    }

    public function test_verification_creates_one_contact_and_other_cs_cannot_access_inquiry(): void
    {
        $owner = $this->user('owner');
        $cs = $this->user();
        $other = $this->user();
        $service = $this->service();
        $this->post(route('inquiry.store'), ['name' => 'Fresh requester', 'phone' => '088812345678',
            'service_id' => $service->id, 'requirement' => 'Cetak banner', 'consent' => 1])->assertRedirect();
        $inquiry = Inquiry::latest('id')->firstOrFail();
        $this->actingAs($owner)->put(route('inquiries.assign', $inquiry), ['assigned_to' => $cs->id])->assertRedirect();
        $this->actingAs($other)->get(route('inquiries.index'))->assertDontSee('Fresh requester');
        $this->get(route('inquiries.show', $inquiry))->assertForbidden();
        $this->put(route('inquiries.verify', $inquiry))->assertForbidden();
        $this->actingAs($cs)->get(route('inquiries.show', $inquiry))->assertOk()
            ->assertSee('identity_confirmed')->assertDontSee('onclick="return confirm', false);
        $this->put(route('inquiries.verify', $inquiry))->assertSessionHasErrors('identity_confirmed');
        $this->assertNull($inquiry->fresh()->contact_id);
        $this->put(route('inquiries.verify', $inquiry), ['identity_confirmed' => '1'])->assertRedirect();
        $this->assertSame(1, Contact::where('phone', '088812345678')->count());
        $this->assertSame($cs->id, $inquiry->fresh()->contact->owner_id);
        $this->put(route('inquiries.verify', $inquiry), ['identity_confirmed' => '1'])->assertSessionHasErrors('contact_id');
        $this->assertSame(1, Contact::where('phone', '088812345678')->count());
    }

    public function test_existing_contact_of_another_cs_requires_owner_reassignment_before_verification(): void
    {
        $owner = $this->user('owner');
        $cs = $this->user();
        $other = $this->user();
        $existing = $this->contact($other, '081255500011');
        $this->post(route('inquiry.store'), ['name' => 'Returning', 'phone' => '081255500011',
            'service_id' => $this->service()->id, 'requirement' => 'Another order', 'consent' => 1])->assertRedirect();
        $inquiry = Inquiry::latest('id')->firstOrFail();
        $this->actingAs($owner)->put(route('inquiries.assign', $inquiry), ['assigned_to' => $cs->id])->assertRedirect();
        $this->actingAs($cs)->put(route('inquiries.verify', $inquiry), ['contact_id' => $existing->id, 'identity_confirmed' => '1'])->assertSessionHasErrors('contact_id');
        $this->assertNull($inquiry->fresh()->contact_id);
        $this->actingAs($owner)->put(route('inquiries.assign', $inquiry), ['assigned_to' => $other->id])->assertRedirect();
        $this->actingAs($cs)->get(route('inquiries.show', $inquiry))->assertForbidden();
        $this->actingAs($other)->put(route('inquiries.verify', $inquiry), ['contact_id' => $existing->id, 'identity_confirmed' => '1'])->assertRedirect();
        $this->assertSame($existing->id, $inquiry->fresh()->contact_id);
        $this->assertSame(1, Contact::where('phone', '081255500011')->count());
    }

    public function test_order_cancellation_requires_owner_reason_and_terminal_statuses_cannot_be_reopened(): void
    {
        $owner = $this->user('owner');
        $cs = $this->user();
        $opportunity = $this->opportunity($this->contact($cs), $this->service(), $cs);
        $order = app(OpportunityConversion::class)->convert($opportunity, 500);
        $this->actingAs($cs)->put(route('orders.update', $order), ['status' => 'dibatalkan', 'cancellation_reason' => 'Pelanggan batal'])->assertForbidden();
        $this->actingAs($owner)->put(route('orders.update', $order), ['status' => 'dibatalkan'])->assertSessionHasErrors('cancellation_reason');
        $this->put(route('orders.update', $order), ['status' => 'dibatalkan', 'cancellation_reason' => 'Pelanggan batal'])->assertRedirect();
        $this->assertSame('Pelanggan batal', $order->fresh()->cancellation_reason);
        $this->assertNotNull($order->fresh()->cancelled_at);
        $this->put(route('orders.update', $order), ['status' => 'diproses'])->assertSessionHasErrors('status');
        $this->put(route('opportunities.update', $opportunity), ['stage' => 'gagal', 'lost_reason' => 'Batal'])->assertSessionHasErrors('stage');
        $lost = $this->opportunity($this->contact($cs), $this->service(), $cs);
        $this->put(route('opportunities.update', $lost), ['stage' => 'gagal', 'lost_reason' => 'Tidak jadi'])->assertRedirect();
        $this->put(route('opportunities.update', $lost), ['title' => 'Changed'])->assertSessionHasErrors('stage');
        $this->assertNotSame('Changed', $lost->fresh()->title);
    }

    public function test_cs_cannot_read_or_change_other_cs_operational_records(): void
    {
        $cs = $this->user();
        $other = $this->user();
        $contact = $this->contact($other);
        $service = $this->service();
        $opportunity = $this->opportunity($contact, $service, $other);
        $task = Task::create(['opportunity_id' => $opportunity->id, 'assigned_to' => $other->id, 'due_at' => now()->addDay(), 'status' => 'belum_dikerjakan']);
        $order = app(OpportunityConversion::class)->convert($opportunity, 200);
        $ticket = Ticket::create(['contact_id' => $contact->id, 'assigned_to' => $other->id, 'subject' => 'Private', 'status' => 'baru']);
        $this->actingAs($cs);
        foreach (['opportunities' => $opportunity, 'tasks' => $task, 'orders' => $order, 'tickets' => $ticket] as $type => $record) {
            $this->get(route($type.'.show', $record))->assertForbidden();
            $this->put(route($type.'.update', $record), ['status' => 'selesai'])->assertForbidden();
            $this->get(route($type.'.index'))->assertOk();
        }
        $this->get(route('tickets.index'))->assertDontSee('Private');
        $this->get(route('opportunities.index'))->assertDontSee($opportunity->title);
    }

    public function test_admin_can_assign_unverified_inquiry_but_cannot_verify_or_edit_business_data(): void
    {
        $admin = $this->user('admin');
        $cs = $this->user();
        $other = $this->user();
        $service = $this->service();
        $this->post(route('inquiry.store'), ['name' => 'Permintaan dari publik', 'phone' => '081288811122',
            'service_id' => $service->id, 'requirement' => 'Butuh poster', 'consent' => 1])->assertRedirect();
        $inquiry = Inquiry::latest('id')->firstOrFail();

        $this->actingAs($admin)->get(route('inquiries.show', $inquiry))->assertOk()
            ->assertSee('Penugasan opsional')->assertDontSee('Konfirmasi verifikasi');
        $this->put(route('inquiries.assign', $inquiry), ['assigned_to' => $admin->id])->assertStatus(422);
        $this->put(route('inquiries.assign', $inquiry), ['assigned_to' => $cs->id])->assertRedirect();
        $this->assertSame($cs->id, $inquiry->fresh()->assigned_to);
        $this->assertSame('ditugaskan', $inquiry->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['entity_type' => Inquiry::class, 'entity_id' => $inquiry->id, 'actor_id' => $admin->id]);
        $this->actingAs($cs)->get(route('inquiries.show', $inquiry))->assertOk();
        $this->actingAs($admin)->put(route('inquiries.assign', $inquiry), ['assigned_to' => $other->id])->assertRedirect();
        $this->actingAs($cs)->get(route('inquiries.show', $inquiry))->assertForbidden();
        $this->actingAs($other)->get(route('inquiries.show', $inquiry))->assertOk();
        $this->actingAs($admin)->put(route('inquiries.verify', $inquiry))->assertForbidden();
        $this->put(route('inquiries.update', $inquiry), ['requirement' => 'Changed'])->assertForbidden();
        $this->assertSame('Butuh poster', $inquiry->fresh()->requirement);
        $this->actingAs($other)->put(route('inquiries.verify', $inquiry), ['identity_confirmed' => '1'])->assertRedirect();
        $this->actingAs($admin)->put(route('inquiries.assign', $inquiry), ['assigned_to' => $cs->id])->assertSessionHasErrors('assigned_to');
        $this->assertSame($other->id, $inquiry->fresh()->assigned_to);
    }

    public function test_visible_search_filters_crm_and_admin_lists(): void
    {
        $cs = $this->user();
        $owner = $this->user('owner');
        $admin = $this->user('admin');
        $contact = $this->contact($cs);
        $service = $this->service();
        $opportunity = $this->opportunity($contact, $service, $cs, ['title' => 'Paket Anggrek']);
        $task = Task::create(['opportunity_id' => $opportunity->id, 'assigned_to' => $cs->id,
            'due_at' => now()->addDay(), 'status' => 'belum_dikerjakan', 'note' => 'Hubungi Anggrek']);
        $ticket = Ticket::create(['contact_id' => $contact->id, 'assigned_to' => $cs->id,
            'subject' => 'Perbaiki sertifikat Anggrek', 'status' => 'baru']);
        $inquiry = Inquiry::create(['contact_id' => $contact->id, 'assigned_to' => $cs->id,
            'submitted_name' => 'Pemesan Anggrek', 'submitted_phone' => $contact->phone,
            'service_id' => $service->id, 'requirement' => 'Sertifikat Anggrek',
            'status' => 'terverifikasi', 'source' => 'langsung', 'consent_at' => now()]);
        $order = app(OpportunityConversion::class)->convert($opportunity, 125000);
        $this->actingAs($cs);
        foreach (['inquiries' => ['Anggrek', $inquiry->id], 'opportunities' => ['Anggrek', $opportunity->id],
            'tasks' => ['Anggrek', $task->id], 'orders' => [$order->order_code, $order->id],
            'tickets' => ['Anggrek', $ticket->id]] as $type => [$term, $id]) {
            $this->get(route($type.'.index', ['search' => $term]))->assertOk()
                ->assertViewHas('records', fn ($records) => $records->pluck('id')->contains($id));
            $this->get(route($type.'.index', ['search' => 'TidakAdaHasilXYZ']))->assertOk()
                ->assertViewHas('records', fn ($records) => $records->isEmpty());
        }
        $this->actingAs($admin)->get(route('admin.services.index', ['search' => $service->name]))->assertOk()
            ->assertViewHas('records', fn ($records) => $records->pluck('id')->contains($service->id));
        $this->get(route('admin.users.index', ['search' => $cs->email]))->assertOk()
            ->assertViewHas('records', fn ($records) => $records->pluck('id')->contains($cs->id));
    }

    public function test_owner_must_assign_active_cs_and_new_tasks_start_pending(): void
    {
        $owner = $this->user('owner');
        $cs = $this->user();
        $contact = $this->contact($cs);
        $opportunity = $this->opportunity($contact, $this->service(), $cs);
        $this->actingAs($owner)->post(route('contacts.store'), ['name' => 'Demo Baru'])
            ->assertSessionHasErrors('owner_id');
        $this->post(route('tasks.store'), ['opportunity_id' => $opportunity->id, 'due_at' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors('assigned_to');
        $this->post(route('tickets.store'), ['contact_id' => $contact->id, 'subject' => 'Demo'])
            ->assertSessionHasErrors('assigned_to');
        $this->post(route('tasks.store'), ['opportunity_id' => $opportunity->id, 'assigned_to' => $cs->id,
            'due_at' => now()->addDay()->toDateString(), 'status' => 'selesai'])->assertSessionHasErrors('status');
        $this->assertSame(0, Task::where('opportunity_id', $opportunity->id)->count());
        $this->post(route('tasks.store'), ['opportunity_id' => $opportunity->id, 'assigned_to' => $cs->id,
            'due_at' => now()->addDay()->toDateString()])->assertRedirect();
        $this->assertSame('belum_dikerjakan', Task::where('opportunity_id', $opportunity->id)->sole()->status);
        $this->get(route('tasks.create'))->assertOk()->assertDontSee('name="status"', false);
    }

    public function test_task_and_status_forms_only_offer_actions_the_server_accepts(): void
    {
        $cs = $this->user();
        $owner = $this->user('owner');
        $contact = $this->contact($cs);
        $opportunity = $this->opportunity($contact, $this->service(), $cs);
        $task = Task::create(['opportunity_id' => $opportunity->id, 'assigned_to' => $cs->id,
            'due_at' => now()->addDay(), 'status' => 'belum_dikerjakan']);
        $this->actingAs($cs)->get(route('tasks.edit', $task))->assertOk()->assertDontSee('name="opportunity_id"', false);
        $this->put(route('tasks.update', $task), ['status' => 'selesai'])->assertRedirect();
        $this->get(route('tasks.show', $task))->assertOk()->assertDontSee(route('tasks.edit', $task));
        $this->get(route('tasks.edit', $task))->assertForbidden();

        $openTask = Task::create(['opportunity_id' => $opportunity->id, 'assigned_to' => $cs->id,
            'due_at' => now()->addDay(), 'status' => 'belum_dikerjakan']);
        $order = app(OpportunityConversion::class)->convert($opportunity, 500);
        $this->get(route('tasks.show', $openTask))->assertOk()->assertDontSee(route('tasks.edit', $openTask));
        $this->get(route('tasks.edit', $openTask))->assertForbidden();
        $this->get(route('orders.edit', $order))->assertOk()->assertDontSee('value="dibatalkan"', false)->assertDontSee('value="selesai"', false);
        $this->actingAs($owner)->get(route('orders.edit', $order))->assertOk()->assertSee('value="dibatalkan"', false)->assertDontSee('value="selesai"', false);

        $ticket = Ticket::create(['contact_id' => $contact->id, 'assigned_to' => $cs->id, 'subject' => 'Perlu bantuan', 'status' => 'baru']);
        $this->actingAs($cs)->get(route('tickets.edit', $ticket))->assertOk()->assertDontSee('value="selesai"', false)->assertDontSee('value="ditutup"', false);
        $this->put(route('tickets.update', $ticket), ['status' => 'ditangani'])->assertRedirect();
        $this->get(route('tickets.edit', $ticket))->assertOk()->assertSee('value="selesai"', false)->assertDontSee('value="ditutup"', false);
    }

    public function test_admin_can_open_create_and_edit_forms_and_update_service(): void
    {
        $admin = $this->user('admin');
        $service = $this->service();
        $staff = $this->user();

        $this->actingAs($admin)->get(route('admin.services.create'))->assertOk();
        $this->get(route('admin.users.create'))->assertOk();
        $this->get(route('admin.services.edit', $service))->assertOk();
        $this->get(route('admin.users.edit', $staff))->assertOk();
        $this->put(route('admin.services.update', $service), ['name' => 'Layanan diperbarui'])->assertRedirect(route('admin.services.index'));
        $this->assertSame('Layanan diperbarui', $service->fresh()->name);
    }

    public function test_core_pages_render_and_admin_password_is_not_logged(): void
    {
        $admin = $this->user('admin');
        $owner = $this->user('owner');
        $service = $this->service();
        $this->get(route('inquiry.create'))->assertOk()->assertSee('Ceritakan kebutuhan')
            ->assertSee('https://wa.me/'.preg_replace('/\D+/', '', (string) config('services.crm.whatsapp_cs')).'?text=MENU', false)
            ->assertSee('Konsultasi via bot WhatsApp')
            ->assertDontSee('Konsultasi online');
        $this->get(route('login'))->assertOk();
        $this->actingAs($owner)->get(route('dashboard'))->assertOk();
        $this->get(route('reports.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->post(route('admin.users.store'), [
            'name' => 'Pegawai Baru', 'email' => 'pegawai-'.\Illuminate\Support\Str::random(10).'@example.test',
            'password' => 'RahasiaUji2026!', 'password_confirmation' => 'RahasiaUji2026!',
            'role' => 'cs', 'active' => 1,
        ])->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseMissing('activity_logs', ['new_value' => '%RahasiaUji2026!%']);
        $this->assertFalse(\App\Models\ActivityLog::where('entity_type', \App\Models\User::class)->where('new_value', 'like', '%RahasiaUji2026!%')->exists());
    }
}
