# RelasiKu CRM

CRM berbasis Laravel 12, MySQL, Blade, dan Vite untuk jasa desain dan percetakan acara. Fitur: permintaan masuk, verifikasi kontak, interaksi, peluang, tugas tindak lanjut, pesanan, keluhan, dashboard, laporan, serta administrasi akun dan layanan. Penawaran dan status pesanan masih ringkas; sistem ini **bukan** pencatatan pembayaran atau manajemen produksi.

Untuk demo lokal, konsultasi WhatsApp ditangani oleh **Node.js + Baileys → HTTP loopback → Laravel → MySQL**. Baileys menggunakan koneksi WhatsApp Web, **bukan API resmi Meta**. Integrasi Cloud API resmi tersedia terpisah tetapi nonaktif secara default. Jangan gunakan nomor bisnis yang menerima pelanggan untuk demo ini.

## Penutupan operasional dan catatan penanganan

**Deployment tertunda:** migrasi aplikasi belum dijalankan untuk tahap ini. Cadangkan database, lalu jalankan `php artisan migrate` sebelum membuka inbox; migrasi `2026_10_27_000002_add_handling_notes.php` menambahkan status penanganan dan tabel catatan tanpa mengubah status verifikasi lama. Jangan gunakan `migrate:fresh` atau seeder untuk pembaruan ini.

- Inbox default menampilkan pekerjaan belum selesai. Filter Aktif, Menunggu pelanggan, Selesai/riwayat, dan Semua beserta jumlahnya mengikuti ruang akses pengguna.
- Pemilik atau CS yang ditugaskan mencatat hasil wajib melalui **Catat hasil penanganan**, **Menunggu pelanggan**, atau **Selesai**. Admin hanya menugaskan; tidak dapat mencatat hasil atau menyelesaikan bisnis.
- Konsultasi dan serah-terima langsung tanpa inquiry dapat selesai tanpa membuat kontak/pesanan. Catatan wajib berisi hasil nyata; jangan masukkan OTP, kata sandi, atau data pembayaran. Ini bukan persetujuan pelanggan untuk membuat inquiry baru.
- Permintaan pesanan menunggu pelanggan, dikonfirmasi dengan persetujuan harga/pesanan dan verifikasi identitas, atau dibatalkan dengan alasan dalam catatan. Setelah dikonversi, status pemenuhan/pembatalan hanya dikelola pada pesanan. Penanganan yang selesai/dibatalkan tidak dapat dikonfirmasi menjadi pesanan.
- Keluhan WhatsApp otomatis membuat tiket setelah pengirim menyetujui pembuatan permintaan. CS dapat melihat tiket yang belum ditugaskan dan mulai menangani tanpa verifikasi identitas; saat perubahan tiket pertama disimpan, tiket diklaim otomatis oleh CS. Verifikasi tetap wajib sebelum menautkan pengirim ke kontak atau membahas data pesanan. Status tiket adalah acuan penyelesaian keluhan; Inbox tidak lagi menghitung permintaan sebagai aktif jika semua tiket terkait selesai/ditutup, dan menghitungnya kembali jika tiket dibuka kembali.
- Catatan bersifat tambahan, berpenulis dan timestamp server, tidak ditimpa. Pengiriman ulang formulir yang sama tidak menggandakan catatan. WhatsApp `wa.me` hanya membuka aplikasi, tidak mengubah status CRM atau mengirim pesan otomatis.
- MENU melanjutkan bot, bukan membuka kembali inquiry lama. Permintaan CS baru tidak menautkan inquiry lama. Riwayat catatan serah-terima langsung tetap tersedia melalui filter riwayat meskipun percakapan direset atau ditugaskan ulang; CS hanya melihat riwayat yang ditugaskan kepadanya saat pencatatan, Pemilik/Admin dapat meninjau semua.

