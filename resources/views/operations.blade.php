@extends('layouts.app')
@section('title', 'Ringkasan operasional')
@section('content')
<div class="workspace-page" data-workspace="operations">
@php $role = auth()->user()->role; @endphp
<div class="page-head"><div><span class="eyebrow">{{ $role === 'cs' ? 'Ruang kerja pribadi' : 'Pusat operasional' }}</span><h1>{{ $role === 'admin' ? 'Administrasi layanan' : ($role === 'owner' ? 'Ringkasan operasional' : 'Pekerjaan saya') }}</h1><p>Selamat datang, {{ auth()->user()->name }}. {{ $role === 'cs' ? 'Ringkasan pelanggan dan pekerjaan yang menjadi tanggung jawab Anda.' : 'Pantau kesiapan tim dan kelancaran layanan pelanggan.' }}</p></div><a class="btn btn-primary" href="{{ route('inbox') }}">Buka inbox WhatsApp →</a></div>
<div class="section-head"><div><span class="eyebrow">Snapshot layanan</span><h2>{{ $role === 'cs' ? 'Portofolio pekerjaan Anda' : 'Kondisi operasional saat ini' }}</h2></div><span class="help">{{ now()->locale('id')->translatedFormat('d F Y') }}</span></div>
<div class="dashboard-metrics">@foreach($overview as $label => $value)<div class="card metric"><small>{{ $label }}</small><strong>{{ number_format($value, 0, ',', '.') }}</strong><span class="help">Posisi saat ini · seluruh periode</span></div>@endforeach</div>
<div class="grid grid-2" style="margin-bottom:24px">
    <section class="card card-pad"><span class="eyebrow">Perlu ditindaklanjuti</span><h2>Permintaan menunggu: {{ $pending }}</h2><p>Permintaan baru atau ditugaskan yang masih menunggu verifikasi identitas pelanggan.</p><a class="btn" href="{{ route('inbox') }}">Tinjau permintaan →</a></section>
    <section class="card card-pad">
    @if($role === 'admin')<span class="eyebrow">Pengaturan layanan</span><h2>Akun, katalog & informasi bot</h2><p>Kelola akses tim dan informasi layanan. Transfer kepemilikan pelanggan tetap dilakukan Pemilik.</p><div class="quick-links"><a class="btn" href="{{ route('admin.users.index') }}">Pengguna</a><a class="btn" href="{{ route('admin.services.index') }}">Layanan</a><a class="btn" href="{{ route('admin.faqs') }}">FAQ bot</a></div>
    @else<span class="eyebrow">Layanan purnajual</span><h2>Keluhan terbuka: {{ $tickets }}</h2><p>Keluhan baru dan dalam penanganan. Selesaikan dengan mencatat hasil penanganan pelanggan.</p><a class="btn" href="{{ route('tickets.index') }}">Tinjau keluhan →</a>@endif
    </section>
</div>
@if($role === 'owner')
<section class="card"><div class="toolbar"><div><h2>Beban kerja CS aktif</h2><span class="help">Posisi saat ini; bukan statistik pesan belum dibaca.</span></div><a class="btn" href="{{ route('reports.index') }}">Lihat laporan →</a></div><div class="table-wrap"><table class="table"><thead><tr><th scope="col">Petugas</th><th scope="col">Menunggu verifikasi</th><th scope="col">Keluhan terbuka</th><th scope="col">Serah-terima WA</th></tr></thead><tbody>@forelse($workload as $row)<tr><td><strong>{{ $row['name'] }}</strong></td><td>{{ $row['inquiries'] }}</td><td>{{ $row['tickets'] }}</td><td>{{ $row['handoffs'] }}</td></tr>@empty<tr><td colspan="4" class="empty">Belum ada CS aktif. Hubungi Administrator untuk menyiapkan akun tim.</td></tr>@endforelse</tbody></table></div></section>
@elseif($role === 'cs')
<section class="card card-pad"><span class="eyebrow">Panduan kerja</span><h2>Alur pelayanan</h2><dl class="workspace-summary"><div><dt>01 / Penerimaan</dt><dd>Tinjau permintaan<small>Kenali kebutuhan dan sumber pelanggan.</small></dd></div><div><dt>02 / Identitas</dt><dd>Verifikasi pelanggan<small>Pastikan identitas sebelum menautkan kontak.</small></dd></div><div><dt>03 / Kesepakatan</dt><dd>Konfirmasi pesanan<small>Catat harga dan persetujuan pelanggan.</small></dd></div></dl><p>Gunakan riwayat interaksi pelanggan untuk mencatat komunikasi dan langkah berikutnya.</p><div class="quick-links"><a class="btn" href="{{ route('contacts.index') }}">Pelanggan saya</a><a class="btn" href="{{ route('orders.index') }}">Kelola pesanan</a></div></section>
@endif
</div>
@endsection
