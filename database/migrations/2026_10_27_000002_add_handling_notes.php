<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['inquiries', 'whatsapp_bot_conversations'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('handling_status', 20)->default('active')->index();
            });
        }
        // Deliberately no cascading entity FK: conversation resets must never erase outcomes.
        Schema::create('handling_notes', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $table->uuid('submission_id')->unique();
            $table->string('action', 20);
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handling_notes');
        foreach (['inquiries', 'whatsapp_bot_conversations'] as $name) Schema::table($name, fn (Blueprint $table) => $table->dropColumn('handling_status'));
    }
};
