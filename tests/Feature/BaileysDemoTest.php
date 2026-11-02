<?php

namespace Tests\Feature;

use App\Models\{Inquiry, Service, WhatsappBotConversation, WhatsappBotMessage};
use Illuminate\Support\Facades\{DB, Schema, RateLimiter};
use Tests\TestCase;

class BaileysDemoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $database = (string) DB::connection()->getDatabaseName();
        if (! preg_match('/(?:_test|_testing)$/i', $database) && $database !== ':memory:') {
            $this->markTestSkipped('A dedicated test database is required.');
        }
        if (! Schema::hasTable('whatsapp_bot_messages')) $this->markTestSkipped('Migrate the test database.');
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if (DB::connection()->transactionLevel()) DB::rollBack();
        parent::tearDown();
    }

    private function send(string $id, string $text, array $headers = [])
    {
        return $this->withHeaders($headers)->postJson(route('baileys.demo.receive'), [
            'phone' => '6287712345678', 'message_id' => $id, 'text' => $text,
        ]);
    }

    public function test_local_bridge_is_disabled_by_default_and_requires_secret_and_loopback(): void
    {
        config()->set(['whatsapp.baileys_enabled' => false, 'whatsapp.baileys_secret' => str_repeat('a', 32)]);
        $this->send('one', 'MENU')->assertStatus(503);
        config()->set('whatsapp.baileys_enabled', true);
        $this->send('one', 'MENU')->assertForbidden();
        $this->send('one', 'MENU', ['X-Baileys-Secret' => 'wrong'])->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->send('one', 'MENU', ['X-Baileys-Secret' => str_repeat('a', 32)])->assertForbidden();
        $this->assertSame(0, WhatsappBotMessage::count());
    }

    public function test_sender_rate_limit_does_not_block_deduplicated_replies(): void
    {
        config()->set(['whatsapp.baileys_enabled' => true, 'whatsapp.baileys_secret' => str_repeat('a', 32)]);
        $headers = ['X-Baileys-Secret' => str_repeat('a', 32)];
        $key = 'baileys-demo:'.hash('sha256', '6287712345678');
        RateLimiter::clear($key);
        try {
            $this->send('limited-1', 'MENU', $headers)->assertOk();
            for ($i = 0; $i < 19; $i++) RateLimiter::hit($key, 60);
            $this->send('limited-2', 'MENU', $headers)->assertStatus(429);
            $this->send('limited-1', 'MENU', $headers)->assertOk();
            $this->assertSame(1, WhatsappBotMessage::where('message_id', 'baileys:limited-1')->count());
        } finally {
            RateLimiter::clear($key);
        }
    }

    public function test_local_bridge_creates_inquiry_only_after_consent_and_deduplicates(): void
    {
        config()->set(['whatsapp.baileys_enabled' => true, 'whatsapp.baileys_secret' => str_repeat('a', 32)]);
        Service::create(['name' => 'Layanan demo', 'category' => 'event', 'active' => true]);
        $headers = ['X-Baileys-Secret' => str_repeat('a', 32)];
        $this->send('1', 'MENU', $headers)->assertOk()->assertJsonPath('handed_off', false);
        $this->send('2', '1', $headers)->assertOk();
        $this->send('3', '1', $headers)->assertOk();
        $this->send('4', 'Pelanggan Demo', $headers)->assertOk();
        $this->send('5', '-', $headers)->assertOk(); // email is optional
        $this->send('6', '-', $headers)->assertOk(); // event date is optional
        $this->send('7', 'Butuh desain sertifikat acara', $headers)->assertOk();
        $this->assertSame(0, Inquiry::where('submitted_phone', '6287712345678')->count());
        $this->send('8', 'SETUJU', $headers)->assertOk()->assertJsonPath('handed_off', true);
        $this->send('8', 'SETUJU', $headers)->assertOk();
        $this->assertSame(1, Inquiry::where('submitted_phone', '6287712345678')->count());
        $inquiry = Inquiry::where('submitted_phone', '6287712345678')->firstOrFail();
        $this->assertSame('whatsapp', $inquiry->source);
        $this->assertNull($inquiry->submitted_email);
        $this->assertSame(1, WhatsappBotMessage::where('message_id', 'baileys:8')->count());
    }
}
