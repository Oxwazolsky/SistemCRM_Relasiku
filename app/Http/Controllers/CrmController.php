<?php

namespace App\Http\Controllers;

use App\Models\{Contact, Inquiry, Interaction, Opportunity, Task, Order, Ticket, Service, User};
use App\Services\{CrmAccess, ActivityRecorder, OpportunityConversion, OpportunityTasks};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CrmController extends Controller
{
    private const MODELS = [
        'contacts' => Contact::class, 'inquiries' => Inquiry::class,
        'interactions' => Interaction::class, 'opportunities' => Opportunity::class,
        'tasks' => Task::class, 'orders' => Order::class, 'tickets' => Ticket::class,
    ];

    public function __construct(private CrmAccess $access, private ActivityRecorder $log) {}

    private function model(string $type): string
    {
        abort_unless(isset(self::MODELS[$type]), 404);
        return self::MODELS[$type];
    }

    private function record(Request $request, string $type, int $id, bool $write = false): Model
    {
        $class = $this->model($type);
        $record = $class::findOrFail($id);
        abort_unless($write ? $this->access->canWrite($request->user(), $record) : $this->access->canRead($request->user(), $record), 403);
        return $record;
    }

    public function index(Request $request, string $type)
    {
        $class = $this->model($type);
        $query = $this->access->scope($class::query(), $request->user(), $type);
        $statusCounts = $type === 'tickets'
            ? (clone $query)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')
            : collect();
        if ($type === 'tickets') {
            $query->with(['contact', 'inquiry', 'assignee']);
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($type, $search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                match ($type) {
                    'contacts' => $q->where('name', 'like', $term)->orWhere('phone', 'like', $term)->orWhere('email', 'like', $term),
                    'inquiries' => $q->where('submitted_name', 'like', $term)->orWhere('submitted_phone', 'like', $term)->orWhere('requirement', 'like', $term),
                    'opportunities' => $q->where('title', 'like', $term),
                    'tasks' => $q->where('note', 'like', $term)->orWhereHas('opportunity', fn ($opportunity) => $opportunity->where('title', 'like', $term)),
                    'orders' => $q->where('order_code', 'like', $term),
                    'tickets' => $q->where('subject', 'like', $term),
                    default => $q->whereRaw('1 = 0'),
                };
            });
        }
        if ($type === 'inquiries' && $request->filled('phone')) $query->where('submitted_phone', $request->query('phone'));
        if ($request->filled('stage') && in_array($type, ['contacts', 'opportunities'], true)) $query->where('stage', $request->query('stage'));
        if ($request->filled('status') && in_array($type, ['tasks', 'orders', 'tickets'], true)) $query->where('status', $request->query('status'));
        if ($type === 'tasks') {
            $query->orderByRaw("CASE WHEN tasks.status = 'belum_dikerjakan' AND tasks.due_at < ? THEN 0 WHEN tasks.status = 'belum_dikerjakan' AND tasks.due_at < ? THEN 1 WHEN tasks.status = 'belum_dikerjakan' THEN 2 ELSE 3 END", [now(), now()->endOfDay()])
                ->orderBy('tasks.due_at')
                ->orderByDesc(Opportunity::select('estimated_value')->whereColumn('opportunities.id', 'tasks.opportunity_id')->limit(1));
        } else $query->latest();
        $records = $query->paginate(15)->withQueryString();
        if ($type === 'tasks') $records->getCollection()->transform(fn ($task) => $this->prioritize($task));
        return view($type.'.index', ['records' => $records, $type => $records, 'statusCounts' => $statusCounts]);
    }

    public function show(Request $request, int $id)
    {
        $type = $request->route('type');
        $record = $this->record($request, $type, $id);
        if ($type === 'tasks') $record->load('opportunity');
        if ($type === 'contacts') {
            $record->load(['inquiries' => fn ($query) => $request->user()->role === 'cs'
                ? $query->where('assigned_to', $request->user()->id) : $query,
                'interactions', 'opportunities', 'orders', 'tickets']);
        }
        $helperContext = null;
        if ($request->user()->role === 'cs' && in_array($type, ['tasks', 'tickets'], true)) {
            $contactId = $type === 'tasks' ? $record->opportunity?->contact_id : $record->contact_id;
            if ($contactId && ! Contact::whereKey($contactId)->where('owner_id', $request->user()->id)->exists()) {
                $helperContext = Contact::whereKey($contactId)->first(['id', 'name', 'phone']);
            }
        }
        $duplicates = $type === 'inquiries' && $request->user()->role === 'owner' && ! $record->contact_id && $record->submitted_phone
            ? Contact::where('phone', $record->submitted_phone)->orderBy('name')->limit(20)->get()
            : collect();
        return view($type.'.show', ['record' => $record, $this->viewKey($type) => $record, 'duplicates' => $duplicates, 'helperContext' => $helperContext]);
    }

    public function create(Request $request, string $type)
    {
        abort_unless($this->access->role($request->user(), 'cs', 'owner'), 403);
        abort_if($type === 'orders', 403);
        $this->model($type);
        abort_if(in_array($type, ['interactions', 'orders'], true), 403);
        return view($type.'.create', $this->formData($request, $type));
    }

    public function edit(Request $request, int $id)
    {
        $type = $request->route('type');
        $record = $this->record($request, $type, $id, true);
        abort_if(in_array($type, ['interactions', 'inquiries'], true), 404);
        abort_if($type === 'opportunities' && in_array($record->stage, ['berhasil', 'gagal'], true), 403);
        abort_if($type === 'orders' && in_array($record->status, ['selesai', 'dibatalkan'], true), 403);
        abort_if($type === 'tickets' && $record->status === 'ditutup', 403);
        abort_if($type === 'tasks' && ($record->status !== 'belum_dikerjakan' || Opportunity::whereKey($record->opportunity_id)->whereIn('stage', ['berhasil', 'gagal'])->exists()), 403);
        return view($type.'.edit', $this->formData($request, $type) + ['record' => $record, $this->viewKey($type) => $record]);
    }

    public function store(Request $request, string $type)
    {
        abort_unless($this->access->role($request->user(), 'cs', 'owner'), 403);
        abort_if($type === 'orders', 403);
        $this->model($type);
        $data = $request->validate($this->rules($type));
        $this->checkReferences($request, $type, $data);
        if ($type === 'opportunities' && in_array($data['stage'] ?? '', ['berhasil', 'gagal'], true)) throw ValidationException::withMessages(['stage' => 'Buat peluang aktif terlebih dahulu sebelum menutupnya.']);
        if ($type === 'inquiries') {
            $contact = Contact::findOrFail($data['contact_id']);
            abort_unless($contact->owner_id && User::whereKey($contact->owner_id)->where('role', 'cs')->where('active', true)->exists(), 422);
            $data += ['submitted_name' => $contact->name, 'submitted_phone' => $contact->phone,
                'submitted_email' => $contact->email, 'assigned_to' => $contact->owner_id, 'status' => 'terverifikasi', 'verified_at' => now()];
            $data['consent_at'] = now();
        }
        if ($type === 'interactions') $data['user_id'] = $request->user()->id;
        if ($type === 'contacts' && $request->user()->role === 'owner' && empty($data['owner_id'])) {
            throw ValidationException::withMessages(['owner_id' => 'Pilih CS aktif sebagai penanggung jawab kontak.']);
        }
        if ($type === 'contacts' || $type === 'opportunities') $data['owner_id'] = $request->user()->role === 'cs' ? $request->user()->id : ($data['owner_id'] ?? $request->user()->id);
        if ($type === 'opportunities') {
            $contactOwner = Contact::findOrFail($data['contact_id'])->owner_id;
            abort_unless($contactOwner && User::whereKey($contactOwner)->where('role', 'cs')->where('active', true)->exists(), 422);
            if ($request->user()->role === 'cs') abort_unless((int) $contactOwner === (int) $request->user()->id, 403);
            if ($request->user()->role === 'owner' && isset($data['owner_id']) && (int) $data['owner_id'] !== (int) $contactOwner) {
                throw ValidationException::withMessages(['owner_id' => 'Penanggung jawab peluang harus sama dengan pemilik kontak.']);
            }
            $data['owner_id'] = $contactOwner;
        }
        if (in_array($type, ['tasks', 'tickets'], true)) {
            if ($request->user()->role === 'owner' && empty($data['assigned_to'])) {
                throw ValidationException::withMessages(['assigned_to' => 'Pilih CS aktif sebagai penanggung jawab.']);
            }
            $data['assigned_to'] = $request->user()->role === 'cs' ? $request->user()->id : $data['assigned_to'];
        }
        if ($type === 'tickets' && in_array($data['status'] ?? '', ['selesai', 'ditutup'], true)) throw ValidationException::withMessages(['status' => 'Buat tiket terbuka terlebih dahulu.']);
        if ($type === 'tasks') {
            if (isset($data['status']) && $data['status'] !== 'belum_dikerjakan') {
                throw ValidationException::withMessages(['status' => 'Tugas baru harus berstatus belum dikerjakan.']);
            }
            $data['status'] = 'belum_dikerjakan';
            if (Opportunity::whereKey($data['opportunity_id'])->whereIn('stage', ['berhasil', 'gagal'])->exists()) {
                throw ValidationException::withMessages(['opportunity_id' => 'Peluang sudah ditutup.']);
            }
        }
        if ($type === 'tickets' && ! empty($data['order_id'])) {
            abort_unless($this->access->scope(Order::whereKey($data['order_id']), $request->user(), 'orders')->where('contact_id', $data['contact_id'])->exists(), 422);
        }
        if ($type === 'opportunities') {
            $record = DB::transaction(function () use ($data, $request) {
                $task = $request->validate(['due_at' => ['nullable', 'date'], 'note' => ['nullable', 'string', 'max:5000']]);
                $record = Opportunity::create($data);
                if (! empty($task['due_at'])) Task::create(['opportunity_id' => $record->id, 'assigned_to' => $record->owner_id, 'due_at' => $task['due_at'], 'note' => $task['note'] ?? null, 'status' => 'belum_dikerjakan']);
                return $record;
            });
        } else {
            $class = $this->model($type);
            $record = $class::create($data);
        }
        $this->log->record($record, 'created', [], $data);
        return redirect()->route($type === 'interactions' ? 'contacts.show' : $type.'.show', $type === 'interactions' ? $record->contact_id : $record->id)->with('success', 'Data tersimpan.');
    }

    public function update(Request $request, int $id, OpportunityConversion $conversion, OpportunityTasks $opportunityTasks)
    {
        $type = $request->route('type');
        $record = $this->record($request, $type, $id, true);
        $data = $request->validate($this->rules($type, true));
        if ($type === 'contacts' && array_key_exists('owner_id', $data)) {
            throw ValidationException::withMessages(['owner_id' => 'Gunakan aksi pengalihan penanggung jawab untuk mengganti pemilik kontak.']);
        }
        if ($type === 'opportunities' && array_key_exists('owner_id', $data)
            && (int) $data['owner_id'] !== (int) $record->owner_id) {
            throw ValidationException::withMessages(['owner_id' => 'Penanggung jawab peluang mengikuti pemilik kontak. Gunakan aksi pengalihan kontak.']);
        }
        if ($request->user()->role === 'cs') unset($data['owner_id'], $data['assigned_to']);
        if (in_array($type, ['inquiries', 'interactions', 'opportunities', 'tickets'], true) && isset($data['contact_id']) && (int) $data['contact_id'] !== (int) $record->contact_id) throw ValidationException::withMessages(['contact_id' => 'Kontak terkait tidak dapat diubah.']);
        if ($type === 'tasks' && isset($data['opportunity_id']) && (int) $data['opportunity_id'] !== (int) $record->opportunity_id) throw ValidationException::withMessages(['opportunity_id' => 'Peluang terkait tidak dapat diubah.']);
        $this->checkReferences($request, $type, $data);
        if ($type === 'opportunities' && in_array($record->stage, ['berhasil', 'gagal'], true)) throw ValidationException::withMessages(['stage' => 'Peluang yang sudah ditutup tidak dapat diubah.']);
        if ($type === 'orders') {
            if (in_array($record->status, ['selesai', 'dibatalkan'], true)) throw ValidationException::withMessages(['status' => 'Pesanan yang sudah selesai atau dibatalkan tidak dapat diubah.']);
            $next = $data['status'] ?? $record->status;
            $allowed = $record->status === 'baru' ? ['baru', 'diproses', 'dibatalkan'] : ['diproses', 'selesai', 'dibatalkan'];
            if (! in_array($next, $allowed, true)) throw ValidationException::withMessages(['status' => 'Perubahan status pesanan tidak valid.']);
            if ($next === 'dibatalkan') {
                abort_unless($this->access->role($request->user(), 'owner'), 403);
                $reason = $request->validate(['cancellation_reason' => ['required', 'string', 'max:5000']]);
                $data += ['cancellation_reason' => $reason['cancellation_reason'], 'cancelled_at' => now()];
            }
        }
        if ($type === 'tasks') {
            $next = $data['status'] ?? $record->status;
            if ($record->status !== 'belum_dikerjakan') throw ValidationException::withMessages(['status' => 'Tugas yang sudah ditutup tidak dapat diubah.']);
            if (($data['opportunity_id'] ?? $record->opportunity_id) && Opportunity::whereKey($record->opportunity_id)->whereIn('stage', ['berhasil', 'gagal'])->exists()) throw ValidationException::withMessages(['status' => 'Tugas pada peluang tertutup tidak dapat diubah.']);
        }
        if ($type === 'tickets') {
            $next = $data['status'] ?? $record->status;
            $allowed = ['baru' => ['baru', 'ditangani'], 'ditangani' => ['ditangani', 'selesai'], 'selesai' => ['selesai', 'ditangani', 'ditutup'], 'ditutup' => ['ditutup']];
            if (! in_array($next, $allowed[$record->status] ?? [], true) || $record->status === 'ditutup') throw ValidationException::withMessages(['status' => 'Perubahan status tiket tidak valid.']);
        }
        if ($type === 'opportunities' && ($data['stage'] ?? null) === 'gagal' && ! in_array($record->stage, ['baru', 'dihubungi', 'penawaran', 'negosiasi'], true)) throw ValidationException::withMessages(['stage' => 'Transisi peluang tidak valid.']);
        if ($type === 'opportunities' && ($data['stage'] ?? null) === 'berhasil') {
            $value = $request->validate(['final_value' => ['required', 'numeric', 'min:0']])['final_value'];
            // Persist editable quote fields before conversion, within one transaction.
            DB::transaction(function () use ($record, $data, $conversion, $value) {
                unset($data['stage'], $data['final_value']);
                $record->update($data);
                $conversion->convert($record, (float) $value);
            });
        } else {
            if ($type === 'tickets' && in_array($data['status'] ?? '', ['selesai', 'ditutup'], true)) {
                if (empty($data['resolution']) && empty($record->resolution)) throw ValidationException::withMessages(['resolution' => 'Hasil penyelesaian wajib diisi.']);
                $data['resolved_at'] = $record->resolved_at ?? now();
            }
            if ($type === 'opportunities' && ($data['stage'] ?? '') === 'gagal') $data['closed_at'] = now();
            if ($type === 'opportunities' && isset($data['stage']) && ! in_array($data['stage'], ['berhasil', 'gagal'])) $data['closed_at'] = null;
            if ($type === 'tickets' && isset($data['status']) && in_array($data['status'], ['baru', 'ditangani'])) $data['resolved_at'] = null;
            if ($type === 'tickets' && ! empty($data['order_id'])) abort_unless($this->access->scope(Order::whereKey($data['order_id']), $request->user(), 'orders')->where('contact_id', $record->contact_id)->exists(), 422);
            if ($type === 'opportunities' && ($data['stage'] ?? null) === 'gagal') {
                DB::transaction(function () use ($record, $data, $opportunityTasks) {
                    $locked = Opportunity::whereKey($record->id)->lockForUpdate()->firstOrFail();
                    if (in_array($locked->stage, ['berhasil', 'gagal'], true)) {
                        throw ValidationException::withMessages(['stage' => 'Peluang yang sudah ditutup tidak dapat diubah.']);
                    }
                    $before = $locked->only(array_keys($data));
                    $locked->update($data);
                    $this->log->changes($locked, $before);
                    $opportunityTasks->cancelPending($locked);
                });
            } elseif ($type === 'tickets' && $request->user()->role === 'cs') {
                DB::transaction(function () use ($record, $data, $request) {
                    $locked = Ticket::whereKey($record->id)->lockForUpdate()->firstOrFail();
                    abort_unless($this->access->canWrite($request->user(), $locked), 403);

                    $next = $data['status'] ?? $locked->status;
                    $allowed = ['baru' => ['baru', 'ditangani'], 'ditangani' => ['ditangani', 'selesai'], 'selesai' => ['selesai', 'ditangani', 'ditutup'], 'ditutup' => ['ditutup']];
                    if (! in_array($next, $allowed[$locked->status] ?? [], true) || $locked->status === 'ditutup') {
                        throw ValidationException::withMessages(['status' => 'Perubahan status tiket tidak valid. Tiket mungkin telah diperbarui oleh petugas lain.']);
                    }
                    if (in_array($next, ['selesai', 'ditutup'], true) && empty($data['resolution']) && empty($locked->resolution)) {
                        throw ValidationException::withMessages(['resolution' => 'Hasil penyelesaian wajib diisi.']);
                    }

                    $before = $locked->only(array_unique([...array_keys($data), 'assigned_to']));
                    $data['assigned_to'] = $locked->assigned_to ?? $request->user()->id;
                    if (in_array($next, ['selesai', 'ditutup'], true)) $data['resolved_at'] = $locked->resolved_at ?? now();
                    if (in_array($next, ['baru', 'ditangani'], true)) $data['resolved_at'] = null;
                    $locked->update($data);
                    $this->log->changes($locked, $before);
                });
            } else {
                $before = $record->only(array_keys($data));
                $record->update($data);
                $this->log->changes($record, $before);
            }
        }
        return redirect()->route($type.'.show', $id)->with('success', 'Data diperbarui.');
    }

    private function checkReferences(Request $request, string $type, array $data): void
    {
        if (isset($data['contact_id'])) $this->access->contact($request->user(), (int) $data['contact_id']);
        if (isset($data['opportunity_id'])) $this->access->opportunity($request->user(), (int) $data['opportunity_id']);
        if ($type === 'contacts' && isset($data['owner_id']) && $request->user()->role === 'owner') abort_unless(User::whereKey($data['owner_id'])->where('active', true)->where('role', 'cs')->exists(), 422);
        if ($type === 'opportunities' && isset($data['owner_id']) && $request->user()->role === 'owner') abort_unless(User::whereKey($data['owner_id'])->where('active', true)->where('role', 'cs')->exists(), 422);
        if (isset($data['assigned_to']) && $request->user()->role === 'owner') abort_unless(User::whereKey($data['assigned_to'])->where('active', true)->where('role', 'cs')->exists(), 422);
        if ($type === 'tasks' && $request->user()->role === 'cs' && isset($data['opportunity_id'])) $this->access->opportunity($request->user(), (int) $data['opportunity_id']);
    }

    private function rules(string $type, bool $update = false): array
    {
        $required = $update ? 'sometimes' : 'required';
        return match ($type) {
            'contacts' => ['name' => [$required, 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:255'], 'organization' => ['nullable', 'string', 'max:255'], 'source' => ['nullable', 'string', 'max:50'], 'stage' => ['sometimes', Rule::in(['lead', 'prospek', 'pelanggan', 'tidak_aktif'])], 'owner_id' => ['sometimes', 'integer', 'exists:users,id']],
            'inquiries' => ['contact_id' => [$required, 'integer', 'exists:contacts,id'], 'service_id' => [$required, 'integer', 'exists:services,id'], 'event_date' => ['nullable', 'date'], 'requirement' => [$required, 'string', 'max:5000'], 'source' => ['nullable', 'string', 'max:50']],
            'interactions' => ['contact_id' => [$required, 'integer', 'exists:contacts,id'], 'channel' => [$required, 'string', 'max:50'], 'occurred_at' => [$required, 'date'], 'summary' => [$required, 'string', 'max:5000'], 'next_step' => ['nullable', 'string', 'max:5000']],
            'opportunities' => ['contact_id' => [$required, 'integer', 'exists:contacts,id'], 'service_id' => [$required, 'integer', 'exists:services,id'], 'owner_id' => ['sometimes', 'integer', 'exists:users,id'], 'title' => [$required, 'string', 'max:255'], 'estimated_value' => ['nullable', 'numeric', 'min:0'], 'quote_summary' => ['nullable', 'string', 'max:5000'], 'stage' => ['sometimes', Rule::in(['baru', 'dihubungi', 'penawaran', 'negosiasi', 'berhasil', 'gagal'])], 'lost_reason' => [Rule::requiredIf(fn () => request('stage') === 'gagal'), 'nullable', 'string', 'max:5000']],
            'tasks' => ['opportunity_id' => [$required, 'integer', 'exists:opportunities,id'], 'assigned_to' => ['sometimes', 'integer', 'exists:users,id'], 'due_at' => [$required, 'date'], 'status' => ['sometimes', Rule::in(['belum_dikerjakan', 'selesai', 'dibatalkan'])], 'note' => ['nullable', 'string', 'max:5000']],
            'orders' => ['status' => ['sometimes', Rule::in(['baru', 'diproses', 'selesai', 'dibatalkan'])]],
            'tickets' => ['contact_id' => [$required, 'integer', 'exists:contacts,id'], 'order_id' => ['nullable', 'integer', 'exists:orders,id'], 'assigned_to' => ['sometimes', 'integer', 'exists:users,id'], 'subject' => [$required, 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'], 'resolution' => ['nullable', 'string', 'max:5000'], 'status' => ['sometimes', Rule::in(['baru', 'ditangani', 'selesai', 'ditutup'])]],
        };
    }

    private function viewKey(string $type): string
    {
        return ['inquiries' => 'inquiry', 'opportunities' => 'opportunity'][$type] ?? rtrim($type, 's');
    }

    private function formData(Request $request, string $type): array
    {
        $user = $request->user();
        $scope = fn (string $model, string $resource) => $this->access->scope($model::query(), $user, $resource);
        return [
            'contacts' => $scope(Contact::class, 'contacts')->orderBy('name')->get(),
            'services' => Service::where('active', true)->orderBy('name')->get(),
            'users' => User::where('active', true)->where('role', 'cs')->orderBy('name')->get(),
            'opportunities' => $scope(Opportunity::class, 'opportunities')->orderBy('title')->get(),
            'orders' => $scope(Order::class, 'orders')->orderBy('order_code')->get(),
        ];
    }

    public function prioritize(Task $task): Task
    {
        $due = \Illuminate\Support\Carbon::parse($task->due_at, 'Asia/Jakarta');
        $task->priority_reason = $due->isPast() ? 'Terlambat '.$due->diffInDays(now('Asia/Jakarta')).' hari' : ($due->isToday() ? 'Jatuh tempo hari ini' : 'Mendatang');
        return $task;
    }
}
