<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row with meta_account_id = NULL is the global default; per-account rows override it.
        Schema::create('bot_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_account_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->boolean('bot_enabled')->default(true);
            $table->text('system_prompt')->nullable();
            $table->text('business_info')->nullable();
            $table->json('faqs')->nullable();              // [{question, answer}]
            $table->text('offers')->nullable();            // offer / pricing info
            $table->json('channel_instructions')->nullable(); // {facebook: "...", instagram: "..."}
            $table->string('model')->nullable();
            $table->decimal('temperature', 3, 2)->nullable();
            $table->unsignedInteger('max_output_tokens')->nullable();
            $table->unsignedInteger('history_limit')->nullable();
            $table->unsignedInteger('reply_delay_seconds')->nullable();
            $table->unsignedInteger('human_takeover_minutes')->nullable();
            $table->text('fallback_message')->nullable(); // null = stay silent when AI fails
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_settings');
    }
};
