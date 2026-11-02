<?php

namespace App\Http\Controllers;

use App\Models\WhatsappBotConversation;
use Illuminate\Support\Facades\RateLimiter;
use App\Services\WhatsappConsultationBot;
use Illuminate\Http\Request;

class BaileysDemoController extends Controller
{
    public function receive(Request $request, WhatsappConsultationBot $bot)
    {
        // This adapter is deliberately local-only. Do not expose it through a tunnel or public reverse proxy.
        $ip = $request->ip();
        if (! in_array($ip, ['127.0.0.1', '::1'], true)) abort(403);
        $secret = config('whatsapp.baileys_secret');
        if (! config('whatsapp.baileys_enabled') || ! is_string($secret) || strlen($secret) < 32) abort(503);
        $provided = $request->header('X-Baileys-Secret', '');
        if (! is_string($provided) || ! hash_equals($secret, $provided)) abort(403);

        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^[1-9][0-9]{7,14}$/'],
            'message_id' => ['required', 'string', 'max:255'],
            'text' => ['required', 'string', 'max:5000'],
        ]);
        // Limit each sender, not just the shared Node process/IP. Retries of an existing
        // message ID must still be allowed to retrieve the same reply without advancing state.
        $id = 'baileys:'.$data['message_id'];
        $existing = \App\Models\WhatsappBotMessage::where('message_id', $id)->first();
        if (! $existing) {
            $key = 'baileys-demo:'.hash('sha256', $data['phone']);
            if (RateLimiter::tooManyAttempts($key, 20)) {
                return response()->json(['error' => 'Terlalu banyak pesan; coba lagi sebentar.'], 429);
            }
            RateLimiter::hit($key, 60);
        }
        $reply = $bot->receive($data['phone'], $id, $data['text']);
        $conversation = WhatsappBotConversation::where('phone', $data['phone'])->firstOrFail();
        return response()->json(['reply' => $reply, 'handed_off' => $conversation->handed_off,
            'inquiry_id' => $conversation->inquiry_id]);
    }
}
