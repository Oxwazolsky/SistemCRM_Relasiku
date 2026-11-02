<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== 'relasiku_crm_test') {
            $this->markTestSkipped('Configured dedicated test database required.');
        }
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if (DB::connection()->transactionLevel()) DB::rollBack();
        parent::tearDown();
    }

    public function test_guest_home_and_dashboard_require_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/login')->assertOk()->assertSee('Gunakan akun tim Anda');
    }

    public function test_each_active_role_reaches_its_existing_dashboard_after_login(): void
    {
        foreach (['cs' => 'Pekerjaan saya', 'owner' => 'Beban kerja CS aktif', 'admin' => 'Administrasi'] as $role => $heading) {
            $user = User::factory()->create(['role' => $role, 'active' => true, 'password' => bcrypt('HomepageTest123!')]);
            $this->post('/login', ['email' => $user->email, 'password' => 'HomepageTest123!'])->assertRedirect(route('dashboard'));
            $this->assertAuthenticatedAs($user);
            $this->get('/')->assertRedirect(route('dashboard'));
            $this->get('/dashboard')->assertOk()->assertViewIs('operations')->assertSee($heading);
            $this->get('/login')->assertRedirect(route('dashboard'));
            $this->post('/logout')->assertRedirect(route('login'));
        }
    }

    public function test_intended_home_after_login_still_leads_to_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'cs', 'active' => true, 'password' => bcrypt('HomepageTest123!')]);
        $this->get('/')->assertRedirect(route('login'));
        $this->post('/login', ['email' => $user->email, 'password' => 'HomepageTest123!'])->assertRedirect('/');
        $this->get('/')->assertRedirect(route('dashboard'));
        $this->get('/dashboard')->assertOk();
    }

    public function test_inactive_accounts_cannot_login_or_use_existing_sessions(): void
    {
        foreach (['cs', 'owner', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role, 'active' => false, 'password' => bcrypt('HomepageTest123!')]);
            $this->post('/login', ['email' => $user->email, 'password' => 'HomepageTest123!'])->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->actingAs($user)->get('/')->assertForbidden();
            $this->get('/dashboard')->assertForbidden();
            auth()->logout();
        }
    }

    public function test_role_authorization_is_not_bypassed_by_home(): void
    {
        $cs = User::factory()->create(['role' => 'cs', 'active' => true]);
        $this->actingAs($cs)->get('/admin/users')->assertForbidden();
        $this->get('/reports')->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $this->actingAs($admin)->get('/reports')->assertForbidden();
    }

    public function test_public_intake_pages_and_submission_routes_remain_available(): void
    {
        $this->get('/inquiry')->assertOk();
        $this->get('/konsultasi')->assertOk();
        $this->post('/inquiry', [])->assertSessionHasErrors();
        $this->post('/konsultasi', [])->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_customer_link_requires_a_configured_valid_number(): void
    {
        foreach ([null, '', 'not-a-number'] as $number) {
            config(['services.crm.whatsapp_cs' => $number]);
            $this->get('/login')->assertOk()->assertDontSee('Pelanggan? Hubungi kami melalui WhatsApp');
        }
        config(['services.crm.whatsapp_cs' => '6281234567890']);
        $this->get('/login')->assertOk()->assertSee('Pelanggan? Hubungi kami melalui WhatsApp')->assertSee('https://wa.me/6281234567890?text=MENU', false);
    }
}
