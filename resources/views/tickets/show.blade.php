@extends('layouts.app')
@section('title', 'Keluhan #'.$record->id)
@section('content')
<div class="workspace-page" data-workspace="ticket-detail">
@php
$access = app(\App\Services\CrmAccess::class);
$intake = $record->inquiry;
$phone = (string) ($intake?->submitted_phone ?? '');
@endphp
<div class="page-head"><div><span class="eyebrow">Keluhan #{{ $record->id }}</span><h1>{{ $record->subject }}</h1><p>{{ $record->created_at?->format('d M Y H:i') }} · {{ $record->inquiry_id ? 'Otomatis via permintaan' : 'Manual / data lama' }}</p></div><a class="btn" href="{{ route('tickets.index') }}">← Antrean keluhan</a></div>
<dl class="workspace-summary"><div><dt>Status keluhan</dt><dd>{{ ucfirst($record->status) }}</dd></div><div><dt>Penanggung jawab</dt><dd>{{ $record->assignee?->name ?? 'Belum ditugaskan' }}</dd></div><div><dt>Pesanan terkait</dt><dd>{{ $record->order?->order_code ?? 'Belum ditautkan' }}</dd></div></dl>
<div class="grid grid-main"><div class="stack">
<div class="card card-pad"><h2>Ringkasan keluhan</h2><p style="white-space:pre-wrap">{{ $record->description ?: 'Belum ada rincian keluhan.' }}</p><dl class="detail-list"><div><dt>Pelanggan</dt><dd>{{ $record->contact?->name ?? $intake?->submitted_name ?? 'Pelanggan belum diketahui' }}</dd></div><div><dt>CS penanggung jawab</dt><dd>{{ $record->assignee?->name ?? 'Belum ditugaskan' }}</dd></div></dl>
@if($record->contact && $access->canRead(auth()->user(), $record->contact))<a href="{{ route('contacts.show', $record->contact) }}">Lihat pelanggan</a>@elseif($helperContext ?? null)<p class="help">Konteks pekerjaan petugas bantu: {{ $helperContext->name }} · {{ $helperContext->phone }}. Profil lengkap pelanggan tetap dibatasi untuk pemiliknya.</p>@endif
</div>
@if($record->inquiry_id)<div class="card card-pad"><h2>Data penerimaan otomatis</h2><p>Referensi permintaan #{{ $record->inquiry_id }} · {{ $intake?->source ?: 'Sumber tidak tersedia' }}</p>
@if(!$record->contact_id)<p><strong>Identitas belum ditautkan ke pelanggan terverifikasi.</strong> Nama dan nomor yang dikirim bukan bukti kepemilikan pesanan. Verifikasi sebelum membahas data pelanggan.</p><dl class="detail-list"><div><dt>Nama yang dikirim</dt><dd>{{ $intake?->submitted_name ?: 'Tidak tersedia' }}</dd></div><div><dt>Nomor yang dikirim</dt><dd>{{ $phone ?: 'Tidak tersedia' }}</dd></div><div><dt>Email yang dikirim</dt><dd>{{ $intake?->submitted_email ?: 'Tidak tersedia' }}</dd></div></dl>@endif
@if(preg_match('/^[1-9][0-9]{7,14}$/', $phone))<a class="btn" href="https://wa.me/{{ $phone }}" target="_blank" rel="noopener noreferrer">Hubungi nomor pengirim di WhatsApp</a><p class="help">Membuka WhatsApp secara manual; bukan konfirmasi identitas atau bukti pesan terkirim.</p>@endif
@if($intake && $access->canRead(auth()->user(), $intake))<a class="btn" href="{{ route('inquiries.show', $intake) }}">{{ $intake->intent === 'complaint' ? 'Lihat permintaan terkait' : 'Tinjau permintaan & verifikasi' }}</a>@else<p class="help">Verifikasi identitas tetap diperlukan sebelum membuka profil atau membahas pesanan pelanggan.</p>@endif
</div>@endif
</div><div class="card card-pad"><h2>Penanganan</h2><span class="badge" data-status="{{ $record->status }}">{{ ucfirst($record->status) }}</span><p>Baru → Ditangani → Selesai → Ditutup. Keluhan selesai dapat ditangani kembali sebelum ditutup.</p><h3>Hasil penyelesaian</h3><p style="white-space:pre-wrap">{{ $record->resolution ?: 'Belum ada hasil penyelesaian.' }}</p>@if($record->resolved_at)<p class="help">Diselesaikan {{ $record->resolved_at->format('d M Y H:i') }}</p>@endif
@if($record->status !== 'ditutup' && $access->canWrite(auth()->user(), $record))<a class="btn btn-primary" href="{{ route('tickets.edit', $record) }}">Tangani keluhan</a><p class="help">Perbarui status dan catatan. Hasil penyelesaian wajib diisi sebelum menyelesaikan atau menutup tiket.@if(auth()->user()->role === 'owner') Penanggung jawab dapat dipilih pada formulir penanganan.@endif</p>@endif
</div></div>
</div>
@endsection