Batasan: satu baris percakapan masih dipakai per nomor. Jumlah inbox menghitung pekerjaan terkini, bukan jumlah siklus handoff historis; riwayat catatan ditampilkan terpisah. Penyaringan status gabungan tiket/pesanan dilakukan di aplikasi sebelum pagination (cocok untuk volume MVP; optimalkan query untuk volume besar). Timestamp mengikuti timezone aplikasi. Tidak ada pengiriman WhatsApp staf dari CRM.

Uji regresi: `php artisan test` (database khusus `relasiku_crm_test`, migrasikan database uji terlebih dahulu), lalu `npm run build`.

## Isi layanan dan FAQ tanpa mengubah akun

Jalankan seeder khusus berikut pada database aplikasi yang sudah memiliki tabel:

```powershell
php artisan db:seed --class=ServiceFaqSeeder
```

`database/seeders/ServiceFaqSeeder.php` menambahkan **6 layanan** (banner/spanduk, poster, sertifikat, kaos, merchandise, desain grafis acara) dan **12 FAQ berkelompok** berbahasa Indonesia berdasarkan ruang lingkup blueprint. Tidak ada migrasi, reset, penghapusan, atau perubahan akun. Seeder memakai `firstOrCreate` berdasarkan nama layanan/pertanyaan FAQ: menjalankan ulang tidak menimpa isi, kategori, maupun status aktif yang sudah diedit pengguna. Jangan memakai `DatabaseSeeder` untuk pemeliharaan ini karena mengatur ulang akun demo.

Kelola isi dan status aktif melalui administrasi layanan/FAQ. Nama layanan dan pertanyaan merupakan kunci pencocokan: bila diganti, menjalankan seeder kembali dapat menambahkan judul bawaan yang lama; tinjau katalog terlebih dahulu. Jangan menjalankan seeder paralel. Model layanan belum memiliki kolom deskripsi; rincian produk dan spesifikasi berada dalam jawaban FAQ. Harga, bahan/varian tersedia, minimum jumlah, tenggat, pembayaran, pengiriman, revisi, pembatalan/refund, dan kebijakan data harus dikonfirmasi CS, bukan dianggap kebijakan bisnis pasti dari contoh isi.

Bot WhatsApp menampilkan seluruh judul FAQ aktif dengan nomor berurutan (bukan ID database), lalu satu jawaban sesuai pilihan; 12 topik ini mengelompokkan rincian agar menu tidak menjadi monolog panjang. Uji akses semua nomor dan pelestarian data: `php artisan test --filter=ServiceFaqSeederTest` (konfigurasi PHPUnit memakai database terpisah `relasiku_crm_test`).

## Persyaratan dan instalasi lokal

- Windows dengan PHP CLI 8.2+, Composer, Node.js 20+, dan MySQL (XAMPP/Laragon). Jalankan perintah berikut dari folder `crm` kecuali disebutkan lain.
- Pastikan port `8000` tersedia dan PHP CLI memakai ekstensi MySQL. **Jangan jalankan `migrate:fresh` pada database berisi data.**

```powershell
composer install
npm ci
Copy-Item .env.example .env  # hanya jika .env belum ada
```

Sesuaikan `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` pada `.env` dengan MySQL lokal. Buat database `relasiku_crm` dengan karakter `utf8mb4` jika belum tersedia. Jangan menimpa `.env` yang sudah berisi konfigurasi; cadangkan database sebelum migrasi pada instalasi lama.

```powershell
php artisan key:generate   # hanya jika APP_KEY belum terisi
php artisan migrate       # instalasi baru atau pembaruan tanpa menghapus data
php artisan db:seed       # HANYA instalasi demo baru; lihat peringatan di bawah
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Buka `http://127.0.0.1:8000/inquiry` untuk landing page dan `/login` untuk staf. **Seeder akan membuat/memperbarui akun demo berikut dan mengatur ulang kata sandinya**; jangan jalankan ulang `db:seed` pada sistem berisi akun yang sudah dipakai.

