<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_bot_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 40)->unique();
            $table->string('step', 40)->default('menu');
            $table->json('answers')->nullable();
            $table->boolean('handed_off')->default(false);
            $table->foreignId('inquiry_id')->nullable()->constrained('inquiries')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('whatsapp_bot_messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 255)->unique();
            $table->foreignId('conversation_id')->constrained('whatsapp_bot_conversations')->cascadeOnDelete();
            $table->text('reply')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_bot_messages');
        Schema::dropIfExists('whatsapp_bot_conversations');
    }
};
