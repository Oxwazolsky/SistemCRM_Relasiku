<?php

namespace App\Http\Controllers;

use App\Models\{Contact, Inquiry, Ticket, User, WhatsappBotConversation, WhatsappBotMessage};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WhatsappHandoffController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $filter = $request->query('handling', 'unresolved');
        abort_unless(in_array($filter, ['unresolved', 'active', 'waiting', 'resolved', 'all']), 422);
        $query = WhatsappBotConversation::where(function ($q) {
            $q->where('handed_off', true)->orWhereIn('id', \App\Models\HandlingNote::where('subject_type', WhatsappBotConversation::class)->select('subject_id'));
        })->with('inquiry')->latest();
        if ($user->role === 'cs') $query->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhereNull('assigned_to'));
        $conversations = $query->get()->filter(fn ($c) => !$c->inquiry_id);
        $inquiries = app(\App\Services\CrmAccess::class)->scope(Inquiry::with('assignee'), $user, 'inquiries')->latest()->get();
        $state = fn ($r) => app(\App\Services\HandlingWorkflow::class)->state($r);
        $matches = fn ($r) => match ($filter) {
            'all' => true, 'unresolved' => in_array($state($r), ['active', 'waiting']),
            'resolved' => in_array($state($r), ['resolved', 'converted', 'cancelled']), default => $state($r) === $filter,
        };
        $counts = ['active' => 0, 'waiting' => 0, 'resolved' => 0];
        foreach ($inquiries->concat($conversations) as $item) {
            $key = in_array($state($item), ['active', 'waiting']) ? $state($item) : 'resolved';
            $counts[$key]++;
        }
        $paginate = fn ($items, $page) => new \Illuminate\Pagination\LengthAwarePaginator($items->filter($matches)->values()->forPage($request->integer($page, 1), 20), $items->filter($matches)->count(), 20, $request->integer($page, 1), ['path' => $request->url(), 'pageName' => $page, 'query' => $request->query()]);
        $history = \App\Models\HandlingNote::where('subject_type', WhatsappBotConversation::class)->with('user')->latest('id');
        if ($user->role === 'cs') $history->where('assigned_to', $user->id);
        return view('whatsapp.handoffs', ['handlingHistory' => $filter === 'all' || $filter === 'resolved' ? $history->paginate(20, ['*'], 'history_page')->withQueryString() : null, 'conversations' => $paginate($conversations, 'page'), 'handlingCounts' => $counts, 'handlingFilter' => $filter,
            'deliveryIssues' => $user->role === 'owner' ? WhatsappBotMessage::where('delivery_status', 'uncertain')
                ->with('conversation')->latest()->limit(20)->get() : collect(),
            'inquiries' => $paginate($inquiries, 'inquiries_page'),
            'staff' => in_array($user->role, ['owner', 'admin']) ? User::where('role', 'cs')->where('active', true)->orderBy('name')->get() : collect()]);
    }

    public function assign(Request $request, WhatsappBotConversation $conversation)
    {
        $data = $request->validate(['assigned_to' => ['required', 'integer', 'exists:users,id']]);
        abort_unless(in_array($request->user()->role, ['owner', 'admin'], true), 403);
        DB::transaction(function () use ($conversation, $data, $request) {
            $conversation = WhatsappBotConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            if (! $conversation->handed_off || ! User::whereKey($data['assigned_to'])->where('role', 'cs')->where('active', true)->exists()) {
                throw ValidationException::withMessages(['assigned_to' => 'Pilih CS aktif untuk percakapan yang menunggu.']);
            }
            $inquiry = $conversation->inquiry_id
                ? Inquiry::whereKey($conversation->inquiry_id)->lockForUpdate()->first()
                : null;
            if ($inquiry?->contact_id) {
                if ($request->user()->role === 'admin' && ($inquiry->assigned_to || $inquiry->contact_id)) abort(403);
                $contact = Contact::whereKey($inquiry->contact_id)->lockForUpdate()->firstOrFail();
                if ($contact->owner_id && (int) $contact->owner_id !== (int) $data['assigned_to']) {
                    throw ValidationException::withMessages(['assigned_to' => 'Inquiry terverifikasi harus tetap ditugaskan kepada pemilik kontak.']);
                }
                if (! $contact->owner_id) $contact->update(['owner_id' => $data['assigned_to']]);
            }
            if ($inquiry) {
                if ($request->user()->role === 'admin' && ($inquiry->assigned_to || $inquiry->contact_id)) abort(403);
                $inquiry->update(['assigned_to' => $data['assigned_to'], 'status' => $inquiry->contact_id ? 'terverifikasi' : 'ditugaskan']);
                Ticket::where('inquiry_id', $inquiry->id)->update(['assigned_to' => $data['assigned_to'], 'contact_id' => $inquiry->contact_id]);
            }
            $conversation->update(['assigned_to' => $data['assigned_to']]);
        });
        return redirect()->route('inbox')->with('success', 'Percakapan ditugaskan ke CS.');
    }
}
