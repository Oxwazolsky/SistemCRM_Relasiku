# Changelog - SistemCRM_Relasiku

Semua perubahan penting dalam project ini akan dicatat di file ini. Format mengikuti [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [1.0.0] - 2026-11-02

### Initial Release
Rilis awal repository SistemCRM_Relasiku setelah serangkaian revisi dan evaluasi sistem.

#### Ditambahkan
- **CRM berbasis Laravel 12** dengan fitur lengkap untuk jasa desain dan percetakan acara
- **Dashboard dan Reporting**: Dashboard dinamis per peran (Admin, Owner, CS) dengan laporan bisnis
- **Manajemen Kontak**: Verifikasi kontak, transfer antar CS, riwayat interaksi
- **Workflow Inquiry**: Dari intake → verifikasi → peluang → pesanan dengan status tracking
- **Inbox WhatsApp**: Antarmuka terpusat untuk antrean serah-terima dan permintaan pelanggan
- **Bot WhatsApp dengan Baileys**: Konsultasi otomatis, FAQ, permintaan pesanan, dan penanganan keluhan
- **Manajemen Pesanan**: Status pemenuhan (baru, diproses, selesai, dibatalkan) dengan pembatalan berbasis Owner
- **Sistem Tiket**: Untuk keluhan WhatsApp dengan status workflow (baru → ditangani → selesai → ditutup)
- **Catatan Penanganan**: Pencatatan wajib hasil penanganan inquiry/tiket dengan status (catat hasil, menunggu pelanggan, selesai)
- **Administrasi**: Manajemen akun pengguna, layanan, dan konten FAQ bot
- **Audit Trail**: Pencatatan aktivitas pengguna untuk compliance dan debugging

#### Fitur Utama
- Peran & Akses: Admin, Owner, CS dengan pembatasan kewenangan per role
- Integrasi WhatsApp: Baileys untuk demo lokal (nonproduksi), Cloud API Meta opsional
- Sistem Verifikasi: Identitas pelanggan sebelum penghubungan ke kontak/pesanan
- Workflow Peluang: Multi-tahap (baru → dihubungi → penawaran → negosiasi → berhasil/gagal)
- Penjadwalan: Pembatalan tugas otomatis pada peluang tertutup, notifikasi status
- Database MySQL: Schema terstruktur dengan migrations & seeding
- Testing: PHPUnit untuk regresi; database tes terpisah

#### Catatan Revisi & Evaluasi
- **Penyederhanaan Intake (27 Oktober 2026)**: 
  - Halaman utama redirect ke login/dashboard sesuai peran
  - Inbox WhatsApp terpusat menggabungkan permintaan dan antrean serah-terima
  - Bot menu: `1` konsultasi, `2` FAQ, `3` pesanan, `4` keluhan
  - Keluhan otomatis membuat tiket setelah `SETUJU` (bukan inquiry)
  - CS dapat mengambil keluhan WhatsApp langsung tanpa penugasan awal
  - Penawaran & konversi peluang otomatis saat pesanan terbentuk
  - Dashboard simplifisasi per peran dengan metrik relevan
  
- **Penanganan & Catatan (27 Oktober 2026)**:
  - Tabel `handling_notes` untuk pencatatan wajib hasil penanganan
  - Status penanganan: "Catat hasil penanganan", "Menunggu pelanggan", "Selesai"
  - Tiket dapat selesai tanpa membuat kontak/pesanan
  - Riwayat catatan serah-terima tersedia melalui filter
  - Catatan berpenulis, berisi timestamp server, tidak ditimpa

- **Migrasi Database**:
  - Tidak menggunakan `migrate:fresh` pada data existing (hanya `migrate`)
  - Backup database sebelum pembaruan di sistem lama
  - Migrasi forward-only untuk tiket tanpa kontak/petugas

#### Pembatasan & Catatan
- **Nonproduksi**: Baileys + Node.js lokal; bukan pengganti API Meta resmi
- **Tanpa pembayaran daring**: Sistem CRM pure, tanpa modul payment gateway
- **Tanpa stok/produksi rinci**: Fokus intake & tindak lanjut, bukan manufacturing
- **Percakapan per nomor**: Satu baris chat per nomor pengirim
- **Verifikasi wajib**: Semua inquiry harus diverifikasi CS sebelum kontak/pesanan ditautkan

#### Deployment
- Persyaratan: PHP 8.2+, Composer, Node.js 20+, MySQL 5.7+
- Setup awal: `composer install`, `npm ci`, `php artisan key:generate`, `php artisan migrate`
- Demo seeding: `php artisan db:seed` (akun demo reset setiap run; jangan ulangi pada sistem live)
- Build front-end: `npm run build`
- Jalankan: `php artisan serve --host=127.0.0.1 --port=8000`

---

## Rencana Pembaruan Mendatang

- [ ] Multi-bahasa lengkap (selain Indonesia)
- [ ] Notifikasi email/SMS terintegrasi
- [ ] Laporan export PDF/Excel
- [ ] Integrasi Cloud API Meta production-ready
- [ ] Mobile app atau progressive web app
- [ ] Analytics & business intelligence dashboard

---

## Kebijakan Versi

Format versi: `MAJOR.MINOR.PATCH`
- **MAJOR**: Perubahan breaking atau fitur signifikan
- **MINOR**: Fitur baru tanpa breaking changes
- **PATCH**: Bug fixes dan perbaikan minor
