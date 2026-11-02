<?php

namespace App\Services;

use App\Models\{Inquiry, Service, WhatsappBotConversation, WhatsappBotMessage};
use Illuminate\Support\Facades\DB;

class WhatsappConsultationBot
{
    public function receive(string $phone, string $messageId, string $text): string
    {
        // Meta wamid values are globally unique; Baileys IDs are explicitly namespaced by its adapter.
        if (! preg_match('/^[1-9][0-9]{7,14}$/', $phone) || $messageId === '' || strlen($messageId) > 255 || mb_strlen($text) > 5000) {
            throw new \InvalidArgumentException('Invalid WhatsApp sender or message ID.');
        }
        return DB::transaction(function () use ($phone, $messageId, $text) {
            $seen = WhatsappBotMessage::where('message_id', $messageId)->first();
            if ($seen) {
                if ($seen->sender !== $phone) throw new \InvalidArgumentException('Message ID belongs to a different sender.');
                return $seen->reply;
            }
            // Lock the sender's row so concurrent messages cannot overwrite answers.
            WhatsappBotConversation::firstOrCreate(['phone' => $phone], ['step' => 'menu', 'answers' => []]);
            $conversation = WhatsappBotConversation::where('phone', $phone)->lockForUpdate()->firstOrFail();
            $seen = WhatsappBotMessage::where('message_id', $messageId)->first();
            if ($seen) {
                if ($seen->sender !== $phone) throw new \InvalidArgumentException('Message ID belongs to a different sender.');
                return $seen->reply;
            }
            $reply = $this->advance($conversation, trim($text));
            WhatsappBotMessage::create(['message_id' => $messageId, 'conversation_id' => $conversation->id, 'sender' => $phone, 'reply' => $reply]);
            return $reply;
        });
    }

