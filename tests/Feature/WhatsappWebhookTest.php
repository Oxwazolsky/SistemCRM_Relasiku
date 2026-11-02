<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsappBotReply;
use App\Models\{Inquiry, Service, WhatsappBotConversation, WhatsappBotMessage};
use Illuminate\Support\Facades\{DB, Http, Queue, Schema};
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsappWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = (string) DB::connection()->getDatabaseName();
        if (! preg_match('/(?:_test|_testing)$/i', $db) && $db !== ':memory:') {
            $this->markTestSkipped('Requires a dedicated test database.');
        }
        if (! Schema::hasColumn('whatsapp_bot_messages', 'delivery_status')) {
            $this->markTestSkipped('Migrate the test database first.');
        }
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if (DB::connection()->transactionLevel() > 0) DB::rollBack();
        parent::tearDown();
    }

    private function event(string $id, string $text, string $phone = '6287712345678'): array
    {
        return ['object' => 'whatsapp_business_account', 'entry' => [['changes' => [[
            'value' => ['metadata' => ['phone_number_id' => 'test-phone-id'],
                'messages' => [['from' => $phone, 'id' => $id, 'type' => 'text', 'text' => ['body' => $text]]]],
        ]]]]];
    }

    private function signedPost(array $payload)
    {
        $body = json_encode($payload);
        return $this->call('POST', route('whatsapp.webhook.receive'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'test-secret'),
        ], $body);
    }

    public function test_webhook_is_disabled_by_default_and_rejects_invalid_verification(): void
    {
        config()->set('whatsapp.enabled', false);
        $this->get(route('whatsapp.webhook.verify', ['hub_mode' => 'subscribe', 'hub_verify_token' => 'test-token', 'hub_challenge' => '123']))->assertForbidden();
        $this->postJson(route('whatsapp.webhook.receive'), $this->event('wamid-one', 'MENU'))->assertStatus(503);
        config()->set(['whatsapp.enabled' => true, 'whatsapp.verify_token' => 'test-token', 'whatsapp.app_secret' => 'test-secret', 'whatsapp.phone_number_id' => 'test-phone-id']);
        $this->get(route('whatsapp.webhook.verify', ['hub_mode' => 'subscribe', 'hub_verify_token' => 'wrong', 'hub_challenge' => '123']))->assertForbidden();
        $this->get(route('whatsapp.webhook.verify', ['hub_mode' => 'subscribe', 'hub_verify_token' => 'test-token', 'hub_challenge' => '123']))->assertOk()->assertSeeText('123');
        $this->postJson(route('whatsapp.webhook.receive'), $this->event('wamid-one', 'MENU'))->assertForbidden();
        $this->assertSame(0, WhatsappBotMessage::count());
    }

    public function test_readiness_check_does_not_expose_credentials_or_contact_meta(): void
    {
        config()->set(['app.url' => 'http://127.0.0.1:8000', 'whatsapp.enabled' => false,
            'whatsapp.verify_token' => 'secret-verify-value', 'whatsapp.app_secret' => 'secret-app-value',
            'whatsapp.access_token' => 'secret-access-value', 'whatsapp.phone_number_id' => 'test-phone-id']);
        $this->artisan('whatsapp:check')->expectsOutput('[BELUM] APP_URL HTTPS publik')
            ->expectsOutput('Cloud API: nonaktif (aman untuk persiapan)')->assertExitCode(1);
    }

    public function test_failed_delivery_is_marked_for_review_and_never_retried_automatically(): void
    {
        config()->set(['whatsapp.enabled' => true, 'whatsapp.app_secret' => 'test-secret',
            'whatsapp.phone_number_id' => 'test-phone-id', 'whatsapp.access_token' => 'fake-token']);
        Queue::fake();
        $this->signedPost($this->event('wamid-failed', 'MENU'))->assertNoContent();
        $message = WhatsappBotMessage::where('message_id', 'wamid-failed')->firstOrFail();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'test failure']], 429)]);
        (new SendWhatsappBotReply($message->id))->handle();
        $this->assertSame('uncertain', $message->fresh()->delivery_status);
        $this->assertStringContainsString('HTTP 429', $message->fresh()->delivery_error);
        (new SendWhatsappBotReply($message->id))->handle();
        Http::assertSentCount(1);
        Queue::fake();
        $this->signedPost($this->event('wamid-failed', 'MENU'))->assertNoContent();
        Queue::assertNotPushed(SendWhatsappBotReply::class);
    }

    public function test_complaint_menu_starts_text_only_complaint_intake(): void
    {
        $bot = app(\App\Services\WhatsappConsultationBot::class);
        $phone = '6287712345678';
        Service::create(['name' => 'Test', 'category' => 'Test', 'active' => true]);
        $bot->receive($phone, 'menu-complaint', 'MENU');
        $reply = $bot->receive($phone, 'choice-complaint', '4');
        $this->assertStringNotContainsString('http', $reply);
        $this->assertStringContainsString('Pilih layanan', $reply);
        $this->assertSame('service', WhatsappBotConversation::where('phone', $phone)->firstOrFail()->step);
    }

    public function test_signed_webhook_deduplicates_and_sends_once_to_expected_number(): void
    {
        config()->set(['whatsapp.enabled' => true, 'whatsapp.verify_token' => 'test-token', 'whatsapp.app_secret' => 'test-secret',
            'whatsapp.phone_number_id' => 'test-phone-id', 'whatsapp.access_token' => 'fake-token']);
        Queue::fake();
        $this->signedPost($this->event('wamid-first', 'MENU'))->assertNoContent();
        $this->signedPost($this->event('wamid-first', 'MENU'))->assertNoContent();
        $this->assertSame(1, WhatsappBotMessage::where('message_id', 'wamid-first')->count());
        $message = WhatsappBotMessage::where('message_id', 'wamid-first')->firstOrFail();
        Queue::assertPushed(SendWhatsappBotReply::class);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'outbound-1']]], 200)]);
        (new SendWhatsappBotReply($message->id))->handle();
        (new SendWhatsappBotReply($message->id))->handle();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://graph.facebook.com/v23.0/test-phone-id/messages'
            && $request['to'] === '6287712345678' && $request->hasHeader('Authorization', 'Bearer fake-token'));
        $this->assertSame('sent', $message->fresh()->delivery_status);
        $this->signedPost($this->event('wamid-first', 'MENU'))->assertNoContent();
        $this->assertSame(1, WhatsappBotMessage::where('message_id', 'wamid-first')->count());
        $this->signedPost($this->event('wamid-first', 'MENU', '6289912345678'))->assertNoContent();
        $this->assertSame(1, WhatsappBotConversation::count());
        $this->signedPost($this->event('wamid-other-phone', 'MENU', '6289912345678'))->assertNoContent();
        $this->assertSame(2, WhatsappBotConversation::count());
        $wrongNumber = $this->event('wamid-ignored', 'MENU');
        $wrongNumber['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] = 'not-ours';
        $this->signedPost($wrongNumber)->assertNoContent();
        $this->assertSame(0, WhatsappBotMessage::where('message_id', 'wamid-ignored')->count());
    }
}
