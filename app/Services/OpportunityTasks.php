<?php

namespace App\Services;

use App\Models\Opportunity;
use App\Models\Task;

class OpportunityTasks
{
    public function __construct(private ActivityRecorder $log) {}

    // Call within the transaction that closes the opportunity.
    public function cancelPending(Opportunity $opportunity): void
    {
        $reason = $opportunity->stage === 'berhasil'
            ? 'Tindak lanjut penjualan dihentikan karena peluang berhasil dan pesanan dibuat.'
            : 'Tindak lanjut penjualan dihentikan karena peluang ditutup gagal.';

        foreach (Task::where('opportunity_id', $opportunity->id)
            ->where('status', 'belum_dikerjakan')->lockForUpdate()->get() as $task) {
            $before = $task->only(['status', 'cancelled_at', 'cancellation_reason']);
            $task->update(['status' => 'dibatalkan', 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
            $this->log->record($task, 'cancelled_on_opportunity_close', $before,
                $task->only(['status', 'cancelled_at', 'cancellation_reason']));
        }
    }
}