| Peran | Email demo | Kata sandi demo |
| --- | --- | --- |
| Admin | `admin@relasiku.test` | `DemoCRM2026!` |
| Owner | `owner@relasiku.test` | `DemoCRM2026!` |
| CS | `cs@relasiku.test` | `DemoCRM2026!` |

Ganti kata sandi akun demo sebelum akses diberikan ke pihak lain. `.env` dan file sesi WhatsApp tidak boleh diunggah/dibagikan. `APP_DEBUG=true` hanya layak di lingkungan lokal; matikan bila aplikasi dapat diakses pihak lain.

## Pembaruan simplifikasi (27 Oktober 2026)

- Halaman `/` mengarahkan pengunjung ke `/login`, dan staf aktif ke `/dashboard` sesuai peran. Akun nonaktif tetap ditolak. Landing/form publik `/inquiry` dan `/konsultasi` beserta route pengirimannya tetap tersedia. Login menampilkan tautan pelanggan WhatsApp hanya jika `CRM_WHATSAPP_CS` dikonfigurasi dengan nomor internasional yang valid; tidak ada nomor fallback.

- Navigasi utama memakai **Inbox WhatsApp** (`/inbox`): permintaan pelanggan dan antrean serah-terima dalam satu halaman, dengan pembatasan akses CS tetap berlaku. Penugasan antrean dan inquiry masih terpisah dan diberi label eksplisit.
- Bot: `1` konsultasi, `2` FAQ, `3` permintaan pesanan, `4` keluhan. Keluhan memakai pengumpulan identitas/kebutuhan yang sama, lalu otomatis membuat tiket setelah `SETUJU`. Isi kebutuhan disalin, tidak perlu diketik ulang. Tiket awal tidak memiliki kontak/pesanan/petugas. CS dapat mengambil keluhan WhatsApp yang belum ditugaskan langsung dari antrean Keluhan; perubahan pertama pada tiket mengklaim tiket ke CS tersebut. Penanganan keluhan tidak menautkan atau memverifikasi kontak. Verifikasi diperlukan sebelum menghubungkan pengirim ke profil atau membahas pesanan. Nomor pengirim maupun kode pesanan bukan bukti identitas.
- CS memverifikasi inquiry, menyepakati harga melalui WhatsApp, lalu **Konfirmasi pesanan** dari detail inquiry. Peluang dibuat di belakang layar dan konversi lama dipakai, dengan penguncian dan satu pesanan per inquiry. Pengulangan konfirmasi membuka pesanan yang sama, bukan mengubah harga.
- Tombol `wa.me` membuka WhatsApp; tidak mengirim pesan dari CRM. Gunakan akun WhatsApp resmi yang benar. Tidak ada composer CRM atau sinkronisasi balasan manual.
- Admin mengelola FAQ/informasi bot di `/admin/faqs`; FAQ aktif menggantikan daftar bawaan. Admin boleh menugaskan antrean WA yang belum ditugaskan dan belum terkait kontak terverifikasi. Transfer pelanggan tetap kewenangan Owner.
- Dashboard disesuaikan per peran; Owner melihat beban permintaan, keluhan terbuka, dan serah-terima per CS aktif. Antrean bukan indikator pesan belum dibaca.
- Route, formulir website, data sumber lama, dan UI peluang lama dipertahankan untuk kompatibilitas; peluang tidak lagi ada di navigasi utama. Form website masih tersedia sebagai jalur lama, sehingga pembaruan ini belum menonaktifkan seluruh intake non-WhatsApp.

### Deployment pembaruan

Cadangkan database, deploy kode, lalu jalankan `php artisan migrate` (bukan `migrate:fresh`), `npm run build`, dan `php artisan view:clear`. Restart worker setelah deploy. Migrasi baru menambah intent/tautan konversi inquiry, tabel FAQ, serta mendukung tiket intake tanpa kontak/petugas. Migrasi ini forward-only untuk menghindari kehilangan tiket yang belum diverifikasi; rollback memerlukan pemulihan backup yang ditinjau. Tidak perlu reseed. Migrasi database aplikasi belum dijalankan oleh implementasi ini; hanya database uji terpisah yang dimigrasikan.

