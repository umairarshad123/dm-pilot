<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('object', 30)->nullable(); // page | instagram
            $table->string('payload_hash', 64)->unique(); // sha256 of raw body: drops identical redeliveries
            $table->json('payload');
            // pending | processed | ignored | failed
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('messages_count')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
