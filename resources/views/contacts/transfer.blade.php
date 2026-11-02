@extends('layouts.app')
@section('title', 'Alih penanggung jawab kontak')
@section('content')
<div class="workspace-page" data-workspace="contact-transfer">
<div class="page-head"><div><span class="eyebrow">Kontak / Pengalihan</span><h1>Alih penanggung jawab</h1><p>Periksa dampak pengalihan sebelum memilih CS pengganti.</p></div><a class="btn" href="{{ route('contacts.show', $contact) }}">← Kembali ke kontak</a></div>
<div class="card card-pad" style="max-width:840px">
    <h2>{{ $contact->name }}</h2><p class="help">Pemilik sekarang: {{ $contact->owner?->name ?: 'Belum ditugaskan' }}</p>
    <h3>Yang akan berpindah</h3>
    <dl class="workspace-summary"><div><dt>Permintaan terverifikasi</dt><dd>{{ $preview['inquiries'] }}</dd></div><div><dt>Peluang aktif</dt><dd>{{ $preview['active_opportunities'] }}</dd></div><div><dt>Tugas terbuka pemilik lama</dt><dd>{{ $preview['own_tasks'] }}</dd></div><div><dt>Tiket terbuka pemilik lama</dt><dd>{{ $preview['own_tickets'] }}</dd></div></dl>
    <p>{{ $preview['helper_tasks'] }} tugas dan {{ $preview['helper_tickets'] }} tiket yang ditangani petugas bantu <strong>tetap pada petugasnya</strong>. Riwayat tugas/tiket selesai serta pemilik peluang yang sudah ditutup tidak diubah.</p>
    <div class="workspace-form-intro"><span class="eyebrow">Konfirmasi pengalihan</span><h2>Pilih penanggung jawab baru</h2><p>Pastikan petugas pengganti siap menerima pekerjaan yang tercantum dalam pratinjau.</p></div>
    <form method="POST" action="{{ route('contacts.transfer.update', $contact) }}" class="stack">@csrf @method('PUT')
        <input type="hidden" name="preview_token" value="{{ $previewToken }}">
        @error('preview')<p class="error">{{ $message }} <a href="{{ route('contacts.transfer.edit', $contact) }}">Muat ulang pratinjau</a></p>@enderror
        <div class="field"><label for="owner_id">CS pengganti</label><select class="input" id="owner_id" name="owner_id" required><option value="">Pilih CS aktif</option>@foreach($staff as $cs)<option value="{{ $cs->id }}" @selected(old('owner_id') == $cs->id)>{{ $cs->name }}</option>@endforeach</select>@error('owner_id')<span class="error">{{ $message }}</span>@enderror</div>
        <div class="field"><label for="confirm_transfer" style="display:flex;gap:10px;align-items:flex-start"><input id="confirm_transfer" type="checkbox" name="confirm_transfer" value="1" required @checked(old('confirm_transfer'))> Saya sudah memeriksa pekerjaan yang akan dialihkan.</label>@error('confirm_transfer')<span class="error">{{ $message }}</span>@enderror</div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Alihkan penanggung jawab</button><a class="btn" href="{{ route('contacts.show', $contact) }}">Batal</a></div>
    </form>
</div>
</div>
@endsection
