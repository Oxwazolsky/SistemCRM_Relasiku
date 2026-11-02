<?php

namespace Tests\Feature;

use App\Models\{BotFaq, Service, User};
use App\Services\WhatsappConsultationBot;
use Database\Seeders\ServiceFaqSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceFaqSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_is_idempotent_and_preserves_existing_content_and_accounts(): void
    {
        $service = Service::create(['name' => 'Poster Acara', 'category' => 'Kategori pribadi', 'active' => false]);
        $faq = BotFaq::create(['question' => 'Bagaimana memulai konsultasi atau permintaan pesanan?', 'answer' => 'Jawaban pengguna', 'active' => false]);
        $custom = BotFaq::create(['question' => 'Pertanyaan khusus', 'answer' => 'Tetap utuh', 'active' => true]);
        $other = Service::create(['name' => 'Layanan pribadi', 'category' => 'Khusus', 'active' => true]);
        $user = User::factory()->create();
        $before = [$service->fresh()->getAttributes(), $faq->fresh()->getAttributes(), $custom->fresh()->getAttributes(), $other->fresh()->getAttributes(), $user->fresh()->getAttributes()];

        $this->seed(ServiceFaqSeeder::class);
        $this->seed(ServiceFaqSeeder::class);

        $this->assertDatabaseCount('services', 7);
        $this->assertDatabaseCount('bot_faqs', 13);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($before, [$service->fresh()->getAttributes(), $faq->fresh()->getAttributes(), $custom->fresh()->getAttributes(), $other->fresh()->getAttributes(), $user->fresh()->getAttributes()]);
    }

    public function test_all_seeded_faqs_are_accessible_by_their_menu_number(): void
    {
        $this->seed(ServiceFaqSeeder::class);
        $this->assertDatabaseCount('services', 6);
        $this->assertDatabaseCount('bot_faqs', 12);
        $bot = app(WhatsappConsultationBot::class);
        $phone = '6281234567890';
        $menu = $bot->receive($phone, 'seed-faq-menu', '2');
        foreach (BotFaq::orderBy('id')->get() as $index => $faq) {
            $this->assertStringContainsString('*'.($index + 1).'.* '.$faq->question, $menu);
            $this->assertLessThanOrEqual(255, mb_strlen($faq->question));
            $this->assertLessThanOrEqual(3000, mb_strlen($faq->answer));
            $reply = $bot->receive($phone, 'seed-faq-answer-'.$index, (string) ($index + 1));
            $this->assertStringContainsString($faq->answer, $reply);
        }
        $this->assertLessThan(1500, mb_strlen($menu));
    }
}
