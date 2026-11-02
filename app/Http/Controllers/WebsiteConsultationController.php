<?php

namespace App\Http\Controllers;

use App\Models\{Inquiry, Service};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WebsiteConsultationController extends Controller
{
    public function show(Request $request)
    {
        return view('consultation.show', [
            'state' => $request->session()->get('website_consultation', ['step' => 'service']),
            'services' => Service::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function submit(Request $request)
    {
        $state = $request->session()->get('website_consultation', ['step' => 'service']);
        if ($request->input('action') === 'restart') {
            $request->session()->forget('website_consultation');
            return redirect()->route('consultation.show');
        }
        if ($request->input('action') === 'faq') {
            $request->session()->put('website_consultation', ['step' => 'faq']);
            return redirect()->route('consultation.show');
        }
        if ($request->input('action') === 'start') {
            $request->session()->put('website_consultation', ['step' => 'service']);
            return redirect()->route('consultation.show');
        }
        if ($request->input('action') === 'decline') {
            $request->session()->forget('website_consultation');
            return redirect()->route('consultation.show');
        }
        if ($state['step'] === 'done') return redirect()->route('consultation.show');

        // Accept only fields belonging to the current step; values in hidden inputs cannot skip consent.
        $next = match ($state['step']) {
            'service' => $this->service($request, $state),
            'name' => $this->name($request, $state),
            'phone' => $this->phone($request, $state),
            'date' => $this->date($request, $state),
            'requirement' => $this->requirement($request, $state),
            'consent' => $this->consent($request, $state),
            default => ['step' => 'service'],
        };
        $request->session()->put('website_consultation', $next);
        return redirect()->route('consultation.show');
    }

    private function service(Request $request, array $state): array
    {
        $data = $request->validate(['service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('active', true)]]);
        return ['step' => 'name', 'service_id' => (int) $data['service_id']];
    }

    private function name(Request $request, array $state): array
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:255']]);
        return array_merge($state, ['step' => 'phone', 'name' => $data['name']]);
    }

    private function phone(Request $request, array $state): array
    {
        $data = $request->validate(['phone' => ['required', 'string', 'regex:/^(?:\+62|62|0)8[0-9]{7,12}$/']]);
        return array_merge($state, ['step' => 'date', 'phone' => $data['phone']]);
    }

    private function date(Request $request, array $state): array
    {
        $data = $request->validate(['event_date' => ['nullable', 'date_format:Y-m-d']]);
        return array_merge($state, ['step' => 'requirement', 'event_date' => $data['event_date'] ?? null]);
    }

    private function requirement(Request $request, array $state): array
    {
        $data = $request->validate(['requirement' => ['required', 'string', 'min:5', 'max:5000']]);
        return array_merge($state, ['step' => 'consent', 'requirement' => $data['requirement']]);
    }

    private function consent(Request $request, array $state): array
    {
        $request->validate(['consent' => ['accepted'], 'website' => ['nullable', 'max:0']]);
        // Recheck service at submission time: a service may have been deactivated mid-conversation.
        if (! Service::whereKey($state['service_id'])->where('active', true)->exists()) {
            return ['step' => 'service'];
        }
        $inquiry = DB::transaction(fn () => Inquiry::create([
            'submitted_name' => $state['name'], 'submitted_phone' => $state['phone'],
            'service_id' => $state['service_id'], 'event_date' => $state['event_date'],
            'requirement' => $state['requirement'], 'consent_at' => now(),
            'source' => 'website_bot', 'status' => 'baru',
        ]));
        return ['step' => 'done', 'inquiry_id' => $inquiry->id];
    }
}
