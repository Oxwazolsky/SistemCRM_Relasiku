<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->string('submitted_name')->nullable();
            $table->string('submitted_phone', 40)->nullable();
            $table->string('submitted_email')->nullable();
            $table->string('status', 20)->default('baru')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
        });

        // Preserve all historical links before allowing new requests without a contact.
        DB::table('inquiries')->join('contacts', 'contacts.id', '=', 'inquiries.contact_id')
            ->select('inquiries.id', 'contacts.name', 'contacts.phone', 'contacts.email', 'contacts.owner_id')
            ->orderBy('inquiries.id')->chunk(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('inquiries')->where('id', $row->id)->update([
                        'submitted_name' => $row->name,
                        'submitted_phone' => $row->phone,
                        'submitted_email' => $row->email,
                        'status' => 'terverifikasi',
                        'assigned_to' => $row->owner_id,
                    ]);
                }
            });
        Schema::table('inquiries', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->change();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('inquiries')->whereNull('contact_id')->exists()) {
            throw new RuntimeException('Cannot roll back inquiry migration while unverified requests exist. Back up and resolve them first.');
        }
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['cancelled_at', 'cancellation_reason']));
        Schema::table('inquiries', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable(false)->change();
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['submitted_name', 'submitted_phone', 'submitted_email', 'status', 'verified_at']);
        });
    }
};
