<?php

namespace App\Services;

use App\Models\Opportunity;
use App\Models\Order;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpportunityConversion
{
    public function __construct(private ActivityRecorder $log, private OpportunityTasks $tasks) {}

    public function convert(Opportunity $opportunity, float $finalValue): Order
    {
        return DB::transaction(function () use ($opportunity, $finalValue) {
            $opportunity = Opportunity::whereKey($opportunity->getKey())->lockForUpdate()->firstOrFail();
            if ($opportunity->stage === 'gagal') throw ValidationException::withMessages(['stage' => 'Peluang gagal tidak dapat dikonversi.']);
            if ($opportunity->stage === 'berhasil') {
                $existing = Order::where('opportunity_id', $opportunity->id)->first();
                if ($existing) return $existing;
            }
            $old = $opportunity->only(['stage', 'final_value']);
            $opportunity->update(['stage' => 'berhasil', 'final_value' => $finalValue, 'closed_at' => $opportunity->closed_at ?? now()]);
            $order = Order::firstOrCreate(['opportunity_id' => $opportunity->id], [
                'contact_id' => $opportunity->contact_id,
                'order_code' => 'ORD-'.str_pad((string) $opportunity->id, 8, '0', STR_PAD_LEFT),
                'final_value' => $finalValue, 'status' => 'baru',
            ]);
            $contact = Contact::whereKey($opportunity->contact_id)->lockForUpdate()->firstOrFail();
            if ($contact->stage !== 'pelanggan') {
                $previous = $contact->stage;
                $contact->update(['stage' => 'pelanggan']);
                $this->log->record($contact, 'stage_changed', ['stage' => $previous], ['stage' => 'pelanggan']);
            }
            if ($old !== $opportunity->only(['stage', 'final_value'])) $this->log->record($opportunity, 'converted', $old, $opportunity->only(['stage', 'final_value']));
            $this->tasks->cancelPending($opportunity);
            return $order;
        });
    }
}
