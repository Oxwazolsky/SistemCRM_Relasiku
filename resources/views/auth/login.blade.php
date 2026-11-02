<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · RelasiKu CRM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="rk-login">
    <main class="rk-shell">
        <section class="rk-story" aria-labelledby="story-title">
            <a href="/" class="rk-brand" aria-label="Sistem CRM — beranda">
                <span class="rk-brand-mark" aria-hidden="true">✳</span>
                <span>Sistem CRM<span class="rk-brand-caption">RUANG KERJA TIM ANDA</span></span>
            </a>
            <div class="rk-story-content">
                <span class="rk-eyebrow">RUANG KERJA LAYANAN PELANGGAN</span>
                <h1 id="story-title">Semua pelanggan.<br><em>Satu alur kerja.</em></h1>
                <p>Kelola percakapan, pesanan, dan layanan pelanggan dalam satu tempat.</p>
                <div class="rk-story-accent" aria-hidden="true">
                    <span class="rk-accent-orbit rk-accent-orbit-one"></span>
                    <span class="rk-accent-orbit rk-accent-orbit-two"></span>
                    <span class="rk-accent-core"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12a8 8 0 1 1 2.35 5.65L4 20l2.35-2.35A7.97 7.97 0 0 1 4 12Z"/><path d="M8 12h8M12 8v8"/></svg></span>
                    <span class="rk-accent-label">Terhubung. Teratur. Terpantau.</span>
                </div>
            </div>
        </section>
        <section class="rk-access" aria-labelledby="login-title">
            <div class="rk-access-content">
                <div class="rk-card">
                    <div class="rk-card-symbol" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg></div>
                    <span class="rk-eyebrow">SELAMAT DATANG KEMBALI</span>
                    <h2 id="login-title">Masuk ke ruang kerja.</h2>
                    <p class="rk-intro">Gunakan akun tim Anda untuk melanjutkan pekerjaan.</p>
                    @if(session('status'))
                        <div class="rk-notice" role="status">{{ session('status') }}</div>
                    @endif
                    @if($errors->any())
                        <div class="rk-notice rk-notice-error" role="alert">Email atau kata sandi tidak sesuai. Silakan coba lagi.</div>
                    @endif
                    <form method="POST" action="{{ route('login.store') }}" class="rk-form">
                        @csrf
                        <div class="rk-field">
                            <label for="email">Alamat email</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="nama@perusahaan.com" autocomplete="username" required autofocus @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                            @error('email')<span class="rk-error" id="email-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="rk-field">
                            <label for="password">Kata sandi</label>
                            <div class="rk-password">
                                <input type="password" id="password" name="password" placeholder="Masukkan kata sandi" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                                <button class="rk-password-toggle" type="button" data-password-toggle aria-label="Tampilkan kata sandi" aria-controls="password" aria-pressed="false">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="rk-eye-slash" d="M3 3l18 18"/></svg>
                                </button>
                            </div>
                            @error('password')<span class="rk-error" id="password-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="rk-form-options">
                            <label class="rk-remember"><input type="checkbox" name="remember" @checked(old('remember'))> Ingat saya</label>
                            <a href="{{ route('password.request') }}">Lupa kata sandi?</a>
                        </div>
                        <button class="rk-submit" type="submit">Masuk ke dashboard <span aria-hidden="true">→</span></button>
                    </form>

                </div>
                @php($businessWhatsapp = trim((string) config('services.crm.whatsapp_cs')))
                @if(preg_match('/^[1-9][0-9]{7,14}$/', $businessWhatsapp))
                    <a class="rk-customer-link" href="https://wa.me/{{ $businessWhatsapp }}?text=MENU">Pelanggan? Hubungi kami melalui WhatsApp <span aria-hidden="true">↗</span></a>
                @endif
            </div>
            <footer class="rk-footer">© {{ date('Y') }} RelasiKu CRM <span aria-hidden="true">·</span> Ruang untuk hubungan yang lebih baik.</footer>
        </section>
    </main>
</body>
</html>
