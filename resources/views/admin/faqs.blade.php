@extends('layouts.app')
@section('title', 'FAQ & informasi bot')
@section('content')
<div class="workspace-page" data-workspace="faqs">
    <div class="page-head"><div><span class="eyebrow">Administrasi / Pengetahuan layanan</span><h1>FAQ & informasi bot</h1><p>Kelola jawaban yang konsisten untuk membantu pelanggan mengenal layanan.</p></div><a class="btn btn-primary" href="#faq-new">Tambah informasi</a></div>
    <dl class="workspace-summary"><div><dt>Informasi tersimpan</dt><dd>{{ $faqs->count() }}<small>Seluruh FAQ</small></dd></div><div><dt>Aktif</dt><dd>{{ $faqs->where('active', true)->count() }}<small>Tersedia untuk jawaban bot</small></dd></div><div><dt>Nonaktif</dt><dd>{{ $faqs->where('active', false)->count() }}<small>Tidak dipublikasikan</small></dd></div></dl>
    <aside class="workspace-note">Hanya publikasikan informasi yang disetujui. Jangan masukkan data pelanggan atau rahasia. FAQ aktif menggantikan jawaban bawaan.</aside>
    <div class="workspace-faq-grid">
    @foreach($faqs->concat([new \App\Models\BotFaq]) as $faq)
        <form id="faq-{{ $faq->exists ? $faq->id : 'new' }}" class="card card-pad stack" method="POST" action="{{ $faq->exists ? route('admin.faqs.update', $faq) : route('admin.faqs.store') }}">@csrf @if($faq->exists)@method('PUT')@endif
            <div class="section-head"><h2>{{ $faq->exists ? 'FAQ #'.$faq->id : 'Tambah informasi' }}</h2><span class="badge">{{ $faq->exists ? ($faq->active ? 'Aktif' : 'Nonaktif') : 'Baru' }}</span></div>
            <label>Pertanyaan<input class="input" name="question" value="{{ $faq->question }}" maxlength="255" required></label>
            <label>Jawaban<textarea class="input" name="answer" maxlength="3000" required rows="5">{{ $faq->answer }}</textarea></label>
            <p class="help">Gunakan jawaban singkat dan jelas. Maksimal 3.000 karakter.</p>
            <label>Status<select class="input" name="active"><option value="1" @selected($faq->active)>Aktif</option><option value="0" @selected(!$faq->active)>Nonaktif</option></select></label>
            <div class="form-actions"><button class="btn btn-primary" type="submit">Simpan informasi</button></div>
        </form>
    @endforeach
    </div>
</div>
@endsection
