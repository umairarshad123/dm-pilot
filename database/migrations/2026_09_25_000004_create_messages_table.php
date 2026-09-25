<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('external_message_id')->nullable()->unique(); // Meta "mid"; dedup key
            $table->string('direction', 10);   // incoming | outgoing
            $table->string('sender_type', 10); // customer | bot | human
            $table->text('body')->nullable();
            $table->json('attachments')->nullable();
            $table->json('payload')->nullable(); // sanitized raw messaging event
            // received | pending | sent | failed | skipped
            $table->string('status', 20)->default('received');
            $table->text('error')->nullable();
            // Bot reply -> the incoming message it answers. Unique = never reply twice to one message.
            $table->foreignId('in_reply_to_id')->nullable()->unique()->constrained('messages')->nullOnDelete();
            $table->timestamp('sent_at')->nullable(); // Meta timestamp (incoming) or delivery time (outgoing)
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
