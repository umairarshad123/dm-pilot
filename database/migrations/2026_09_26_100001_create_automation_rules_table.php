<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keyword / welcome auto-replies. A matching rule's reply_text is sent instead of the AI reply.
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            // NULL = applies to every page; page-specific rules win over global ones.
            $table->foreignId('meta_account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('trigger', 20)->default('keyword'); // keyword | welcome
            $table->string('match_type', 20)->default('contains'); // exact | contains | starts_with | regex
            $table->json('keywords')->nullable(); // list of strings (case-insensitive); unused for welcome
            $table->text('reply_text');
            $table->integer('priority')->default(0); // higher first
            $table->boolean('active')->default(true);
            $table->unsignedInteger('trigger_count')->default(0);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['trigger', 'active', 'meta_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
    }
};