Batas saat ini: keluhan masih meminta layanan/email opsional/tanggal seperti konsultasi; asosiasi pesanan pada tiket bot tidak dilakukan otomatis; tidak ada pencarian status pesanan publik. Dashboard lama dan route laporan tetap tersedia dalam kode/route terkait, namun tampilan dashboard utama sekarang ringkas.

## Alur dan peran CRM (kompatibilitas alur lama)

1. Pengunjung mengirim formulir di `/inquiry`, atau mengikuti konsultasi bot WhatsApp. Bot menanyakan layanan, nama, **email opsional** (`-` untuk melewati), tanggal acara (`YYYY-MM-DD` atau `-`), dan kebutuhan. Nomor WhatsApp diambil dari pengirim. Inquiry bot **baru dibuat setelah `SETUJU`**; `TIDAK` membatalkan. Formulir website juga membutuhkan persetujuan.
2. Inquiry berstatus `baru`, **belum otomatis membuat/menautkan kontak**. Untuk konsultasi dan permintaan pesanan, Owner atau Admin menugaskan inquiry kepada CS aktif; CS memverifikasi identitas sebelum memilih kontak lama atau membuat kontak baru. Keluhan WhatsApp dapat langsung ditangani CS melalui tiket tanpa menunggu verifikasi atau penugasan awal. Nomor pengirim yang sama bukan bukti identitas. Verifikasi tetap diperlukan sebelum menautkan keluhan ke profil atau membahas pesanan.
3. CS mencatat interaksi dan membuat peluang, ringkasan penawaran, serta tugas tindak lanjut. Percakapan WhatsApp **tidak otomatis** menjadi entri interaksi. Owner wajib memilih CS aktif ketika membuat kontak, tugas, atau tiket; tugas baru selalu berstatus `belum_dikerjakan`.
4. Peluang bergerak melalui `baru → dihubungi → penawaran → negosiasi`, lalu `berhasil` (dengan nilai akhir) atau `gagal` (dengan alasan). Peluang berhasil membuat **satu pesanan** dan menaikkan tahap kontak menjadi `pelanggan`. Tugas terbuka pada peluang tertutup dibatalkan otomatis dengan alasan dan waktu; tugas selesai tetap tersimpan sebagai riwayat.
5. Pesanan mempunyai status pemenuhan `baru`, `diproses`, `selesai`, atau `dibatalkan`. Pembatalan hanya oleh Owner dengan alasan. Keluhan ditangani melalui tiket, bisa tanpa pesanan dan tanpa kontak terverifikasi. Status tiket mengikuti transisi yang diizinkan (`baru → ditangani → selesai → ditutup`, dengan pembukaan kembali terbatas sebelum ditutup); tiket menjadi sumber status penyelesaian keluhan dan badge Inbox mengabaikan permintaan jika seluruh tiket terkait sudah selesai/ditutup.

| Peran | Kewenangan utama |
| --- | --- |
| Admin | Kelola akun dan layanan; lihat data dan tugaskan inquiry belum terverifikasi. Tidak memverifikasi inquiry atau mengubah data bisnis. |
| Owner | Lihat seluruh data, tugaskan inquiry/Antrean WA, kelola pekerjaan lintas CS, alihkan penanggung jawab kontak, batalkan pesanan dengan alasan, dan buka laporan. |
| CS | Lihat dan menangani inquiry sesuai cakupan; dapat mengambil tiket keluhan WhatsApp yang belum ditugaskan, tanpa verifikasi identitas untuk triase/penanganan keluhan. Verifikasi wajib sebelum menautkan kontak atau membahas data pesanan. Mengelola interaksi/peluang/tugas/pesanan/tiket sesuai akses; tidak memindahkan kepemilikan sendiri. |

