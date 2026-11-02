@php
    $waNumber = preg_replace('/\D+/', '', (string) config('services.crm.whatsapp_cs'));
    $waUrl = $waNumber ? 'https://wa.me/'.$waNumber.'?text='.rawurlencode('MENU') : null;
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#122e26">
    <meta name="description" content="Wujudkan kebutuhan desain dan percetakan acara bersama RelasiKu. Mulai dari banner, poster, sertifikat hingga merchandise. Konsultasikan ide Anda dengan tim kami.">
    <title>Desain & Cetak untuk Momen Berarti | RelasiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="landing-page">
    <a class="landing-skip" href="#permintaan">Lewati ke formulir permintaan</a>
    <header class="landing-header">
        <div class="landing-container landing-header-inner">
            <a href="{{ route('inquiry.create') }}" class="landing-logo" aria-label="RelasiKu, kembali ke beranda">
                <span class="landing-logo-mark" aria-hidden="true">✳</span>
                <span>relasiku<small>DESAIN & PERCETAKAN ACARA</small></span>
            </a>
            <nav class="landing-nav" aria-label="Navigasi utama">
                <a href="#layanan">Layanan</a>
                <a href="#cara-kerja">Cara kerja</a>
                <a href="#permintaan">Mulai proyek</a>
            </nav>
            <div class="landing-header-actions">
                @if($waUrl)<a class="landing-header-wa" href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer">Chat bot WA <span aria-hidden="true">↗</span></a>@endif
                <a class="landing-header-login" href="{{ route('login') }}">Masuk tim</a>
            </div>
        </div>
    </header>

    <main>
        <section class="landing-hero" aria-labelledby="hero-title">
            <div class="landing-container landing-hero-grid">
                <div class="landing-hero-copy">
                    <span class="landing-kicker"><span class="landing-kicker-dot"></span> PARTNER KREATIF UNTUK ACARAMU</span>
                    <h1 id="hero-title">Setiap acara punya <em>cerita.</em> Biar kami bantu <span class="landing-headline-accent">wujudkan.</span></h1>
                    <p>Dari ide pertama hingga hasil cetak yang siap memeriahkan acara. Ceritakan kebutuhanmu, kami bantu cari jalan terbaik untuk desain dan percetakannya.</p>
                    <div class="landing-hero-actions">
                        @if($waUrl)<a class="landing-button landing-button-primary" href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer">Konsultasi via bot WhatsApp <span aria-hidden="true">↗</span></a>@endif
                        <a class="landing-button landing-button-outline" href="#permintaan">Isi formulir permintaan <span aria-hidden="true">↗</span></a>
                    </div>
                    <div class="landing-hero-note"><span class="landing-note-icon" aria-hidden="true">✦</span><span>Belum tahu harus mulai dari mana? Tenang, konsultasi dulu juga boleh.</span></div>
                </div>
                <div class="landing-hero-art" aria-label="Ilustrasi desain sertifikat, poster, dan kartu acara">
                    <div class="landing-art-glow"></div>
                    <div class="landing-art-orbit landing-art-orbit-one"></div>
                    <div class="landing-art-orbit landing-art-orbit-two"></div>
                    <div class="landing-art-poster"><span class="landing-poster-tag">LET'S CELEBRATE</span><strong>Make it<br><i>memorable.</i></strong><span class="landing-poster-star">✳</span><small>YOUR EVENT · YOUR STORY · YOUR MOMENT</small></div>
                    <div class="landing-art-certificate"><div class="landing-certificate-top">✳ <span>CERTIFICATE OF APPRECIATION</span></div><span class="landing-certificate-line"></span><strong>For the moments<br>that matter.</strong><span class="landing-certificate-rule"></span><small>CREATED WITH CARE BY RELASIKU</small></div>
                    <div class="landing-art-ticket"><span>EVENT PASS</span><strong>MAKE<br>IT COUNT</strong><span>NO. 001 — 2026</span></div>
                    <div class="landing-art-sticker">ide kamu<br><strong>jadi nyata ✦</strong></div>
                    <div class="landing-art-caption"><span class="landing-caption-symbol">✳</span><span>DETAIL KECIL.<br><strong>KESAN BESAR.</strong></span></div>
                </div>
            </div>
            <div class="landing-container landing-hero-footer"><span>DESAIN YANG TERASA PERSONAL</span><span aria-hidden="true">✳</span><span>CETAK YANG SIAP MERAMAIKAN ACARA</span><span aria-hidden="true">✳</span><span>PROSES YANG NYAMAN</span></div>
        </section>

        <section class="landing-services landing-section" id="layanan" aria-labelledby="services-title" data-reveal>
            <div class="landing-container">
                <div class="landing-section-heading"><div><span class="landing-section-label">01 / YANG BISA KAMI BANTU</span><h2 id="services-title">Ide besar dimulai dari <em>detail.</em></h2></div><p>Kebutuhan setiap acara berbeda. Pilih yang cocok untukmu, atau ceritakan idemu sendiri.</p></div>
                <div class="landing-service-grid">
                    <article class="landing-service-card"><span class="landing-service-number">01 — VISUAL ACARA</span><div class="landing-service-visual landing-service-visual-poster"><span>YOUR<br>BIG DAY<span class="landing-visual-spark">✳</span></span></div><h3>Banner & Poster</h3><p>Bikin acara lebih mudah dikenali dan susah dilupakan.</p><a href="#permintaan">Mulai diskusi <span aria-hidden="true">↗</span></a></article>
                    <article class="landing-service-card"><span class="landing-service-number">02 — APRESIASI</span><div class="landing-service-visual landing-service-visual-certificate"><span>✦<br><strong>Certificate</strong><small>OF APPRECIATION</small></span></div><h3>Sertifikat</h3><p>Kenang setiap pencapaian dengan sentuhan yang terasa spesial.</p><a href="#permintaan">Mulai diskusi <span aria-hidden="true">↗</span></a></article>
                    <article class="landing-service-card"><span class="landing-service-number">03 — KENANGAN</span><div class="landing-service-visual landing-service-visual-merch"><span>good<br><i>vibes.</i><span class="landing-visual-spark">✳</span></span></div><h3>Kaus & Merchandise</h3><p>Oleh-oleh yang bikin momen acara terus hidup setelah selesai.</p><a href="#permintaan">Mulai diskusi <span aria-hidden="true">↗</span></a></article>
                </div>
            </div>
        </section>

        <section class="landing-process landing-section" id="cara-kerja" aria-labelledby="process-title" data-reveal>
            <div class="landing-container landing-process-inner"><div class="landing-process-intro"><span class="landing-section-label">02 / PROSESNYA GAMPANG</span><h2 id="process-title">Dari bayangan, <em>jadi kenyataan.</em></h2><p>Nggak perlu datang dengan brief yang sempurna. Kita mulai dari percakapan sederhana.</p><a href="#permintaan" class="landing-text-link">Yuk, mulai sekarang <span aria-hidden="true">↗</span></a></div><div class="landing-process-steps"><div class="landing-process-step"><span>01</span><div><h3>Ceritakan kebutuhanmu</h3><p>Isi formulir singkat: untuk acara apa, butuh apa, dan kapan acaranya.</p></div><b aria-hidden="true">↗</b></div><div class="landing-process-step"><span>02</span><div><h3>Diskusi bareng tim</h3><p>Tim kami menghubungimu untuk memahami detail dan menyiapkan penawaran.</p></div><b aria-hidden="true">↗</b></div><div class="landing-process-step"><span>03</span><div><h3>Wujudkan acaramu</h3><p>Setelah cocok, pesanan dicatat dan proses selanjutnya dikomunikasikan.</p></div><b aria-hidden="true">✳</b></div></div></div>
        </section>

        <section class="landing-contact landing-section" id="permintaan" aria-labelledby="form-title" data-reveal>
            <div class="landing-container landing-contact-grid"><div class="landing-contact-copy"><span class="landing-section-label">03 / CERITAKAN IDEMU</span><h2 id="form-title">Acaramu berikutnya dimulai <em>di sini.</em></h2><p>Punya gambaran yang sudah jelas? Bagus. Masih sekadar ide? Juga boleh. Isi kebutuhanmu dan tim kami akan menghubungimu untuk langkah selanjutnya.</p><div class="landing-contact-reminder"><span aria-hidden="true">✳</span><div><strong>Masih ada pertanyaan?</strong><p>Konsultasikan dulu sebelum mengirim permintaan.</p>@if($waUrl)<a href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer">Chat bot demo via WhatsApp ↗</a>@endif</div></div></div>
            <div class="landing-form-card"><div class="landing-form-heading"><span class="landing-form-icon" aria-hidden="true">✳</span><div><span class="landing-section-label">FORMULIR PERMINTAAN</span><h3>{{ request('intent') === 'complaint' ? 'Formulir keluhan' : 'Yuk, mulai cerita.' }}</h3></div></div>
                @if(session('success'))<div class="alert" role="status">{{ session('success') }}</div>@endif
                @if($errors->any())<div class="alert alert-error" role="alert">Mohon periksa kembali data yang ditandai di bawah.</div>@endif
                <div class="landing-form-feedback" role="status" aria-live="polite" tabindex="-1" hidden></div>
                <form method="POST" action="{{ route('inquiry.store') }}" class="landing-form" data-inquiry-form>@csrf<input type="hidden" name="intent" value="{{ request('intent') === 'complaint' ? 'complaint' : 'consultation' }}">
                    <div class="landing-form-row"><div class="field"><label for="name">Nama lengkap <span>*</span></label><input class="input" id="name" name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="name" placeholder="Nama kamu">@error('name')<span class="error">{{ $message }}</span>@enderror</div><div class="field"><label for="phone">Nomor WhatsApp <span>*</span></label><input class="input" id="phone" name="phone" type="tel" value="{{ old('phone') }}" required maxlength="40" autocomplete="tel" placeholder="08xxxxxxxxxx">@error('phone')<span class="error">{{ $message }}</span>@enderror</div></div>
                    <div class="landing-form-row"><div class="field"><label for="email">Email <small>(opsional)</small></label><input class="input" id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" placeholder="nama@email.com">@error('email')<span class="error">{{ $message }}</span>@enderror</div><div class="field"><label for="service_id">Layanan yang dibutuhkan <span>*</span></label><select class="input" id="service_id" name="service_id" required><option value="">Pilih layanan</option>@foreach($services as $service)<option value="{{ $service->id }}" @selected(old('service_id') == $service->id)>{{ $service->name }}</option>@endforeach</select>@error('service_id')<span class="error">{{ $message }}</span>@enderror</div></div>
                    <div class="field"><label for="event_date">Tanggal acara <small>(jika sudah tahu)</small></label><input class="input" id="event_date" name="event_date" type="date" value="{{ old('event_date') }}">@error('event_date')<span class="error">{{ $message }}</span>@enderror</div>
                    <div class="field"><label for="requirement">{{ request('intent') === 'complaint' ? 'Ceritakan masalah yang terjadi' : 'Ceritakan kebutuhanmu' }} <span>*</span></label><textarea class="input" id="requirement" name="requirement" required maxlength="5000" rows="4" placeholder="{{ request('intent') === 'complaint' ? 'Jelaskan kendala, layanan/pesanan terkait, kapan terjadi, dan dampaknya...' : 'Contoh: Kami butuh 150 sertifikat untuk acara turnamen bulan depan...' }}">{{ old('requirement') }}</textarea>@error('requirement')<span class="error">{{ $message }}</span>@enderror</div>
                    <label class="landing-consent"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))><span>Saya setuju dihubungi oleh tim RelasiKu mengenai permintaan ini.</span></label>@error('consent')<span class="error">{{ $message }}</span>@enderror
                    <div class="landing-honeypot" aria-hidden="true"><label for="website">Kosongkan kolom ini</label><input type="text" id="website" name="website" tabindex="-1" autocomplete="off"></div>
                    <button type="submit" class="landing-button landing-button-primary landing-submit" data-inquiry-submit><span data-submit-label>Kirim permintaan</span><span data-submit-icon aria-hidden="true">↗</span></button><p class="landing-privacy">Data yang kamu kirim hanya digunakan untuk menindaklanjuti permintaanmu.</p>
                </form>
            </div></div>
        </section>
    </main>
    <footer class="landing-footer"><div class="landing-container landing-footer-inner"><a href="{{ route('inquiry.create') }}" class="landing-logo"><span class="landing-logo-mark" aria-hidden="true">✳</span><span>relasiku<small>DESAIN & PERCETAKAN ACARA</small></span></a><span>Detail kecil. Kesan besar. © {{ date('Y') }} RelasiKu.</span><a href="{{ route('login') }}">Ruang kerja tim ↗</a></div></footer>
    @if($waUrl)<a class="landing-wa-float" href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Konsultasi dengan bot demo melalui WhatsApp"><span class="landing-wa-label">Chat bot demo WA</span><span class="landing-wa-icon" aria-hidden="true"><svg viewBox="0 0 32 32" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M16.02 2.67A13.2 13.2 0 0 0 4.59 22.5L2.67 29.33l7.02-1.84A13.22 13.22 0 1 0 16.02 2.67Zm0 24.02c-2.13 0-4.22-.57-6.04-1.65l-.43-.26-4.16 1.1 1.11-4.05-.29-.45a10.79 10.79 0 1 1 9.81 5.31Zm5.92-8.11c-.32-.16-1.9-.94-2.2-1.04-.29-.1-.5-.16-.72.16-.21.32-.83 1.04-1.01 1.25-.19.21-.37.24-.7.08-.32-.16-1.36-.5-2.6-1.6-.95-.85-1.6-1.9-1.78-2.22-.19-.32-.02-.5.14-.66.14-.14.32-.37.48-.56.16-.19.21-.32.32-.53.11-.21.05-.4-.03-.56-.08-.16-.72-1.74-.99-2.38-.26-.62-.53-.54-.72-.55h-.61c-.21 0-.56.08-.85.4-.29.32-1.12 1.1-1.12 2.67 0 1.57 1.15 3.08 1.31 3.29.16.21 2.26 3.45 5.48 4.84.76.33 1.36.53 1.83.67.77.24 1.47.2 2.02.12.62-.09 1.9-.77 2.17-1.52.27-.75.27-1.39.19-1.52-.08-.13-.29-.21-.61-.37Z"/></svg></span></a>@endif
</body>
</html>
