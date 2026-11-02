@if(app(\App\Services\CrmAccess::class)->canRead(auth()->user(), $inquiry))
<a class="btn" href="{{ route('inquiries.show', $inquiry) }}">{{ !$inquiry->assigned_to ? 'Tinjau & tangani' : 'Tinjau permintaan' }} →</a>
@else
<div class="inquiry-awaiting"><strong>Ditangani CS lain</strong><small>Permintaan ini sudah memiliki penanggung jawab.</small></div>
@endif
