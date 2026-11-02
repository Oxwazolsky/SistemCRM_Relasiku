@php
    $waNumber = preg_replace('/\D+/', '', (string) config('services.crm.whatsapp_cs'));
    $waUrl = $waNumber ? 'https://wa.me/'.$waNumber.'?text='.rawurlencode('Halo, saya ingin konsultasi desain dan percetakan acara.') : null;
    $step = $state['step'];
@endphp
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Konsultasi Online | RelasiKu</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="landing-page">
<header class="landing-header"><div class="landing-container landing-header-inner"><a class="landing-logo" href="{{ route('inquiry.create') }}"><span class="landing-logo-mark">✳</span><span>relasiku<small>DESAIN & PERCETAKAN ACARA</small></span></a><div class="landing-header-actions"><a class="landing-header-login" href="{{ route('inquiry.create') }}">← Kembali ke beranda</a></div></div></header>
<main class="landing-container" style="max-width:780px;padding-top:72px;padding-bottom:96px">
    <div class="landing-section-heading"><div><span class="landing-section-label">KONSULTASI ONLINE</span><h1>Mulai dari <em>cerita Anda.</em></h1></div></div>
    <div class="landing-form-card" style="margin-top:24px"><div class="landing-form-heading"><span class="landing-form-icon">✳</span><div><h2>Asisten konsultasi</h2><p>Pilih layanan atau lihat FAQ. Jawaban otomatis ini bukan penawaran harga atau janji tenggat.</p></div></div>
    @if($errors->any())<div class="alert alert-error" role="alert">{{ $errors->first() }}</div>@endif
    @if($step === 'done')
        <div class="alert" role="status"><strong>Terima kasih! Permintaan #{{ $state['inquiry_id'] }} sudah dicatat.</strong> Tim kami akan meninjau kebutuhan Anda. Identitas dan kontak akan diverifikasi oleh CS.</div>
        <p>Ingin berbicara dengan CS? Gunakan tautan WhatsApp di bawah secara manual.</p>
    @elseif($step === 'faq')
        <h3>Pertanyaan yang sering ditanyakan</h3>
        <div class="stack"><p><strong>Berapa harga?</strong><br>Harga bergantung pada jenis layanan, jumlah, dan spesifikasi. CS akan menyiapkan penawaran setelah memeriksa kebutuhan Anda.</p><p><strong>Berapa lama pengerjaannya?</strong><br>Tergantung layanan dan jadwal produksi. CS akan mengonfirmasi tenggat setelah berdiskusi.</p><p><strong>Bagaimana cara memesan?</strong><br>Isi kebutuhan Anda melalui konsultasi ini. CS akan menindaklanjuti; pesanan baru dicatat setelah ada kesepakatan.</p></div>
    @else
        <p class="help">Langkah {{ array_search($step, ['service','name','phone','date','requirement','consent'], true) + 1 }} dari 6. Sebelum Anda setuju, jawaban yang telah diberikan disimpan sebagai draf dalam sesi situs untuk melanjutkan konsultasi; inquiry CRM baru dibuat setelah persetujuan eksplisit. Sesi browser juga menyimpan data untuk fungsi situs ini.</p>
        <form method="POST" action="{{ route('consultation.submit') }}" class="landing-form">@csrf
        @if($step === 'service')
            <div class="field"><label for="service_id">Layanan yang dibutuhkan</label><select class="input" name="service_id" id="service_id" required><option value="">Pilih layanan</option>@foreach($services as $service)<option value="{{ $service->id }}" @selected(old('service_id') == $service->id)>{{ $service->name }}</option>@endforeach</select></div>
        @elseif($step === 'name')
            <div class="field"><label for="name">Siapa nama Anda?</label><input class="input" id="name" name="name" required minlength="2" maxlength="255" autocomplete="name" value="{{ old('name') }}"></div>
        @elseif($step === 'phone')
            <div class="field"><label for="phone">Nomor WhatsApp untuk dihubungi CS</label><input class="input" type="tel" id="phone" name="phone" required maxlength="16" autocomplete="tel" placeholder="08xxxxxxxxxx" value="{{ old('phone') }}"></div>
        @elseif($step === 'date')
            <div class="field"><label for="event_date">Tanggal acara (opsional)</label><input class="input" type="date" id="event_date" name="event_date" value="{{ old('event_date') }}"></div>
        @elseif($step === 'requirement')
            <div class="field"><label for="requirement">Ceritakan kebutuhan Anda</label><textarea class="input" id="requirement" name="requirement" minlength="5" maxlength="5000" rows="5" required>{{ old('requirement') }}</textarea></div>
        @elseif($step === 'consent')
            <h3>Periksa sebelum mengirim</h3><dl class="detail-list"><div><dt>Nama</dt><dd>{{ $state['name'] }}</dd></div><div><dt>Nomor</dt><dd>{{ $state['phone'] }}</dd></div><div><dt>Kebutuhan</dt><dd>{{ $state['requirement'] }}</dd></div></dl>
            <label class="landing-consent"><input type="checkbox" name="consent" value="1" required><span>Saya setuju nama, nomor, tanggal acara (jika ada), dan kebutuhan saya dicatat sebagai permintaan di CRM untuk ditinjau serta ditindaklanjuti CS. Sebelum setuju, jawaban ini tersimpan sebagai draf sesi situs; inquiry baru dibuat setelah persetujuan ini.</span></label>
            <div class="landing-honeypot" aria-hidden="true"><label for="website">Kosongkan kolom ini</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
            <form method="POST" action="{{ route('consultation.submit') }}" style="margin-top:12px">@csrf<input type="hidden" name="action" value="decline"><button class="btn" type="submit">Tidak setuju dan batalkan draf</button></form>
        @endif
        <button class="landing-button landing-button-primary" type="submit">{{ $step === 'consent' ? 'Setuju dan kirim permintaan' : 'Lanjutkan →' }}</button>
        </form>
    @endif
    <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:24px">
        @if($step !== 'faq' && $step !== 'done')<form method="POST" action="{{ route('consultation.submit') }}">@csrf<input type="hidden" name="action" value="faq"><button class="btn" type="submit">Lihat FAQ</button></form>@endif
        @if($step === 'faq')<form method="POST" action="{{ route('consultation.submit') }}">@csrf<input type="hidden" name="action" value="start"><button class="btn btn-primary" type="submit">Mulai konsultasi</button></form>@endif
        @if($step !== 'service')<form method="POST" action="{{ route('consultation.submit') }}">@csrf<input type="hidden" name="action" value="restart"><button class="btn" type="submit">Mulai ulang</button></form>@endif
        @if($waUrl)<a class="btn" href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer">Bicara dengan CS via WhatsApp ↗</a>@endif
    </div>
    <p class="landing-privacy">Konsultasi di halaman ini tidak mengirim pesan WhatsApp otomatis. Untuk komunikasi WhatsApp, buka tautan CS di atas.</p>
    </div>
</main></body></html>
