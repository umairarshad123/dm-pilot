<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_account_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20);
            $table->string('external_user_id'); // PSID (Messenger) / IGSID (Instagram)
            $table->string('customer_name')->nullable();
            $table->string('status', 20)->default('open'); // open | closed
            $table->boolean('bot_enabled')->default(true);
            $table->boolean('human_takeover')->default(false);
            $table->timestamp('bot_paused_until')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_customer_message_at')->nullable(); // 24h messaging window
            $table->json('meta')->nullable(); // future: lead data, tags, stage, CRM ids
            $table->timestamps();

            $table->unique(['meta_account_id', 'external_user_id']);
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
