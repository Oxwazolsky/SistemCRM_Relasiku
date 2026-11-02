<?php

namespace App\Jobs;

use App\Models\WhatsappBotMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SendWhatsappBotReply implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $messageId) {}

    public function handle(): void
    {
        if (! config('whatsapp.enabled')) return;
        $token = config('whatsapp.access_token');
        $phoneId = config('whatsapp.phone_number_id');
        if (! $token || ! $phoneId) throw new RuntimeException('WhatsApp Cloud API is not configured.');
        $message = WhatsappBotMessage::findOrFail($this->messageId);
        if (! $message->reply || ! $message->sender || $message->delivery_status === 'sent') return;
        // Mark before calling Meta: a timeout may happen after Meta accepts the send.
        // Never blindly retry and accidentally send the same response twice.
        $claimed = WhatsappBotMessage::whereKey($message->id)->where('delivery_status', 'pending')
            ->update(['delivery_status' => 'sending']);
        if (! $claimed) return;
        try {
            $response = Http::withToken($token)->timeout(15)->post(
                'https://graph.facebook.com/'.config('whatsapp.graph_version').'/'.$phoneId.'/messages',
                ['messaging_product' => 'whatsapp', 'to' => $message->sender, 'type' => 'text',
                    'text' => ['body' => $message->reply]]
            );
            if (! $response->successful() || ! $response->json('messages.0.id')) {
                throw new RuntimeException('WhatsApp API rejected outbound message (HTTP '.$response->status().').');
            }
            $message->update(['delivery_status' => 'sent', 'outbound_message_id' => $response->json('messages.0.id'), 'sent_at' => now()]);
        } catch (\Throwable $exception) {
            $message->update(['delivery_status' => 'uncertain', 'delivery_error' => substr($exception->getMessage(), 0, 1000)]);
            // Operator must investigate the provider status before any manual resend.
            report($exception);
        }
    }
}
