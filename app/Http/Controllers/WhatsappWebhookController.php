<?php

namespace App\Http\Controllers;

use App\Jobs\SendWhatsappBotReply;
use App\Models\WhatsappBotMessage;
use Illuminate\Support\Facades\Log;
use App\Services\WhatsappConsultationBot;
use Illuminate\Http\Request;

class WhatsappWebhookController extends Controller
{
    public function verify(Request $request)
    {
        $token = config('whatsapp.verify_token');
        if (! config('whatsapp.enabled') || ! is_string($token) || $token === ''
            || $request->query('hub_mode') !== 'subscribe'
            || ! hash_equals($token, (string) $request->query('hub_verify_token', ''))) abort(403);
        return response((string) $request->query('hub_challenge', ''), 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, WhatsappConsultationBot $bot)
    {
        $secret = config('whatsapp.app_secret');
        if (! config('whatsapp.enabled') || ! is_string($secret) || $secret === '') abort(503);
        $signature = $request->header('X-Hub-Signature-256', '');
        if (! preg_match('/^sha256=([a-f0-9]{64})$/', $signature, $matches)
            || ! hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $matches[1])) abort(403);
        $payload = $request->json()->all();
        if (($payload['object'] ?? null) !== 'whatsapp_business_account') return response()->noContent();
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                if (($value['metadata']['phone_number_id'] ?? null) != config('whatsapp.phone_number_id')) continue;
                foreach ($value['messages'] ?? [] as $message) {
                    $type = $message['type'] ?? null;
                    if ($type !== 'text') continue;
                    $phone = $message['from'] ?? '';
                    $id = $message['id'] ?? '';
                    if (! is_string($phone) || ! preg_match('/^[1-9][0-9]{7,14}$/', $phone) || ! is_string($id) || strlen($id) > 255 || $id === '') continue;
                    try {
                        $text = $message['text']['body'] ?? '';
                        if (! is_string($text) || mb_strlen($text) > 5000) continue;
                        $bot->receive($phone, $id, $text);
                    } catch (\DomainException $exception) {
                        continue;
                    } catch (\InvalidArgumentException $exception) {
                        Log::warning('WhatsApp webhook message ID conflict', ['message_id' => $id]);
                        continue;
                    } catch (\Throwable $exception) {
                        Log::warning('WhatsApp inbound text could not be processed', ['message_id' => $id]);
                        continue;
                    }
                    $record = WhatsappBotMessage::where('message_id', $id)->where('sender', $phone)->first();
                    if ($record && $record->delivery_status === 'pending' && $record->reply) SendWhatsappBotReply::dispatch($record->id)->afterCommit();
                }
            }
        }
        return response()->noContent();
    }
}
