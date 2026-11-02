<?php

namespace App\Console\Commands;

use App\Models\{Inquiry, Opportunity, Task};
use Illuminate\Console\Command;

class AuditCrmOwnership extends Command
{
    protected $signature = 'crm:audit-ownership';

    protected $description = 'Read-only audit of legacy CRM ownership inconsistencies';

    public function handle(): int
    {
        $checks = [
            'Inquiry tertaut dengan CS berbeda dari pemilik kontak' => Inquiry::query()
                ->join('contacts', 'contacts.id', '=', 'inquiries.contact_id')
                ->where(fn ($q) => $q->whereNull('inquiries.assigned_to')->orWhereNull('contacts.owner_id')
                    ->orWhereColumn('inquiries.assigned_to', '!=', 'contacts.owner_id'))
                ->select('inquiries.id'),
            'Peluang aktif dengan CS berbeda dari pemilik kontak' => Opportunity::query()
                ->join('contacts', 'contacts.id', '=', 'opportunities.contact_id')
                ->whereNotIn('opportunities.stage', ['berhasil', 'gagal'])
                ->where(fn ($q) => $q->whereNull('contacts.owner_id')
                    ->orWhereColumn('opportunities.owner_id', '!=', 'contacts.owner_id'))
                ->select('opportunities.id'),
            'Tugas terbuka pada peluang tertutup' => Task::query()
                ->join('opportunities', 'opportunities.id', '=', 'tasks.opportunity_id')
                ->where('tasks.status', 'belum_dikerjakan')
                ->whereIn('opportunities.stage', ['berhasil', 'gagal'])->select('tasks.id'),
        ];

        foreach ($checks as $label => $query) {
            $this->line($label.': '.$query->count().' (contoh ID: '.($query->orderByRaw('1')->limit(10)->pluck('id')->implode(', ') ?: '—').')');
        }
        $this->warn('Laporan ini hanya membaca data. Tinjau kasus satu per satu; tidak ada data yang diubah.');
        return self::SUCCESS;
    }
}
