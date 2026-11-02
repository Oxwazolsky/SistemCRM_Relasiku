@extends('layouts.app')
@section('title', 'Inbox WhatsApp')
@section('content')
<div class="workspace-page inbox-workspace" data-workspace="inbox">
<header class="page-head inbox-heading">
    <div><span class="eyebrow">Komunikasi pelanggan</span><h1>Inbox WhatsApp</h1><p>Prioritaskan permintaan dan catat tindak lanjut pelanggan.</p></div>
    @if(auth()->user()->role !== 'admin')
    <nav class="workspace-actions" aria-label="Aksi inbox">
        <a class="btn btn-primary" href="{{ route('inquiries.create') }}"><svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg><span>Tambahkan permintaan manual</span></a>
    </nav>
    @endif
</header>
<div class="inbox-metrics">
@foreach(['active'=>'Aktif', 'waiting'=>'Menunggu pelanggan', 'resolved'=>'Selesai / riwayat'] as $value=>$label)
<a class="card inbox-metric" href="{{ route('inbox', ['handling'=>$value]) }}"><span>{{ $label }}</span><strong>{{ $handlingCounts[$value] }}</strong><small>{{ $value === 'resolved' ? 'Termasuk konversi & pembatalan' : 'Permintaan & serah-terima langsung' }}</small></a>
@endforeach
</div>
<section class="card inbox-queue" aria-labelledby="inbox-requests">
<div class="inbox-section-head"><div><h2 id="inbox-requests">Permintaan pelanggan</h2><p>{{ $inquiries->total() }} permintaan dalam tampilan ini</p></div><form method="GET" class="inbox-filter"><label for="inbox-handling">Penanganan</label><select id="inbox-handling" name="handling" class="input">@foreach(['unresolved'=>'Belum selesai', 'active'=>'Aktif', 'waiting'=>'Menunggu pelanggan', 'resolved'=>'Selesai / riwayat', 'all'=>'Semua'] as $value=>$label)<option value="{{ $value }}" @selected($handlingFilter === $value)>{{ $label }}</option>@endforeach</select><button class="btn">Terapkan</button></form></div>
<nav class="inbox-tabs" aria-label="Status inbox">@foreach(['unresolved'=>'Belum selesai', 'waiting'=>'Menunggu pelanggan', 'resolved'=>'Riwayat', 'all'=>'Semua'] as $value=>$label)<a href="{{ route('inbox', ['handling'=>$value]) }}" @if($handlingFilter === $value) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
@php
$intentLabels = ['consultation'=>'Konsultasi', 'order'=>'Permintaan pesanan', 'complaint'=>'Keluhan', 'other'=>'Lainnya'];
$groupedInquiries = $inquiries->getCollection()->groupBy(fn($inquiry) => $inquiry->intent ?: 'other');
@endphp
@if($groupedInquiries->isEmpty())<div class="inbox-empty"><strong>Tidak ada permintaan pada filter ini</strong><p>Pilih Semua untuk melihat permintaan dan riwayat lainnya.</p></div>@else
<div class="inbox-groups">
@foreach($intentLabels as $intent=>$label)
@php($group = $groupedInquiries->get($intent, collect()))
@if($group->isNotEmpty())
<section class="inbox-intent-group" aria-labelledby="intent-{{ $intent }}">
<header class="inbox-intent-heading"><div><span class="inbox-intent-kicker">KELOMPOK PERMINTAAN</span><h3 id="intent-{{ $intent }}">{{ $label }}</h3></div><span class="inbox-intent-count">{{ $group->count() }} {{ $group->count() === 1 ? 'permintaan' : 'permintaan' }}</span></header>
@foreach($group as $inquiry)
<article class="inbox-request inbox-request--{{ $intent }}" data-inquiry-id="{{ $inquiry->id }}">
<div class="inbox-request-main"><div class="inbox-request-title"><strong>{{ $inquiry->submitted_name }}</strong><span class="badge" data-status="{{ $inquiry->status }}">{{ ucfirst(str_replace('_', ' ', $inquiry->status)) }}</span><span class="badge">{{ $inquiry->source ?: 'Manual' }}</span></div><p class="inbox-preview">{{ \Illuminate\Support\Str::limit($inquiry->requirement, 180) }}</p><div class="inbox-meta"><span>{{ $inquiry->assignee?->name ?: 'Belum ditugaskan' }}</span><time datetime="{{ $inquiry->created_at->toIso8601String() }}">{{ $inquiry->created_at->format('d M Y · H:i') }}</time><span>#{{ $inquiry->id }}</span></div></div>
<div class="inbox-request-action">@include('partials.inquiry-action', ['inquiry'=>$inquiry])</div></article>
@endforeach</section>
@endif
@endforeach
@foreach($groupedInquiries->reject(fn($group, $intent) => array_key_exists($intent, $intentLabels)) as $unknownIntent=>$group)
<section class="inbox-intent-group"><header class="inbox-intent-heading"><div><span class="inbox-intent-kicker">KELOMPOK PERMINTAAN</span><h3>Lainnya</h3></div><span class="inbox-intent-count">{{ $group->count() }} permintaan</span></header>
@foreach($group as $inquiry)<article class="inbox-request inbox-request--{{ $unknownIntent }}" data-inquiry-id="{{ $inquiry->id }}"><div class="inbox-request-main"><div class="inbox-request-title"><strong>{{ $inquiry->submitted_name }}</strong><span class="badge" data-status="{{ $inquiry->status }}">{{ ucfirst(str_replace('_', ' ', $inquiry->status)) }}</span><span class="badge">{{ $inquiry->source ?: 'Manual' }}</span></div><p class="inbox-preview">{{ \Illuminate\Support\Str::limit($inquiry->requirement, 180) }}</p><div class="inbox-meta"><span>{{ $inquiry->assignee?->name ?: 'Belum ditugaskan' }}</span><time datetime="{{ $inquiry->created_at->toIso8601String() }}">{{ $inquiry->created_at->format('d M Y · H:i') }}</time><span>#{{ $inquiry->id }}</span></div></div><div class="inbox-request-action">@include('partials.inquiry-action', ['inquiry'=>$inquiry])</div></article>@endforeach</section>
@endforeach
</div>@endif
<div class="inbox-pagination">{{ $inquiries->links() }}</div></section>
<section class="card card-pad inbox-handoffs" id="inbox-handoffs" aria-labelledby="inbox-handoffs-title"><div class="inbox-section-head"><div><h2 id="inbox-handoffs-title">Serah-terima WhatsApp langsung</h2><p>Percakapan yang membutuhkan petugas, tanpa inquiry terkait.</p></div><span class="badge">{{ $conversations->total() }} percakapan</span></div>
@forelse($conversations as $conversation)
<div class="list-row"><div><strong>{{ $conversation->phone }}</strong><small>{{ $conversation->assigned_to ? 'Sudah ditugaskan' : 'Belum ditugaskan' }} · inquiry/tiket {{ $conversation->inquiry?->assigned_to ? 'ditugaskan' : ($conversation->inquiry_id ? 'belum ditugaskan' : 'belum dibuat') }}</small>
@if(auth()->user()->role !== 'admin')<a class="btn" href="https://wa.me/{{ $conversation->phone }}" target="_blank" rel="noopener noreferrer">Buka WhatsApp</a>@endif</div>
@if(auth()->user()->role === 'owner' || (auth()->user()->role === 'admin' && !$conversation->assigned_to && !$conversation->inquiry?->contact_id))
<form method="POST" action="{{ route('whatsapp.handoffs.assign', $conversation) }}">@csrf @method('PUT')<label>Petugas (penugasan inquiry/tiket tersinkron)<select class="input" name="assigned_to" required>@foreach($staff as $cs)<option value="{{ $cs->id }}" @selected((string)$conversation->assigned_to === (string)$cs->id)>{{ $cs->name }}</option>@endforeach</select></label><button class="btn">Tugaskan</button></form>
@endif</div>
@include('partials.handling', ['subject' => $conversation])
@empty<div class="inbox-empty"><strong>Tidak ada serah-terima langsung</strong><p>Percakapan yang membutuhkan petugas akan muncul di sini.</p></div>@endforelse
{{ $conversations->links() }}</section>
@if($deliveryIssues->isNotEmpty())<div class="card card-pad"><h2>Pengiriman perlu diperiksa</h2><p>Jangan kirim ulang otomatis; periksa WhatsApp terlebih dahulu.</p>@foreach($deliveryIssues as $issue)<p>{{ $issue->sender }} · {{ $issue->delivery_error }}</p>@endforeach</div>@endif
@if($handlingHistory)<div class="card card-pad"><h2>Riwayat catatan serah-terima langsung</h2><p>Catatan tetap tersimpan meskipun pelanggan memulai MENU atau petugas berganti.</p>@foreach($handlingHistory as $note)<p><strong>Percakapan #{{ $note->subject_id }} · {{ $note->user?->name }} · {{ $note->created_at->format('d M Y H:i:s') }}</strong><br>{{ $note->action }} — {{ $note->body }}</p>@endforeach{{ $handlingHistory->links() }}</div>@endif
</div>
@endsection