Akun harus aktif dan login untuk mengakses CRM. Pemeriksaan hak akses dilakukan di server. Owner memindahkan pemilik kontak melalui **Kontak → Alihkan penanggung jawab**: pratinjau menampilkan pekerjaan terkait dan akan ditolak jika data berubah sebelum konfirmasi. Inquiry publik yang belum ditautkan tidak ikut berpindah; riwayat tugas/tiket selesai dan pekerjaan petugas bantu tetap. Untuk audit ketidaksesuaian data lama tanpa mengubahnya, jalankan `php artisan crm:audit-ownership`.

## Bot WhatsApp untuk demo lokal (Baileys)

Panduan langkah demi langkah dan pemulihan koneksi ada di [`baileys-demo/README.md`](baileys-demo/README.md). Gambaran singkat:

1. Jalankan MySQL, migrasi, dan Laravel pada `http://127.0.0.1:8000`. Biarkan `WHATSAPP_CLOUD_ENABLED=false`.
2. Di **`crm/.env`**, isi `BAILEYS_DEMO_ENABLED=true` dan `BAILEYS_DEMO_SECRET` dengan rahasia acak minimal 32 karakter. Di **`crm/baileys-demo/.env`**, isi `CRM_URL=http://127.0.0.1:8000` dan secret yang **sama**. Salin file contoh jika belum ada. Jangan menaruh secret di `.env.example` atau percakapan publik. Setelah mengubah konfigurasi Laravel, jalankan `php artisan config:clear` bila diperlukan.
3. Untuk penguji terbatas, isi `DEMO_ALLOWLIST` dengan nomor internasional `62...` tanpa `+`, pisahkan dengan koma, dan biarkan `DEMO_OPEN_MODE=false`. Untuk demo lokal yang menerima chat pribadi dari nomor mana pun, set `DEMO_OPEN_MODE=true` secara sadar dan **restart proses Node**. Mode ini membatasi 20 pesan baru per pengirim per menit; bukan jaminan ketersediaan untuk publik.
4. Dari folder `baileys-demo`, jalankan `npm ci` lalu `npm start`. Pindai QR dengan **nomor WhatsApp khusus bot demo** melalui *Perangkat tertaut*. Nomor penguji harus berbeda dari nomor bot. Tunggu `Demo WhatsApp connected`.
5. Kirim `MENU` dari nomor penguji ke nomor bot; pilih `1` untuk konsultasi atau `2` untuk FAQ. Setelah memilih layanan, isi nama → email atau `-` → tanggal atau `-` → kebutuhan → `SETUJU`. Cek **Permintaan masuk** di CRM, lalu tugaskan dan verifikasi lewat akun staf. Ketik `CS` untuk meminta petugas; Owner dapat menugaskannya di **Antrean WA**.

Saat `CS` atau setelah inquiry disetujui, bot berhenti membalas chat tersebut agar CS bisa menindaklanjuti **secara manual melalui WhatsApp nomor demo**; `MENU` mengaktifkan alur lagi. Antrean WA adalah daftar penugasan, **bukan inbox balas chat dari CRM**. Jika balasan gagal atau statusnya tidak pasti, periksa chat dan terminal bot; balasan tidak dikirim ulang otomatis karena timeout mungkin terjadi sesudah WhatsApp menerimanya. Hanya pesan teks chat pribadi yang diproses, bukan grup/media.

Tombol WhatsApp landing page menggunakan `CRM_WHATSAPP_CS` dari `.env` (format `62...`) dan menyiapkan teks `MENU`. Untuk demo lokal, arahkan ke nomor bot khusus demo; jangan publikasikan nomor demo sebagai layanan produksi. Halaman `/konsultasi` (alternatif bot website) masih dapat diakses langsung, tetapi tidak ditautkan dari landing page; inquiry-nya bersumber `website_bot` dan juga membutuhkan persetujuan. Formulir biasa di `/inquiry` tetap tersedia.

