<?php

namespace App\Http\Controllers;

use App\Models\{Inquiry, Service};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicInquiryController extends Controller
{
    public function create()
    {
        return view('inquiries.public', ['services' => Service::where('active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'], 'service_id' => ['required', 'integer', 'exists:services,id'],
            'event_date' => ['nullable', 'date'], 'requirement' => ['required', 'string', 'max:5000'],
            'intent' => ['nullable', 'in:consultation,order,complaint'],
            'consent' => ['accepted'], 'website' => ['nullable', 'max:0'],
        ]);
        abort_unless(Service::whereKey($data['service_id'])->where('active', true)->exists(), 422);
        // The request is not a new customer until a staff member verifies the identity.
        $inquiry = DB::transaction(function () use ($data) {
                $inquiry = Inquiry::create(['submitted_name' => $data['name'], 'submitted_phone' => $data['phone'],
                    'submitted_email' => $data['email'] ?? null, 'service_id' => $data['service_id'],
                    'event_date' => $data['event_date'] ?? null, 'requirement' => $data['requirement'], 'intent' => $data['intent'] ?? 'consultation',
                    'consent_at' => now(), 'source' => 'website', 'status' => 'baru']);
                return $inquiry;
            });
        $message = 'Permintaan telah diterima. Tim kami akan segera menghubungi Anda.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 201);
        }

        return redirect()->route('inquiry.create')->with('success', $message);
    }
}
