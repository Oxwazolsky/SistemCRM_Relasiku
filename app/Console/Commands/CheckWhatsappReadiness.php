<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CheckWhatsappReadiness extends Command
{
    protected $signature = 'whatsapp:check';

    protected $description = 'Read-only Cloud API test-number readiness check; never displays secrets';

    public function handle(): int
    {
        $checks = [
            'Migrasi percakapan' => Schema::hasTable('whatsapp_bot_conversations')
                && Schema::hasColumn('whatsapp_bot_messages', 'delivery_status')
                && Schema::hasColumn('whatsapp_bot_conversations', 'assigned_to'),
            'APP_URL HTTPS publik' => str_starts_with((string) config('app.url'), 'https://'),
            'Verify token' => filled(config('whatsapp.verify_token')),
            'App secret' => filled(config('whatsapp.app_secret')),
            'Access token' => filled(config('whatsapp.access_token')),
            'Phone number ID' => filled(config('whatsapp.phone_number_id')),
            'Antrean asinkron' => ! in_array(config('queue.default'), ['sync', 'null'], true),
        ];
        foreach ($checks as $name => $ready) $this->line(($ready ? '[OK] ' : '[BELUM] ').$name);
        $this->line('Cloud API: '.(config('whatsapp.enabled') ? 'AKTIF' : 'nonaktif (aman untuk persiapan)'));
        $this->line('Callback: '.rtrim((string) config('app.url'), '/').'/webhooks/whatsapp');
        $this->warn('HTTPS harus dapat dijangkau Meta; worker dan nomor tes harus diperiksa manual. Tidak ada koneksi Meta atau pengiriman pesan dari perintah ini.');
        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
