@extends('layouts.app')
@section('title', 'Detail permintaan')
@section('content')
<div class="workspace-page" data-workspace="inquiry-detail">
<div class="page-head"><div><span class="eyebrow">Penerimaan / Verifikasi pelanggan</span><h1>Detail permintaan</h1><p>Verifikasi identitas sebelum membuat kontak baru atau memakai kontak lama.</p></div><a class="btn" href="{{ route('inbox') }}">← Kembali</a></div>
<dl class="workspace-summary"><div><dt>Referensi permintaan</dt><dd>#{{ $record->id }}</dd></div><div><dt>Status verifikasi</dt><dd>{{ ucfirst($record->status) }}</dd></div><div><dt>Penanggung jawab</dt><dd>{{ $record->assignee?->name ?: 'Belum ditugaskan' }}</dd></div></dl>
<div class="grid grid-main"><div class="stack"><div class="card card-pad">
    <div class="section-head"><h2>Permintaan #{{ $record->id }}</h2><span class="badge">{{ ucfirst($record->status) }}</span></div>
    <dl class="detail-list">
        <div><dt>Nama pengirim</dt><dd>{{ $record->submitted_name ?: $record->contact?->name }}</dd></div>
        <div><dt>Telepon</dt><dd>{{ $record->submitted_phone ?: $record->contact?->phone }}</dd></div>
        <div><dt>Email</dt><dd>{{ $record->submitted_email ?: '—' }}</dd></div>
        <div><dt>Layanan</dt><dd>{{ $record->service?->name }}</dd></div>
        <div><dt>Tanggal acara</dt><dd>{{ $record->event_date?->format('d M Y') ?: '—' }}</dd></div>
        <div><dt>Kebutuhan</dt><dd>{{ $record->requirement }}</dd></div>
        <div><dt>Petugas</dt><dd>{{ $record->assignee?->name ?: 'Belum ditugaskan' }}</dd></div>
        <div><dt>Kontak terverifikasi</dt><dd>@if($record->contact)@if(auth()->user()->role === 'admin'){{ $record->contact->name }}@else<a href="{{ route('contacts.show', $record->contact) }}">{{ $record->contact->name }}</a>@endif @else Belum ada @endif</dd></div>
        @if($record->intent === 'complaint')<div><dt>Tiket otomatis</dt><dd>@foreach(\App\Models\Ticket::where('inquiry_id', $record->id)->get() as $ticket)<a href="{{ route('tickets.show', $ticket) }}">Tiket #{{ $ticket->id }} · {{ $ticket->status }}</a>@endforeach</dd></div>@endif
    </dl>
</div>
@if(($record->status !== 'ditolak') && (auth()->user()->role === 'owner' || (auth()->user()->role === 'admin' && !$record->contact_id)))
<div class="card card-pad"><h2>{{ $record->assigned_to ? 'Ubah penugasan CS' : 'Penugasan opsional' }}</h2><p class="help">CS dapat langsung mengambil dan menangani permintaan ini. Penugasan manual hanya diperlukan untuk pembagian atau pengalihan pekerjaan.</p>
    <form method="POST" action="{{ route('inquiries.assign', $record) }}" class="stack">@csrf @method('PUT')
        <div class="field"><label for="assigned_to">Petugas</label><select class="input" id="assigned_to" name="assigned_to" required><option value="">Pilih petugas</option>
        @foreach(\App\Models\User::where('role', 'cs')->where('active', true)->orderBy('name')->get() as $staff)<option value="{{ $staff->id }}" @selected($record->assigned_to === $staff->id)>{{ $staff->name }}</option>@endforeach</select>@error('assigned_to')<span class="error">{{ $message }}</span>@enderror</div>
        <button class="btn btn-primary" type="submit">Simpan penugasan</button>
    </form>
