<?php

namespace App\Services;

use App\Models\{HandlingNote, Inquiry, Order, Ticket, User, WhatsappBotConversation};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HandlingWorkflow
{
    public function state(Model $record): string
    {
        if ($record instanceof WhatsappBotConversation && $record->inquiry_id) return $this->state($record->inquiry);
        if ($record instanceof Inquiry) {
            if ($record->intent === 'complaint') {
                $tickets = Ticket::where('inquiry_id', $record->id)->get();
                return $tickets->isNotEmpty() && $tickets->every(fn ($ticket) => in_array($ticket->status, ['selesai', 'ditutup'])) ? 'resolved' : 'active';
            }
            if ($record->opportunity_id && Order::where('opportunity_id', $record->opportunity_id)->exists()) return 'converted';
            if ($record->status === 'ditolak') return 'cancelled';
        }
        return $record->handling_status ?? 'active';
    }

    public function save(Model $subject, User $user, array $data): void
    {
        DB::transaction(function () use ($subject, $user, $data) {
            $record = $subject->newQuery()->whereKey($subject->id)->lockForUpdate()->firstOrFail();
            abort_unless(app(CrmAccess::class)->canWrite($user, $record), 403);
            if ($user->role === 'cs' && $record instanceof Inquiry && $record->assigned_to === null) {
                if ($record->contact_id) abort(403);
                $record->update(['assigned_to' => $user->id]);
                Ticket::where('inquiry_id', $record->id)->update(['assigned_to' => $user->id, 'contact_id' => $record->contact_id]);
                WhatsappBotConversation::where('inquiry_id', $record->id)->update(['assigned_to' => $user->id]);
            }
            if ($user->role === 'cs' && $record instanceof WhatsappBotConversation && $record->assigned_to === null) {
                abort_unless($record->handed_off && ! $record->inquiry_id, 403);
                $record->update(['assigned_to' => $user->id]);
            }
            $existing = HandlingNote::where('submission_id', $data['submission_id'])->first();
            if ($existing) {
                abort_unless($existing->subject_type === $record->getMorphClass() && (int) $existing->subject_id === $record->id && (int) $existing->user_id === $user->id && $existing->body === $data['body'] && $existing->action === $data['action'], 422);
                return;
            }
            $fail = fn ($message) => throw ValidationException::withMessages(['action' => $message]);
            if ($record instanceof WhatsappBotConversation && (! $record->handed_off || $record->inquiry_id)) $fail('Buka detail permintaan terkait untuk mencatat penanganan.');
            $state = $this->state($record);
            if ($data['action'] !== 'note') {
                if (in_array($state, ['resolved', 'cancelled', 'converted'])) $fail('Penanganan sudah ditutup. Catatan tambahan tetap dapat disimpan.');
                if ($record instanceof Inquiry && $record->intent === 'complaint') $fail('Selesaikan keluhan melalui tiket; status tiket adalah acuan.');
                if ($data['action'] === 'resolved' && $record instanceof Inquiry && $record->intent === 'order') $fail('Konfirmasi pesanan atau batalkan permintaan dengan alasan.');
                if ($data['action'] === 'cancelled' && (! $record instanceof Inquiry || $record->intent !== 'order')) $fail('Pembatalan hanya untuk permintaan pesanan.');
                $record->update(['handling_status' => $data['action']]);
            }
            HandlingNote::create(['subject_type' => $record->getMorphClass(), 'subject_id' => $record->id,
                'user_id' => $user->id, 'assigned_to' => $record->assigned_to, 'submission_id' => $data['submission_id'], 'action' => $data['action'], 'body' => $data['body']]);
        });
    }
}
