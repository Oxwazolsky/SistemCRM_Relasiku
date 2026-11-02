<?php

use App\Models\Task;
use Illuminate\Support\Facades\Schedule;

// Dashboard juga menghitung keterlambatan secara langsung; penjadwal hanya mencatat waktu pengingat.
Schedule::call(function (): void {
    Task::query()
        ->where('status', 'belum_dikerjakan')
        ->where('due_at', '<=', now())
        ->whereNull('last_reminded_at')
        ->update(['last_reminded_at' => now()]);
})->everyMinute()->name('crm:mark-due-tasks')->withoutOverlapping();
