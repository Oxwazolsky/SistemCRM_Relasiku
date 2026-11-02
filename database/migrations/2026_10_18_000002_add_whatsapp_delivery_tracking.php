<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_bot_messages', function (Blueprint $table) {
            $table->string('sender', 40)->nullable();
            $table->string('delivery_status', 20)->default('pending');
            $table->string('outbound_message_id')->nullable();
            $table->text('delivery_error')->nullable();
            $table->timestamp('sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_bot_messages', function (Blueprint $table) {
            $table->dropColumn(['sender', 'delivery_status', 'outbound_message_id', 'delivery_error', 'sent_at']);
        });
    }
};
