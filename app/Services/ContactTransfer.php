<?php

namespace App\Services;

use App\Models\{Contact, Inquiry, Opportunity, Task, Ticket, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContactTransfer
{
    public function __construct(private ActivityRecorder $log) {}

    public function preview(Contact $contact): array
    {
        $tasks = Task::whereHas('opportunity', fn ($q) => $q->where('contact_id', $contact->id))
            ->where('status', 'belum_dikerjakan');
        $tickets = Ticket::where('contact_id', $contact->id)->whereIn('status', ['baru', 'ditangani']);
        $previous = $contact->owner_id;
        $ids = fn ($query) => $query->orderBy('id')->pluck('id')->all();

        return [
            'contact_id' => $contact->id,
            'owner_id' => $previous,
            'inquiries' => $ids(Inquiry::where('contact_id', $contact->id)),
            'active_opportunities' => $ids(Opportunity::where('contact_id', $contact->id)->whereNotIn('stage', ['berhasil', 'gagal'])),
            'own_tasks' => $previous ? $ids((clone $tasks)->where('assigned_to', $previous)) : [],
            'helper_tasks' => $ids((clone $tasks)->when($previous, fn ($q) => $q->where('assigned_to', '!=', $previous))),
            'own_tickets' => $previous ? $ids((clone $tickets)->where('assigned_to', $previous)) : [],
            'helper_tickets' => $ids((clone $tickets)->when($previous, fn ($q) => $q->where('assigned_to', '!=', $previous))),
        ];
    }

    public function transfer(Contact $contact, User $replacement, array $expectedPreview): void
    {
        DB::transaction(function () use ($contact, $replacement, $expectedPreview) {
            $contact = Contact::whereKey($contact->id)->lockForUpdate()->firstOrFail();
            $replacement = User::whereKey($replacement->id)->lockForUpdate()->firstOrFail();
            if (! $replacement->active || $replacement->role !== 'cs') {
                throw ValidationException::withMessages(['owner_id' => 'Pilih CS yang masih aktif.']);
            }
            $previous = $contact->owner_id;
            if ($this->preview($contact) !== $expectedPreview) {
                throw ValidationException::withMessages(['preview' => 'Daftar pekerjaan atau pemilik kontak sudah berubah. Muat ulang pratinjau sebelum melanjutkan.']);
            }
            if ((int) $previous === (int) $replacement->id) {
                throw ValidationException::withMessages(['owner_id' => 'Kontak sudah dimiliki CS tersebut.']);
            }

            // Every linked inquiry must follow the contact, including historical verified requests.
            foreach (Inquiry::where('contact_id', $contact->id)->lockForUpdate()->get() as $inquiry) {
                $before = $inquiry->only(['assigned_to']);
                $inquiry->update(['assigned_to' => $replacement->id]);
                $this->log->changes($inquiry, $before);
            }
            // Keep the historical owner on closed opportunities; current contact owner controls read access.
            foreach (Opportunity::where('contact_id', $contact->id)->whereNotIn('stage', ['berhasil', 'gagal'])->lockForUpdate()->get() as $opportunity) {
                $before = $opportunity->only(['owner_id']);
                $opportunity->update(['owner_id' => $replacement->id]);
                $this->log->changes($opportunity, $before);
            }
            // Only the former contact owner's *open* assignments move. Helpers and completed work stay as-is.
            $tasks = Task::whereHas('opportunity', fn ($q) => $q->where('contact_id', $contact->id))
                ->where('assigned_to', $previous)->where('status', 'belum_dikerjakan')->lockForUpdate()->get();
            foreach ($tasks as $task) {
                $before = $task->only(['assigned_to']);
                $task->update(['assigned_to' => $replacement->id]);
                $this->log->changes($task, $before);
            }
            $tickets = Ticket::where('contact_id', $contact->id)->where('assigned_to', $previous)
                ->whereIn('status', ['baru', 'ditangani'])->lockForUpdate()->get();
            foreach ($tickets as $ticket) {
                $before = $ticket->only(['assigned_to']);
                $ticket->update(['assigned_to' => $replacement->id]);
                $this->log->changes($ticket, $before);
            }
            $contact->update(['owner_id' => $replacement->id]);
            $this->log->record($contact, 'owner_transferred', ['owner_id' => $previous], ['owner_id' => $replacement->id]);
        });
    }
}
