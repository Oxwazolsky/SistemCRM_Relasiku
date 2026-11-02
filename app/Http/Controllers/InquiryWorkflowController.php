<?php

namespace App\Http\Controllers;

use App\Models\{Contact, Inquiry, User};
use App\Services\{ActivityRecorder, CrmAccess};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InquiryWorkflowController extends Controller
{
    public function __construct(private CrmAccess $access, private ActivityRecorder $log) {}

    public function assign(Request $request, Inquiry $inquiry)
    {
        abort_unless($this->access->role($request->user(), 'owner', 'admin'), 403);
        $data = $request->validate(['assigned_to' => ['required', 'integer', 'exists:users,id']]);
        abort_unless(User::whereKey($data['assigned_to'])->where('role', 'cs')->where('active', true)->exists(), 422);
        DB::transaction(function () use ($inquiry, $data, $request) {
            $inquiry = Inquiry::whereKey($inquiry->id)->lockForUpdate()->firstOrFail();
            abort_if($inquiry->status === 'ditolak', 422);
            if ($inquiry->contact_id) {
                // Admin's assignment permission must not silently change a customer profile.
                if ($request->user()->role === 'admin') {
                    throw ValidationException::withMessages(['assigned_to' => 'Admin hanya dapat menugaskan permintaan yang belum diverifikasi. Hubungi Pemilik untuk permintaan lama.']);
                }
                $contact = Contact::whereKey($inquiry->contact_id)->lockForUpdate()->firstOrFail();
                if ($contact->owner_id && (int) $contact->owner_id !== (int) $data['assigned_to']) {
                    throw ValidationException::withMessages(['assigned_to' => 'Inquiry terverifikasi harus tetap ditugaskan kepada pemilik kontak.']);
                }
                // Legacy inquiries may be linked to an unassigned contact; assign both together.
                if (! $contact->owner_id) {
                    $contact->update(['owner_id' => $data['assigned_to']]);
                    $this->log->record($contact, 'assigned', ['owner_id' => null], ['owner_id' => $data['assigned_to']]);
                }
            }
            $before = $inquiry->only(['assigned_to', 'status']);
            $inquiry->update(['assigned_to' => $data['assigned_to'], 'status' => $inquiry->contact_id ? 'terverifikasi' : 'ditugaskan']);
            \App\Models\Ticket::where('inquiry_id', $inquiry->id)->update(['assigned_to' => $inquiry->assigned_to, 'contact_id' => $inquiry->contact_id]);
            \App\Models\WhatsappBotConversation::where('inquiry_id', $inquiry->id)->update(['assigned_to' => $inquiry->assigned_to]);
            $this->log->changes($inquiry, $before);
        });
        return redirect()->route('inquiries.show', $inquiry)->with('success', 'Permintaan ditugaskan.');
    }

    public function confirmOrder(Request $request, Inquiry $inquiry)
    {
        $data = $request->validate(['final_value' => ['required', 'numeric', 'min:0', 'max:9999999999999'], 'customer_confirmed' => ['accepted']]);
        $order = DB::transaction(function () use ($request, $inquiry, $data) {
            $inquiry = Inquiry::whereKey($inquiry->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->access->canWrite($request->user(), $inquiry), 403);
            abort_unless($inquiry->status === 'terverifikasi' && $inquiry->contact_id && $inquiry->intent !== 'complaint', 422);
            abort_if(in_array($inquiry->handling_status, ['resolved', 'cancelled']), 422);
            $contact = Contact::whereKey($inquiry->contact_id)->lockForUpdate()->firstOrFail();
            abort_unless(User::whereKey($contact->owner_id)->where('role', 'cs')->where('active', true)->exists(), 422);
            $opportunity = $inquiry->opportunity_id ? \App\Models\Opportunity::findOrFail($inquiry->opportunity_id) : \App\Models\Opportunity::create([
                'contact_id' => $contact->id, 'owner_id' => $contact->owner_id, 'service_id' => $inquiry->service_id,
                'title' => 'Permintaan #'.$inquiry->id, 'quote_summary' => $inquiry->requirement, 'stage' => 'baru',
            ]);
            $inquiry->update(['opportunity_id' => $opportunity->id]);
            return app(\App\Services\OpportunityConversion::class)->convert($opportunity, (float) $data['final_value']);
        });
        return redirect()->route('orders.show', $order)->with('success', 'Pesanan dikonfirmasi.');
    }

    public function verify(Request $request, Inquiry $inquiry)
    {
        $user = $request->user();
        abort_unless($this->access->role($user, 'cs', 'owner') && $this->access->canWrite($user, $inquiry), 403);
        $data = $request->validate([
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'identity_confirmed' => ['accepted'],
        ]);
        DB::transaction(function () use ($inquiry, $data, $user) {
            $inquiry = Inquiry::whereKey($inquiry->id)->lockForUpdate()->firstOrFail();
            if (! in_array($inquiry->status, ['baru', 'ditugaskan'], true) || $inquiry->contact_id) {
                throw ValidationException::withMessages(['contact_id' => 'Permintaan ini tidak menunggu verifikasi.']);
            }
            if ($user->role === 'cs') {
                abort_unless($inquiry->assigned_to === null || (int) $inquiry->assigned_to === (int) $user->id, 403);
                $inquiry->assigned_to = $user->id;
            }
            $assignee = User::whereKey($inquiry->assigned_to)->where('role', 'cs')->where('active', true)->firstOrFail();
            if (! empty($data['contact_id'])) {
                $contact = Contact::whereKey($data['contact_id'])->lockForUpdate()->firstOrFail();
                if ((int) $contact->owner_id !== (int) $assignee->id) {
                    throw ValidationException::withMessages(['contact_id' => 'Kontak milik petugas lain. Minta Pemilik atau Admin menugaskan inquiry kepada pemilik kontak tersebut terlebih dahulu.']);
                }
            } else {
                $contact = Contact::create(['name' => $inquiry->submitted_name, 'phone' => $inquiry->submitted_phone,
                    'email' => $inquiry->submitted_email, 'source' => $inquiry->source, 'stage' => 'lead', 'owner_id' => $assignee->id]);
            }
            $before = $inquiry->only(['assigned_to', 'contact_id', 'status', 'verified_at']);
            $inquiry->update(['assigned_to' => $assignee->id, 'contact_id' => $contact->id, 'status' => 'terverifikasi', 'verified_at' => now()]);
            \App\Models\Ticket::where('inquiry_id', $inquiry->id)->update(['assigned_to' => $inquiry->assigned_to, 'contact_id' => $inquiry->contact_id]);
            \App\Models\WhatsappBotConversation::where('inquiry_id', $inquiry->id)->update(['assigned_to' => $inquiry->assigned_to]);
            $this->log->changes($inquiry, $before);
        });
        return redirect()->route('inquiries.show', $inquiry)->with('success', 'Kontak berhasil diverifikasi.');
    }
}
