<?php

namespace Database\Seeders;

use App\Models\{BotFaq, Service};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Reference content only: never resets accounts or overwrites edited records. */
class ServiceFaqSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach ($this->services() as $name => $category) {
                Service::firstOrCreate(['name' => $name], ['category' => $category, 'active' => true]);
            }
            foreach ($this->faqs() as $question => $answer) {
                BotFaq::firstOrCreate(['question' => $question], ['answer' => $answer, 'active' => true]);
            }
        });
    }

    public function services(): array
    {
        // Banner/spanduk are one product family; variants are discussed with CS.
        // The current Service schema has no description field; details live in FAQ.
        return [
            'Banner / Spanduk Acara' => 'Percetakan acara',
            'Poster Acara' => 'Percetakan acara',
            'Sertifikat Acara' => 'Percetakan acara',
            'Kaos Acara' => 'Sandang acara',
            'Merchandise Acara' => 'Merchandise acara',
            'Desain Grafis Acara' => 'Desain acara',
        ];
    }

    public function faqs(): array
    {
        // Twelve grouped topics keep the numbered WhatsApp menu readable.
        return [
            'Layanan apa saja yang dapat dikonsultasikan?' =>
                'Kami menerima konsultasi desain dan percetakan acara: banner/spanduk untuk informasi acara, poster untuk publikasi, sertifikat peserta atau penghargaan, kaos panitia/peserta, merchandise acara, serta desain grafis materi acara. Banner dan spanduk berada dalam satu layanan. Jenis merchandise, bahan, ukuran, teknik cetak, dan ketersediaan varian perlu dikonfirmasi CS; daftar ini bukan jaminan semua spesifikasi tersedia. Desain dapat dibahas tersendiri atau sebagai bagian kebutuhan cetak. Sampaikan jika membutuhkan beberapa produk agar CS merinci cakupannya.',
            'Bagaimana memulai konsultasi atau permintaan pesanan?' =>
                'Di WhatsApp ketik MENU lalu pilih konsultasi layanan atau permintaan pesanan. Pilih satu layanan utama, isi nama, email opsional, tanggal acara bila sudah diketahui, dan kebutuhan. Sebutkan produk tambahan di uraian kebutuhan. Periksa persetujuan sebelum menyimpan inquiry. Form konsultasi website juga dapat digunakan. Permintaan yang masuk belum merupakan pesanan terkonfirmasi; CS akan memverifikasi identitas, spesifikasi, penawaran, dan persetujuan sebelum proses berikutnya. Untuk pesanan ulang, sampaikan referensi lama kepada CS tanpa menganggap harga atau spesifikasinya otomatis sama.',
            'Spesifikasi cetak apa yang perlu disiapkan?' =>
                'Untuk banner/spanduk, tulis ukuran akhir beserta satuannya, jumlah, lokasi penggunaan dalam/luar ruang, dan kebutuhan finishing atau pemasangan untuk ditinjau CS. Untuk poster, tulis ukuran, jumlah, sisi cetak, dan preferensi kertas/finishing. Untuk sertifikat, tulis ukuran, jumlah, nama acara, teks, logo, penandatangan, serta apakah nama peserta berbeda tiap lembar. Periksa ejaan dan daftar nama sebelum persetujuan. Bahan, finishing, personalisasi, dan pemasangan tidak otomatis termasuk; pilihan serta biayanya dikonfirmasi CS.',
            'Bagaimana spesifikasi kaos, merchandise, dan desain?' =>
                'Untuk kaos acara, siapkan jumlah per ukuran, warna, preferensi bahan, posisi serta ukuran gambar, dan tanggal diperlukan. Untuk merchandise, jelaskan jenis barang yang diinginkan, fungsi, jumlah, warna, serta area logo; CS memeriksa jenis dan teknik pengerjaan yang tersedia. Untuk desain grafis, siapkan tujuan materi, tema, target pembaca, ukuran/media penggunaan, teks final, logo, referensi visual, dan keluaran yang dibutuhkan. Pengerjaan desain, berkas sumber, serta hak penggunaan hasil perlu disepakati dalam cakupan penawaran; tidak otomatis termasuk pada setiap produk.',
            'Format artwork dan cara mengirim file bagaimana?' =>
                'Sampaikan format file yang dimiliki kepada CS, misalnya PDF, PNG/JPG, atau file vektor/editable. Format yang diterima, ukuran akhir, resolusi, mode warna, bleed, font, dan kebutuhan file sumber harus mengikuti arahan CS sesuai produk; jangan menganggap gambar dari chat pasti siap cetak. Mintalah kanal pengiriman file yang disepakati, karena formulir konsultasi mencatat kebutuhan, bukan pemeriksa artwork. Gunakan logo/foto/font yang Anda berhak gunakan. Periksa pratinjau, teks, ejaan, ukuran, dan posisi sebelum menyetujui; warna layar dapat berbeda dari hasil cetak. Kebutuhan contoh cetak dibahas dengan CS.',
            'Apakah ada minimum jumlah dan paket beberapa produk?' =>
                'Tuliskan jumlah tiap produk, termasuk rincian ukuran kaos atau variasi nama sertifikat. Minimum pemesanan, kemungkinan satuan, jumlah besar, contoh produk, dan perubahan kuantitas bergantung pada produk serta proses yang tersedia dan harus dikonfirmasi CS. Untuk beberapa produk dalam satu acara, pilih layanan utama lalu tuliskan semua kebutuhan di uraian; CS akan meninjau rincian dan cara penawarannya. Tidak ada minimum, diskon jumlah, atau paket harga otomatis yang dijanjikan bot.',
            'Berapa lama pengerjaan dan apakah bisa mendesak?' =>
                'Sampaikan tanggal acara dan batas barang harus diterima secara terpisah, termasuk lokasi penerimaan. Estimasi bergantung pada kelengkapan materi, persetujuan desain, jumlah, bahan, antrean produksi, serta pengiriman. Permintaan mendesak perlu ditinjau CS; bot tidak menjamin layanan kilat maupun tanggal selesai. Perubahan desain, jumlah, atau tanggal acara dapat memengaruhi jadwal dan biaya. Mintalah konfirmasi tenggat serta langkah yang perlu diselesaikan pelanggan sebelum menyetujui penawaran. Jam layanan dan waktu respons petugas dikonfirmasi melalui CS.',
            'Bagaimana harga, penawaran, dan pembayaran ditentukan?' =>
                'Harga ditinjau berdasarkan produk, ukuran, bahan, jumlah, desain, finishing, dan kebutuhan penyerahan. Sampaikan kisaran anggaran bila ada agar CS dapat membahas opsi. Mintalah penawaran dengan cakupan, rincian biaya yang termasuk/tidak termasuk, masa berlaku, dan jadwal yang disepakati. Bot tidak menetapkan harga akhir. Metode pembayaran, uang muka bila berlaku, jatuh tempo, rekening resmi, dan konfirmasi pembayaran hanya mengikuti arahan CS yang terverifikasi; tidak ada nominal atau kebijakan bank bawaan di bot. Jangan kirim PIN, OTP, kata sandi, atau data kartu. Perubahan rekening/instruksi pembayaran perlu diverifikasi melalui kanal resmi yang telah dikenal.',
            'Bagaimana revisi desain dan perubahan atau pembatalan pesanan?' =>
                'Kirim masukan desain secara jelas dan terkumpul, lalu periksa versi terbaru sebelum persetujuan. Jumlah putaran revisi, batas cakupan, biaya tambahan, serta penyerahan file final dikonfirmasi CS dalam penawaran; revisi tidak dijanjikan tanpa batas. Perubahan teks, ukuran, bahan, jumlah, atau tanggal setelah persetujuan harus ditinjau ulang karena dapat memengaruhi harga dan jadwal, khususnya bila produksi sudah berjalan. Pembatalan dan kemungkinan pengembalian dana harus dibahas dengan CS berdasarkan kondisi pesanan dan kesepakatan; bot tidak menjanjikan pembatalan gratis maupun refund.',
            'Bagaimana pengambilan, pengiriman, dan pengecekan status?' =>
                'Sampaikan lokasi tujuan dan batas penerimaan saat konsultasi. Opsi pengambilan atau pengiriman, alamat pengambilan, jangkauan, biaya, kemasan, pihak pengantar, dan estimasi tiba dikonfirmasi CS; tidak otomatis termasuk harga produk. Untuk menanyakan perkembangan, siapkan nomor inquiry/pesanan dan identitas pemesan kepada CS. Bot tidak memberikan pelacakan produksi atau pengiriman secara langsung. CS akan memverifikasi identitas sebelum membagikan rincian pesanan. Periksa jumlah dan kondisi barang saat diterima, lalu sampaikan ketidaksesuaian melalui jalur keluhan.',
            'Bagaimana menyampaikan keluhan atau berbicara dengan CS?' =>
                'Ketik CS untuk masuk antrean bantuan manusia; balasan otomatis berhenti sampai Anda mengetik MENU. Penanganan bergantung pada ketersediaan dan penugasan petugas, bukan jaminan respons seketika. Untuk keluhan melalui WhatsApp, ketik MENU lalu pilih keluhan, pilih layanan terkait, dan tuliskan referensi pesanan jika ada, kronologi, bagian yang bermasalah, jumlah terdampak, serta penyelesaian yang diharapkan. Setelah SETUJU, keluhan dicatat sebagai inquiry dan tiket. Siapkan foto atau bukti untuk dikirim lewat kanal yang diarahkan CS. Petugas memverifikasi identitas serta masalah sebelum mengonfirmasi langkah penanganan; cetak ulang, penggantian, atau refund tidak otomatis dijanjikan.',
            'Bagaimana persetujuan dan privasi data konsultasi?' =>
                'Data yang Anda isi digunakan untuk meninjau kebutuhan dan tindak lanjut CS. Pada WhatsApp, SETUJU menyimpan inquiry (dan tiket untuk keluhan); TIDAK membatalkan pembuatan inquiry, bukan jaminan menghapus riwayat percakapan yang sudah tercatat. Percakapan bot dan antrean bantuan dapat tersimpan untuk operasional walaupun inquiry belum dibuat. Website meminta persetujuan sebelum mencatat inquiry. Berikan hanya data yang diperlukan; jangan mengirim OTP, kata sandi, data kartu, atau dokumen sensitif yang tidak diminta. Untuk koreksi data, pertanyaan akses/penyimpanan, atau permintaan penghapusan, hubungi CS agar identitas dan kebijakan yang berlaku diperiksa; bot tidak menetapkan masa retensi atau menjanjikan penghapusan otomatis.',
        ];
    }
}
