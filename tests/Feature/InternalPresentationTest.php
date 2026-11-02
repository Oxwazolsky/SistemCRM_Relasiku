<?php

namespace Tests\Feature;

use App\Models\{Contact, Inquiry, Order, Service, User};
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternalPresentationTest extends TestCase
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

    public function test_role_workspaces_and_active_pages_render(): void
    {
        foreach (['cs', 'owner', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role, 'active' => true]);
            $this->actingAs($user);
            $routes = ['dashboard', 'inbox'];
            $routes = array_merge($routes, $role === 'admin'
                ? ['admin.users.index', 'admin.users.create', 'admin.services.index', 'admin.services.create', 'admin.faqs']
                : ['contacts.index', 'contacts.create', 'inquiries.index', 'inquiries.create', 'orders.index', 'tickets.index', 'tickets.create']);
            if ($role === 'owner') $routes[] = 'reports.index';
            foreach ($routes as $route) {
                $response = $this->get(route($route));
                $response->assertSee('data-workspace=', false);
                $response->assertOk()->assertSee('Sistem CRM')->assertSee('Lewati navigasi')
                    ->assertDontSee('href="'.route('tasks.index').'"', false)
                    ->assertDontSee('href="'.route('opportunities.index').'"', false);
            }
            if ($role === 'cs') $this->get(route('dashboard'))->assertDontSee('Analisis &amp; pengaturan', false)->assertSee('Pekerjaan saya');
            // Existing contract permits admin read-only access, but not primary navigation or writes.
            if ($role === 'admin') $this->get(route('contacts.index'))->assertOk()->assertDontSee('Tambah pelanggan');
        }
    }

    public function test_protected_presentations_remain_byte_for_byte_unchanged(): void
    {
        foreach ([
            'resources/views/auth/login.blade.php' => 'c29131126cbea6ad359ad22d1ff17eb968da23b076264a06c2c40ba5d61aa036',
            'resources/views/partials/handling.blade.php' => '712374f5b9aa2680cef0c4779aa6dca11d1ed71a655fd3c4f93dd2ca3faf664b',
        ] as $path => $hash) {
            $this->assertSame($hash, hash_file('sha256', base_path($path)), $path);
        }
    }

    public function test_existing_order_is_a_link_not_a_required_price_form(): void
    {
        $cs = User::factory()->create(['role' => 'cs', 'active' => true]);
        $contact = Contact::create(['name' => 'Presentation customer', 'owner_id' => $cs->id, 'stage' => 'lead']);
        $service = Service::create(['name' => 'Presentation service', 'category' => 'Test', 'active' => true]);
        $inquiry = Inquiry::create(['contact_id' => $contact->id, 'assigned_to' => $cs->id, 'service_id' => $service->id,
            'requirement' => 'Print', 'status' => 'terverifikasi', 'intent' => 'order']);
        $this->actingAs($cs)->post(route('inquiries.confirm-order', $inquiry), ['final_value' => 1000, 'customer_confirmed' => 1])->assertRedirect();
        $order = Order::where('opportunity_id', $inquiry->fresh()->opportunity_id)->sole();
        $this->get(route('inquiries.show', $inquiry))->assertOk()->assertSee('Buka pesanan '.$order->order_code)
            ->assertDontSee('name="final_value"', false)->assertSee('aria-current="page"', false);
        $owner = User::factory()->create(['role' => 'owner', 'active' => true]);
        $this->actingAs($owner)->get(route('contacts.show', $contact))->assertOk()->assertSee($cs->name)->assertSee('bukan riwayat audit');
        foreach (['contacts.edit', 'contacts.transfer.edit'] as $route) $this->get(route($route, $contact))->assertOk();
        foreach (['orders.show', 'orders.edit'] as $route) $this->get(route($route, $order))->assertOk();
        $order->update(['status' => 'dibatalkan']);
        $this->get(route('reports.index', ['service_id' => $service->id]))->assertOk()
            ->assertViewHas('metrics', fn ($metrics) => (float) $metrics['sales_value'] === 0.0);
    }
}
