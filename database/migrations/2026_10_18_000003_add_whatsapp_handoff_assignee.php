<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_bot_conversations', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_bot_conversations', fn (Blueprint $table) => $table->dropConstrainedForeignId('assigned_to'));
    }
};