    private function advance(WhatsappBotConversation $conversation, string $text): string
    {
        $command = mb_strtolower($text);
        // While CS owns the chat, only MENU explicitly resumes the bot.
        if ($conversation->handed_off && $conversation->step === 'handoff' && $command !== 'menu') return '';
        if (in_array($command, ['halo', 'hai', 'hi', 'hello', 'hey', 'assalamualaikum', 'assalamu alaikum', 'pagi', 'siang', 'sore', 'malam'], true)) {
            $conversation->update(['step' => 'menu', 'answers' => [], 'handed_off' => false]);
            return "Halo, selamat datang di *RelasiKu*. Senang dapat membantu Anda. Kami siap menerima konsultasi layanan, pertanyaan pesanan, maupun keluhan dan akan menghubungkan Anda dengan tim yang tepat.\n\nKetik *MENU* untuk melihat pilihan layanan, atau *CS* jika ingin berbicara langsung dengan petugas.\n\nJangan kirim kata sandi, kode OTP, atau data pembayaran melalui chat.";
        }
        if (in_array($command, ['cs', 'bantuan', 'bicara dengan cs'], true)) {
            // No CRM inquiry without consent; the internal queue still records the handoff request.
            $conversation->update(['handed_off' => true, 'step' => 'handoff', 'assigned_to' => null, 'inquiry_id' => null, 'handling_status' => 'active']);
            return "Baik, Anda ingin berbicara dengan tim RelasiKu. Permintaan bantuan sudah diterima.\n\nSaya berhenti membalas otomatis agar percakapan dapat dilanjutkan oleh tim kami. Waktu respons mengikuti ketersediaan tim.\n\nKetik *MENU* jika ingin kembali ke asisten otomatis.";
        }
        if ($command === 'menu' || $conversation->step === 'menu') {
            if ($command === 'menu' || $text === '') {
                // Retain inquiry_id and assigned_to as historical ownership; handed_off marks
                // only an active handoff and must be cleared before the bot resumes.
                $conversation->update(['step' => 'menu', 'answers' => [], 'handed_off' => false]);
                return "*Menu layanan RelasiKu*\nSilakan pilih topik agar kami dapat mengarahkan Anda dengan tepat:\n\n*1.* Konsultasi produk dan layanan\n*2.* Pertanyaan umum\n*3.* Informasi pesanan atau kendala pesanan\n*4.* Keluhan pelanggan\n\nBalas dengan nomor pilihan. Ketik *CS* kapan saja untuk meminta bantuan petugas.\n\nUntuk menjaga keamanan, jangan kirim kata sandi, kode OTP, atau data pembayaran melalui chat.";
            }
            if ($text === '4') {
                $services = Service::where('active', true)->orderBy('id')->get();
                if ($services->isEmpty()) return 'Mohon maaf, daftar layanan sedang tidak tersedia. Ketik *CS* untuk meminta bantuan petugas atau *MENU* untuk kembali ke awal.';
                $conversation->update(['step' => 'service', 'answers' => ['intent' => 'complaint', 'service_choices' => $services->pluck('id')->all()]]);
                return "*Pencatatan keluhan*\nPilih layanan yang berkaitan dengan masalah Anda. Jika tidak yakin, pilih yang paling mendekati—tim kami akan memeriksa kembali.\n\n".$services->map(fn ($service, $i) => '*'.($i + 1).'.* '.$service->name)->implode("\n")."\n\nKetik *CS* untuk bantuan atau *MENU* untuk kembali.";
            }
            if (in_array($text, ['1', '3'], true)) {
                $services = Service::where('active', true)->orderBy('id')->get();
                if ($services->isEmpty()) return 'Mohon maaf, daftar layanan sedang tidak tersedia. Ketik *CS* untuk meminta bantuan petugas atau *MENU* untuk kembali ke awal.';
                $conversation->update(['step' => 'service', 'answers' => ['intent' => match ($text) { '3' => 'order', default => 'consultation' }, 'service_choices' => $services->pluck('id')->all()]]);
                $intro = $text === '3'
                    ? "*Informasi pesanan*\nPilih layanan yang berkaitan dengan pesanan atau kendala Anda. Kami akan mencatat ringkasan agar tim dapat meninjau dan membantu langkah berikutnya. Pengisian ini belum mengubah atau mengonfirmasi pesanan."
                    : "*Konsultasi produk dan layanan*\nPilih layanan yang ingin Anda ketahui atau diskusikan. Setelah itu, kami akan menanyakan beberapa informasi singkat agar tim RelasiKu dapat memahami kebutuhan Anda.";
                return $intro."\n\n".$services->map(fn ($service, $i) => '*'.($i + 1).'.* '.$service->name)->implode("\n")."\n\nBalas nomor layanan. Ketik *CS* untuk bantuan petugas atau *MENU* untuk kembali.";
            }
            if ($text === '2') {
                $faqs = \App\Models\BotFaq::where('active', true)->orderBy('id')->get();
                if ($faqs->isNotEmpty()) {
                    $conversation->update(['step' => 'managed_faq', 'answers' => ['faq_choices' => $faqs->pluck('id')->all()]]);
                    return "*Pertanyaan umum*\n\n".$faqs->map(fn ($faq, $i) => '*'.($i + 1).'.* '.$faq->question)->implode("\n")."\n\nBalas nomor pertanyaan untuk membaca jawabannya. Ketik *MENU* untuk kembali atau *CS* untuk bantuan tim.";
                }
                $conversation->update(['step' => 'faq']);
                return "*Pertanyaan Umum — RelasiKu*\n\n*1.* Bagaimana penentuan harga?\n*2.* Berapa lama waktu pengerjaan?\n*3.* Bagaimana cara memulai pemesanan?\n\nBalas *1*, *2*, atau *3* untuk melihat jawabannya. Ketik *MENU* untuk kembali ke awal atau *CS* untuk meminta bantuan petugas.";
            }
            return 'Pilihan belum dikenali. Balas *1* untuk konsultasi, *2* untuk pertanyaan umum, atau *CS* untuk meminta bantuan petugas. Ketik *MENU* untuk melihat pilihan dari awal.';
        }
        if ($conversation->step === 'managed_faq') {
            $choices = $conversation->answers['faq_choices'] ?? [];
            $id = ctype_digit($text) ? ($choices[(int) $text - 1] ?? null) : null;
            $faq = \App\Models\BotFaq::whereKey($id)->where('active', true)->first();
            if ($faq) return $faq->answer."\nBalas nomor pertanyaan lain untuk membaca jawabannya, atau ketik *MENU* untuk kembali dan *CS* untuk bantuan tim.";
            $conversation->update(['step' => 'menu', 'answers' => []]);
            return 'Pertanyaan tersebut belum tersedia. Ketik *2* untuk melihat daftar pertanyaan terbaru, *MENU* untuk kembali, atau *CS* untuk bantuan tim.';
        }
        if ($conversation->step === 'faq') {
            return match ($text) {
                '1' => "*Bagaimana penentuan harga?*\nBiaya disesuaikan dengan jenis produk, jumlah, bahan, dan detail desain yang Anda butuhkan. Tim kami akan meninjau kebutuhan Anda terlebih dahulu sebelum memberikan penawaran; bot tidak menetapkan harga akhir.\n\nKetik *MENU* untuk konsultasi layanan atau *CS* untuk meminta bantuan petugas.",
                '2' => "*Berapa lama waktu pengerjaan?*\nEstimasi waktu bergantung pada spesifikasi, jumlah pesanan, dan jadwal produksi. Tim kami akan mengonfirmasi tenggat setelah kebutuhan Anda ditinjau; bot belum dapat menjanjikan tanggal selesai.\n\nKetik *MENU* untuk konsultasi layanan atau *CS* untuk meminta bantuan petugas.",
                '3' => "*Bagaimana cara memulai pemesanan?*\nKetik *MENU*, lalu pilih *1* untuk menyampaikan kebutuhan Anda. Setelah Anda menyetujui pencatatannya, tim kami akan meninjau permintaan dan menghubungi Anda untuk membahas langkah berikutnya. Pengisian konsultasi belum berarti pesanan dikonfirmasi.\n\nKetik *MENU* untuk mulai atau *CS* untuk meminta bantuan petugas.",
                default => 'Pilihan FAQ belum dikenali. Silakan balas *1*, *2*, atau *3*. Ketik *MENU* untuk kembali ke awal atau *CS* untuk meminta bantuan petugas.',
            };
        }
        $answers = $conversation->answers ?? [];
        if ($conversation->step === 'service') {
            $choices = $answers['service_choices'] ?? [];
            if (! ctype_digit($text) || (int) $text < 1 || (int) $text > count($choices)) return 'Nomor layanan belum sesuai dengan daftar. Silakan balas nomor layanan yang tersedia, *MENU* untuk mulai ulang, atau *CS* untuk meminta bantuan petugas.';
            $answers['service_id'] = $choices[(int) $text - 1];
            unset($answers['service_choices']);
            $conversation->update(['answers' => $answers, 'step' => 'name']);
            $prompt = match ($answers['intent'] ?? 'consultation') {
                'complaint' => 'Saya akan membantu mencatat keluhan Anda untuk ditinjau oleh tim terkait. Mohon jangan sertakan kata sandi, kode OTP, atau data pembayaran.',
                'order' => 'Baik, kami bantu mencatat informasi pesanan atau kendalanya. Tim akan meninjau lebih lanjut; percakapan ini tidak mengubah status pesanan secara otomatis.',
                default => 'Baik, kami bantu menyiapkan konsultasi agar tim dapat memahami kebutuhan Anda dengan lebih baik.',
            };
            return $prompt."\n\nUntuk keperluan tindak lanjut, siapa nama Anda?";
        }
        if ($conversation->step === 'name') {
            if (mb_strlen($text) < 2 || mb_strlen($text) > 255) return 'Mohon tuliskan nama dengan panjang 2–255 karakter agar dapat kami catat dengan benar.';
            $answers['name'] = $text;
            $conversation->update(['answers' => $answers, 'step' => 'email']);
            return 'Terima kasih, *'.$answers['name'].'*. Apakah Anda ingin menambahkan email untuk tindak lanjut? Email bersifat *opsional*; balas *-* jika ingin melewati.';
        }
        if ($conversation->step === 'email') {
            if ($text !== '-' && (mb_strlen($text) > 255 || ! filter_var($text, FILTER_VALIDATE_EMAIL))) {
                return 'Alamat email belum sesuai format. Silakan tulis email yang valid (maksimal 255 karakter), atau balas *-* untuk melewati.';
            }
            $answers['email'] = $text === '-' ? null : $text;
            $conversation->update(['answers' => $answers, 'step' => 'date']);
            return match ($answers['intent'] ?? 'consultation') {
                'complaint' => 'Kapan masalah atau keluhan ini terjadi? Tulis tanggal dengan format *YYYY-MM-DD* jika ingat, atau balas *-* bila tidak yakin.',
                'order' => 'Kapan pesanan dibuat atau kendala mulai terjadi? Tulis tanggal *YYYY-MM-DD* jika ingat, atau balas *-* bila tidak yakin.',
                default => 'Kapan layanan ini dibutuhkan atau acara Anda direncanakan? Tulis tanggal *YYYY-MM-DD* jika sudah ada, atau balas *-* bila belum ditentukan.',
            };
        }
        if ($conversation->step === 'date') {
            if ($text !== '-') {
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $text);
                if (! $date || $date->format('Y-m-d') !== $text) return 'Tanggal belum sesuai. Mohon gunakan format *YYYY-MM-DD* (contoh: 2027-12-01), atau balas *-* jika belum tahu.';
            }
            $answers['event_date'] = $text === '-' ? null : $text;
            $conversation->update(['answers' => $answers, 'step' => 'requirement']);
            return match ($answers['intent'] ?? 'consultation') {
                'complaint' => "Mohon jelaskan masalah yang terjadi, layanan atau pesanan terkait, serta dampaknya. Tuliskan informasi seperlunya tanpa menyertakan kata sandi, kode OTP, atau data pembayaran. (Maksimal 5.000 karakter.)",
                'order' => "Jelaskan nomor atau detail pesanan jika tersedia, kendala yang dialami, dan bantuan yang Anda harapkan. Jangan sertakan kata sandi, kode OTP, atau data pembayaran. (Maksimal 5.000 karakter.)",
                default => 'Ceritakan layanan atau produk yang Anda minati, perkiraan jumlah/kebutuhan, serta pertanyaan yang ingin dibahas. Informasi awal saja cukup—tim kami dapat membantu merincinya. (Maksimal 5.000 karakter).',
            };
        }
        if ($conversation->step === 'requirement') {
            if (mb_strlen($text) < 5 || mb_strlen($text) > 5000) {
                return match ($answers['intent'] ?? 'consultation') {
                    'complaint' => 'Mohon tambahkan sedikit detail tentang masalah atau dampaknya (5–5.000 karakter). Hindari kata sandi, kode OTP, dan data pembayaran.',
                    'order' => 'Mohon jelaskan kendala pesanan dan bantuan yang diperlukan (5–5.000 karakter). Jangan sertakan data rahasia atau pembayaran.',
                    default => 'Mohon tambahkan sedikit detail tentang layanan atau produk yang ingin dikonsultasikan (5–5.000 karakter).',
                };
            }
            $answers['requirement'] = $text;
            $conversation->update(['answers' => $answers, 'step' => 'consent']);
            $purpose = match ($answers['intent'] ?? 'consultation') {
                'complaint' => 'keluhan Anda sebagai tiket agar dapat ditinjau oleh tim terkait',
                'order' => 'informasi pesanan atau kendala Anda agar tim dapat memeriksa dan menindaklanjutinya',
                default => 'informasi kebutuhan konsultasi Anda agar tim RelasiKu dapat menyiapkan tindak lanjut yang sesuai',
            };
            $note = ($answers['intent'] ?? '') === 'order' ? ' Pencatatan ini tidak mengubah atau mengonfirmasi pesanan.' : '';
            return "*Mohon konfirmasi*\nApakah Anda mengizinkan kami mencatat nama, nomor WhatsApp, serta informasi yang Anda berikan".($answers['email'] || $answers['event_date'] ? ' (termasuk email atau tanggal jika diisi)' : '')." sebagai ".$purpose."? Tim kami akan memverifikasi identitas sebelum membahas data pelanggan atau pesanan.".$note."\n\nBalas *SETUJU* untuk melanjutkan atau *TIDAK* untuk membatalkan. Anda juga dapat mengetik *CS* untuk meminta bantuan petugas.\n\nJangan kirim kata sandi, kode OTP, atau data pembayaran.";
        }
        if ($conversation->step === 'consent') {
            if ($command === 'tidak') {
                $conversation->update(['answers' => [], 'step' => 'menu']);
                return 'Baik, pencatatan dibatalkan. Tidak ada permintaan atau tiket baru yang dibuat; riwayat percakapan tetap tersimpan. Ketik *MENU* untuk mulai lagi atau *CS* untuk bantuan tim.';
            }
            if ($command !== 'setuju') return 'Mohon balas *SETUJU* untuk mengizinkan pencatatan dan tindak lanjut, *TIDAK* untuk membatalkan, atau *CS* untuk meminta bantuan petugas.';
            if (! Service::whereKey($answers['service_id'] ?? null)->where('active', true)->exists()) {
                $conversation->update(['step' => 'menu', 'answers' => []]);
                return 'Mohon maaf, layanan yang dipilih sudah tidak tersedia. Ketik *MENU* untuk memilih layanan lain atau *CS* untuk meminta bantuan petugas.';
            }
            $inquiry = Inquiry::create(['submitted_name' => $answers['name'], 'submitted_phone' => $conversation->phone,
                'submitted_email' => $answers['email'] ?? null,
                'service_id' => $answers['service_id'], 'event_date' => $answers['event_date'],
                'requirement' => $answers['requirement'], 'intent' => $answers['intent'] ?? 'consultation', 'consent_at' => now(), 'source' => 'whatsapp', 'status' => 'baru']);
            if ($inquiry->intent === 'complaint') {
                // A sender number or order reference is not identity verification.
                \App\Models\Ticket::create(['inquiry_id' => $inquiry->id, 'subject' => 'Keluhan WhatsApp #'.$inquiry->id,
                    'description' => $inquiry->requirement, 'status' => 'baru']);
            }
            $conversation->update(['inquiry_id' => $inquiry->id, 'step' => 'handoff', 'handed_off' => true, 'assigned_to' => null]);
            if ($inquiry->intent === 'complaint') return 'Keluhan Anda telah dicatat sebagai tiket untuk ditinjau CS. Petugas akan memverifikasi identitas sebelum membahas pesanan atau data pelanggan. Terima kasih telah menghubungi RelasiKu. Ketik *MENU* jika ingin memulai percakapan baru.';
            return 'Terima kasih! Permintaan *#'.$inquiry->id.'* berhasil dicatat dan akan ditinjau oleh tim RelasiKu. CS akan memverifikasi informasi Anda sebelum menindaklanjuti; waktu respons bergantung pada ketersediaan tim. Pesanan belum dikonfirmasi. Ketik *MENU* jika ingin memulai konsultasi baru.';
        }
        return 'Untuk melanjutkan, ketik *MENU* agar pilihan konsultasi ditampilkan, atau *CS* untuk meminta bantuan petugas.';
    }
}