</div>
@endif
</div><div class="stack">
@if($record->intent !== 'complaint' && in_array($record->status, ['baru', 'ditugaskan'], true) && (auth()->user()->role === 'owner' || (auth()->user()->role === 'cs' && (!$record->assigned_to || $record->assigned_to === auth()->id()))))
<div class="card card-pad"><h2>Verifikasi kontak</h2><p class="help">Pilih kontak lama yang telah dipastikan identitasnya, atau buat kontak baru dari identitas pengirim. Nomor sama tidak otomatis berarti orang yang sama.</p>
    @if(auth()->user()->role === 'owner' && $duplicates->isNotEmpty())<h3>Kandidat dengan nomor sama</h3>@foreach($duplicates as $candidate)<div class="list-row"><strong>{{ $candidate->name }}</strong><small>{{ $candidate->phone }} · CS: {{ $candidate->owner?->name ?: 'Belum ditugaskan' }}</small></div>@endforeach
    @endif
    <form method="POST" action="{{ route('inquiries.verify', $record) }}" class="stack">@csrf @method('PUT')
        <div class="field"><label for="contact_id">Kontak lama (kosongkan untuk membuat baru)</label><select class="input" id="contact_id" name="contact_id"><option value="">Buat kontak baru</option>
        @foreach(\App\Models\Contact::where('owner_id', $record->assigned_to)->orderBy('name')->get() as $candidate)<option value="{{ $candidate->id }}" @selected(old('contact_id') == $candidate->id)>{{ $candidate->name }} · {{ $candidate->phone }}</option>@endforeach</select>@error('contact_id')<span class="error">{{ $message }}</span>@enderror</div>
        <div class="field"><label for="identity_confirmed" style="display:flex;align-items:flex-start;gap:10px;cursor:pointer"><input id="identity_confirmed" name="identity_confirmed" type="checkbox" value="1" @checked(old('identity_confirmed')) required style="margin-top:4px"><span>Saya telah memastikan identitas pengirim dan kontak yang dipilih sudah sesuai.</span></label>@error('identity_confirmed')<span class="error">{{ $message }}</span>@enderror</div>
        <button class="btn btn-primary" type="submit">Simpan hasil verifikasi</button>
    </form>
</div>
@endif
</div></div>
@if(auth()->user()->role !== 'admin')
@php $phone = preg_replace('/\D/', '', $record->submitted_phone ?: $record->contact?->phone ?: ''); if(str_starts_with($phone, '0')) $phone = '62'.substr($phone, 1); @endphp
@if(preg_match('/^[1-9][0-9]{7,14}$/', $phone))<a class="btn" href="https://wa.me/{{ $phone }}" target="_blank" rel="noopener noreferrer">Balas lewat WhatsApp</a>@endif
@if($record->status === 'terverifikasi' && $record->intent !== 'complaint')
@php $existingOrder = $record->opportunity_id ? \App\Models\Order::where('opportunity_id', $record->opportunity_id)->first() : null; @endphp
<div class="card card-pad"><h2>{{ $existingOrder ? 'Pesanan terhubung' : 'Konfirmasi pesanan' }}</h2>
@if($existingOrder)<p>Pesanan sudah tercatat. Buka untuk melihat status pemenuhan tanpa mengisi ulang harga.</p><a class="btn btn-primary" href="{{ route('orders.show', $existingOrder) }}">Buka pesanan {{ $existingOrder->order_code }} →</a>
@elseif(!in_array($record->handling_status, ['resolved', 'cancelled']))<p>Kebutuhan di atas disalin otomatis. Pastikan harga dan pesanan telah disetujui pelanggan.</p><form class="stack" method="POST" action="{{ route('inquiries.confirm-order', $record) }}">@csrf<div class="field"><label for="final_value">Nilai akhir (Rp)</label><input id="final_value" class="input" type="number" min="0" max="9999999999999" step="0.01" name="final_value" value="{{ old('final_value') }}" required></div><label><input type="checkbox" name="customer_confirmed" value="1" @checked(old('customer_confirmed')) required> Pelanggan telah menyetujui pesanan dan harga.</label><button class="btn btn-primary" type="submit">Konfirmasi pesanan</button></form>@endif</div>
@endif
@endif
@include('partials.handling', ['subject' => $record])
</div>
@endsection