**Keselamatan operasi:** jangan bagikan QR, `baileys-demo/auth/`, `.env`, secret, atau chat pelanggan. `auth/` adalah kredensial sesi. Endpoint `/internal/baileys-demo/message` hanya untuk koneksi loopback dengan secret; jangan pasang tunnel, port-forward, atau proxy publik. Hentikan Node untuk menghentikan bot; matikan mode terbuka dengan `DEMO_OPEN_MODE=false` dan restart Node. Koneksi Baileys bisa putus dan akun berisiko dibatasi. Mode demo bukan pengganti API resmi Meta.

## Integrasi Meta Cloud API (opsional, tidak aktif)

Cloud API memakai webhook `GET/POST /webhooks/whatsapp`, verifikasi token dan tanda tangan, deduplikasi ID pesan, serta antrean pengiriman. Pengaturan ada di `.env.example` dan `config/whatsapp.php`; **jangan mengaktifkan Cloud API bersamaan dengan Baileys untuk nomor/alur yang sama**, karena state percakapan masih berbasis nomor pengirim. `php artisan whatsapp:check` hanya memeriksa kesiapan konfigurasi lokal, bukan membuktikan webhook publik dapat dijangkau atau akun Meta sehat.

Jika suatu saat beralih ke integrasi resmi: siapkan HTTPS publik, nomor dan akun Meta yang sehat, token privat, webhook, queue worker, serta prosedur balasan manual CS sebelum mengaktifkan `WHATSAPP_CLOUD_ENABLED=true`. Perhatikan aturan pesan dan jendela layanan Meta. Jangan memindahkan nomor WhatsApp Business App yang masih digunakan tanpa keputusan migrasi/coexistence yang jelas.

## Pengujian dan pemeriksaan sebelum demo

**Jangan gunakan database demo sebagai database tes.** `phpunit.xml` mengarahkan pengujian ke MySQL `relasiku_crm_test`; buat database itu secara terpisah dan jalankan migrasi dengan konfigurasi database uji tanpa mengubah `.env` utama. Contoh di PowerShell (gunakan terminal khusus, tutup setelah selesai):

```powershell
$env:DB_DATABASE='relasiku_crm_test'
php artisan migrate
Remove-Item Env:DB_DATABASE
php artisan test --compact
npm run build
php artisan view:cache
```

Jalankan `npm test` dari folder `baileys-demo` untuk tes helper Node. Tes otomatis tidak menggantikan uji WhatsApp end-to-end: periksa `MENU` → FAQ/konsultasi → `SETUJU` → inquiry CRM → penugasan/ verifikasi CS, serta `CS` → Antrean WA. Periksa koneksi Node, Laravel, MySQL, layanan aktif, nomor bot, dan allowlist/mode terbuka sebelum presentasi. Tidak ada keharusan menjalankan migrasi ulang bila semuanya sudah berstatus `Ran` (`php artisan migrate:status`).

Pemulihan kata sandi pada `MAIL_MAILER=log` menulis tautan reset ke `storage/logs/laravel.log`, bukan mengirim email. Untuk deployment dengan tugas penjadwal, jalankan `php artisan schedule:work` atau `schedule:run` tiap menit; tugas terlambat tetap dihitung dari tenggat walau penjadwal tidak berjalan. Zona waktu laporan: Asia/Jakarta.

## Batasan

Belum ada pembayaran daring, stok/manajemen produksi rinci, penawaran multi-item berhistori versi, sinkronisasi Instagram, ataupun inbox WhatsApp manual di CRM. Bot Baileys bekerja hanya selama komputer, internet, Node, Laravel, dan MySQL aktif; tidak dijanjikan bebas gangguan. Setiap inquiry tetap memerlukan verifikasi manusia sebelum ditautkan ke kontak.
