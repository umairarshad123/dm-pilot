<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for Meta Data Deletion and Deauthorize callbacks (signed_request).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->string('confirmation_code', 64)->unique();
            $table->string('type', 20)->default('deletion'); // deletion | deauthorize
            $table->string('meta_user_id')->nullable(); // app-scoped user id from signed_request (NOT a PSID/IGSID)
            // pending | completed | no_data | failed
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('conversations_deleted')->default(0);
            $table->unsignedInteger('messages_deleted')->default(0);
            $table->unsignedInteger('webhook_events_deleted')->default(0);
            $table->unsignedInteger('accounts_affected')->default(0);
            $table->json('details')->nullable();
            $table->timestamp('issued_at')->nullable(); // signed_request issued_at
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['meta_user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_deletion_requests');
    }
};
