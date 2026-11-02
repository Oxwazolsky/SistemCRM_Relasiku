<?php

namespace App\Http\Controllers;

use App\Models\{User, Service};
use App\Services\ActivityRecorder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminController extends Controller
{
    public function __construct(private ActivityRecorder $log) {}

    public function index(string $type)
    {
        $class = $this->model($type);
        $query = $class::query();
        if (request()->filled('search')) {
            $term = '%'.addcslashes(trim((string) request()->query('search')), '%_\\').'%';
            $query->where(function ($q) use ($type, $term) {
                $q->where('name', 'like', $term);
                if ($type === 'users') $q->orWhere('email', 'like', $term);
                if ($type === 'services') $q->orWhere('category', 'like', $term);
            });
        }
        $records = $query->latest()->paginate(15)->withQueryString();
        return view('admin.'.$type.'.index', ['records' => $records, $type => $records]);
    }

    public function form(Request $request)
    {
        $type = $request->route('type');
        $id = $request->route('id');
        $class = $this->model($type);
        $record = $id !== null ? $class::findOrFail($id) : null;
        return view('admin.'.$type.'.'.($record ? 'edit' : 'create'), ['record' => $record, $type === 'users' ? 'user' : 'service' => $record]);
    }

    public function store(Request $request, string $type)
    {
        $class = $this->model($type);
        $data = $request->validate($this->rules($type));
        $record = $class::create($data);
        $this->log->record($record, 'created', [], collect($data)->except(['password', 'password_confirmation'])->all());
        return redirect()->route('admin.'.$type.'.index')->with('success', 'Data tersimpan.');
    }

    public function update(Request $request)
    {
        $type = $request->route('type');
        $id = (int) $request->route('id');
        $class = $this->model($type);
        $record = $class::findOrFail($id);
        $data = $request->validate($this->rules($type, $id));
        if ($type === 'users' && $record->id === $request->user()->id && (array_key_exists('active', $data) && ! $data['active'] || isset($data['role']) && $data['role'] !== 'admin')) abort(422, 'Tidak dapat menonaktifkan akun admin sendiri.');
        if (empty($data['password'])) unset($data['password']);
        $before = $record->only(array_keys(collect($data)->except(['password', 'password_confirmation'])->all()));
        $record->update($data);
        $this->log->changes($record, $before);
        return redirect()->route('admin.'.$type.'.index')->with('success', 'Data diperbarui.');
    }

    private function model(string $type): string
    {
        abort_unless(in_array($type, ['users', 'services'], true), 404);
        return $type === 'users' ? User::class : Service::class;
    }

    private function rules(string $type, ?int $id = null): array
    {
        if ($type === 'services') return ['name' => [$id ? 'sometimes' : 'required', 'string', 'max:255'], 'category' => [$id ? 'sometimes' : 'required', 'string', 'max:255'], 'active' => ['sometimes', 'boolean']];
        return ['name' => [$id ? 'sometimes' : 'required', 'string', 'max:255'], 'email' => [$id ? 'sometimes' : 'required', 'email', Rule::unique('users')->ignore($id)], 'password' => [$id ? 'nullable' : 'required', 'confirmed', Password::defaults()], 'role' => [$id ? 'sometimes' : 'required', Rule::in(['admin', 'owner', 'cs'])], 'active' => ['sometimes', 'boolean']];
    }
}
