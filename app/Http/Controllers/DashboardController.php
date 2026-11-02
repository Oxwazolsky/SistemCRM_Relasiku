<?php

namespace App\Http\Controllers;

use App\Models\{Inquiry, Opportunity, Order, Task, Ticket, User, Service};
use App\Services\CrmAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function __construct(private CrmAccess $access) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $workload = User::where('role', 'cs')->where('active', true)->get()->map(function ($staff) {
            return ['name' => $staff->name, 'inquiries' => Inquiry::where('assigned_to', $staff->id)->whereIn('status', ['baru', 'ditugaskan'])->count(),
                'tickets' => Ticket::where('assigned_to', $staff->id)->whereNotIn('status', ['selesai', 'ditutup'])->count(),
                'handoffs' => \App\Models\WhatsappBotConversation::where('assigned_to', $staff->id)->where('handed_off', true)->count()];
        });
        $now = now('Asia/Jakarta');
        $tasks = $this->access->scope(Task::with('opportunity'), $user, 'tasks')->where('status', 'belum_dikerjakan')
            ->where('due_at', '<', $now->copy()->endOfDay())->orderBy('due_at')->get();
        $tasks->each(function ($task) use ($now) {
            $days = (int) $task->due_at->diffInDays($now);
            $task->priority_reason = $task->due_at->lt($now) ? ($days > 0 ? 'Terlambat '.$days.' hari' : 'Terlambat kurang dari 1 hari') : 'Jatuh tempo hari ini';
        });
        $overview = $user->role === 'admin' ? [
            'Pengguna aktif' => User::where('active', true)->count(),
            'CS aktif' => User::where('role', 'cs')->where('active', true)->count(),
            'Layanan aktif' => Service::where('active', true)->count(),
            'Belum ditugaskan' => Inquiry::whereNull('assigned_to')->whereIn('status', ['baru', 'ditugaskan'])->count(),
        ] : [
            'Pelanggan' => $this->access->scope(\App\Models\Contact::query(), $user, 'contacts')->count(),
            'Pesanan aktif' => $this->access->scope(Order::query(), $user, 'orders')->whereIn('status', ['baru', 'diproses'])->count(),
            'Pesanan selesai' => $this->access->scope(Order::query(), $user, 'orders')->where('status', 'selesai')->count(),
            'Pesanan dibatalkan' => $this->access->scope(Order::query(), $user, 'orders')->where('status', 'dibatalkan')->count(),
        ];
        return view('operations', ['overview' => $overview, 'tasks' => $tasks, 'stats' => ['overdue_tasks' => $tasks->filter(fn ($task) => $task->due_at->lt($now))->count()], 'workload' => $user->role === 'owner' ? $workload : collect(),
            'pending' => $this->access->scope(Inquiry::query(), $user, 'inquiries')->whereIn('status', ['baru', 'ditugaskan'])->count(),
            'tickets' => $this->access->scope(Ticket::query(), $user, 'tickets')->whereNotIn('status', ['selesai', 'ditutup'])->count()]);
    }

    public function report(Request $request)
    {
        abort_unless($this->access->role($request->user(), 'owner'), 403);
        $filters = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'], 'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'stage' => ['nullable', Rule::in(['baru', 'dihubungi', 'penawaran', 'negosiasi', 'berhasil', 'gagal'])],
        ]);
        $from = Carbon::parse($filters['from'] ?? now('Asia/Jakarta')->startOfMonth()->toDateString(), 'Asia/Jakarta')->startOfDay();
        $to = Carbon::parse($filters['to'] ?? now('Asia/Jakarta')->toDateString(), 'Asia/Jakarta')->endOfDay();
        $opportunities = Opportunity::query()->when($filters['owner_id'] ?? null, fn ($q, $v) => $q->where('owner_id', $v))->when($filters['service_id'] ?? null, fn ($q, $v) => $q->where('service_id', $v));
        $closed = (clone $opportunities)->whereBetween('closed_at', [$from, $to]);
        $won = (clone $closed)->where('stage', 'berhasil')->count();
        $lost = (clone $closed)->where('stage', 'gagal')->count();
        $metrics = [
            'inquiries' => Inquiry::whereBetween('created_at', [$from, $to])->when($filters['service_id'] ?? null, fn ($q, $v) => $q->where('service_id', $v))->when($filters['owner_id'] ?? null, fn ($q, $v) => $q->whereHas('contact', fn ($c) => $c->where('owner_id', $v)))->count(),
            'won' => $won, 'lost' => $lost, 'conversion_percent' => $won + $lost ? round(100 * $won / ($won + $lost), 2) : 0,
            'sales_value' => Order::where('status', '!=', 'dibatalkan')->whereHas('opportunity', fn ($q) => $q->where('stage', 'berhasil')->whereBetween('closed_at', [$from, $to])->when($filters['owner_id'] ?? null, fn ($q, $v) => $q->where('owner_id', $v))->when($filters['service_id'] ?? null, fn ($q, $v) => $q->where('service_id', $v)))->sum('final_value'),
            'overdue_tasks' => Task::where('status', 'belum_dikerjakan')->where('due_at', '<', now())->whereHas('opportunity', fn ($q) => $q->when($filters['owner_id'] ?? null, fn ($q, $v) => $q->where('owner_id', $v))->when($filters['service_id'] ?? null, fn ($q, $v) => $q->where('service_id', $v)))->count(),
            'average_ticket_resolution_hours' => Ticket::whereBetween('resolved_at', [$from, $to])->whereNotNull('resolved_at')->when($filters['owner_id'] ?? null, fn ($q, $v) => $q->where('assigned_to', $v))->when($filters['service_id'] ?? null, fn ($q, $v) => $q->whereHas('order.opportunity', fn ($q) => $q->where('service_id', $v)))->get()->avg(fn ($t) => $t->created_at->diffInMinutes($t->resolved_at) / 60),
        ];
        $records = $opportunities->when($filters['stage'] ?? null, fn ($q, $v) => $q->where('stage', $v))->whereBetween('created_at', [$from, $to])->latest()->paginate(15)->withQueryString();
        $timezone = 'Asia/Jakarta';
        $stats = $metrics;
        $users = User::where('role', 'cs')->orderBy('name')->get();
        $services = Service::orderBy('name')->get();
        return view('reports.index', compact('metrics', 'stats', 'records', 'filters', 'from', 'to', 'timezone', 'users', 'services'));
    }
}
