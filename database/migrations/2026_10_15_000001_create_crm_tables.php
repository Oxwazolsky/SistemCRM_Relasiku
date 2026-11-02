<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('cs')->index();
            $table->boolean('active')->default(true);
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->index();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 40)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('organization')->nullable();
            $table->string('stage', 30)->default('lead')->index();
            $table->string('source', 50)->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->date('event_date')->nullable();
            $table->text('requirement');
            $table->timestamp('consent_at');
            $table->string('source', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('channel', 50);
            $table->timestamp('occurred_at')->index();
            $table->text('summary');
            $table->text('next_step')->nullable();
            $table->timestamps();
        });

        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->decimal('estimated_value', 15, 2)->nullable();
            $table->text('quote_summary')->nullable();
            $table->decimal('final_value', 15, 2)->nullable();
            $table->string('stage', 30)->default('baru')->index();
            $table->text('lost_reason')->nullable();
            $table->timestamp('closed_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->timestamp('due_at')->index();
            $table->timestamp('last_reminded_at')->nullable();
            $table->string('status', 30)->default('belum_dikerjakan');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['assigned_to', 'status', 'due_at']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->string('order_code')->unique();
            $table->decimal('final_value', 15, 2);
            $table->string('status', 30)->default('baru')->index();
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->text('resolution')->nullable();
            $table->string('status', 30)->default('baru')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('entity_type', 80);
            $table->unsignedBigInteger('entity_id');
            $table->string('action', 50);
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('opportunities');
        Schema::dropIfExists('interactions');
        Schema::dropIfExists('inquiries');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('services');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'active']);
        });
    }
};
