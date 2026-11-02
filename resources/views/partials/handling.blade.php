@php
    $handlingState = app(\App\Services\HandlingWorkflow::class)->state($subject);
    $labels = ['active'=>'Aktif', 'waiting'=>'Menunggu pelanggan', 'resolved'=>'Selesai', 'converted'=>'Dikonversi ke pesanan', 'cancelled'=>'Dibatalkan'];
    $isInquiry = $subject instanceof \App\Models\Inquiry;
    $notes = \App\Models\HandlingNote::where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->id)->with('user')->orderBy('id')->get();
    if (!$isInquiry && auth()->user()->role === 'cs') $notes = $notes->filter(fn ($note) => (int)$note->assigned_to === auth()->id());
@endphp
<div class="card card-pad"><h2>Penanganan: {{ $labels[$handlingState] }}</h2>
@if($isInquiry && $subject->intent === 'complaint')<p>Status penyelesaian mengikuti tiket. Gunakan detail tiket untuk menyelesaikan keluhan.</p>@endif
@foreach($notes as $note)<div class="list-row"><div><strong>{{ $note->user?->name }} · {{ $note->created_at->format('d M Y H:i:s') }}</strong><small>{{ $labels[$note->action] ?? 'Catat hasil penanganan' }}</small><p style="white-space:pre-wrap">{{ $note->body }}</p></div></div>@endforeach
@if(app(\App\Services\CrmAccess::class)->canWrite(auth()->user(), $subject) && ($isInquiry || ($subject->handed_off && !$subject->inquiry_id)))
<form method="POST" action="{{ route($isInquiry ? 'inquiries.handling' : 'whatsapp.handling', $subject) }}" class="stack">@csrf
<input type="hidden" name="submission_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<label>Hasil penanganan / alasan pembatalan<textarea class="input" name="body" maxlength="5000" required></textarea></label>
@error('body')<p class="error">{{ $message }}</p>@enderror @error('action')<p class="error">{{ $message }}</p>@enderror
<button class="btn" name="action" value="note">Catat hasil penanganan</button>
@if(in_array($handlingState, ['active', 'waiting']) && !($isInquiry && $subject->intent === 'complaint'))
<button class="btn" name="action" value="waiting">Menunggu pelanggan</button>
@if($isInquiry && $subject->intent === 'order')<button class="btn" name="action" value="cancelled">Batalkan permintaan</button>
@else<button class="btn btn-primary" name="action" value="resolved">Selesai</button>@endif
@endif
</form>@endif</div>
