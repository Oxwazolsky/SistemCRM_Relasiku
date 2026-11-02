<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->string('intent', 30)->default('consultation');
            $table->foreignId('opportunity_id')->nullable()->unique()->constrained()->restrictOnDelete();
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('contact_id')->nullable()->change();
            $table->unsignedBigInteger('assigned_to')->nullable()->change();
            $table->foreignId('inquiry_id')->nullable()->unique()->constrained()->restrictOnDelete();
        });
        Schema::create('bot_faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Intake tickets may have no verified contact. Do not destroy or invalidate them on rollback.
        throw new RuntimeException('Forward-only migration: retain customer intake data; restore a reviewed backup to roll back.');
    }
};
