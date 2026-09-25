<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contact (CRM) profile on conversations: one conversation = one contact per connected Page / IG account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('customer_name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('username')->nullable()->after('last_name'); // Instagram handle
            $table->text('profile_pic_url')->nullable()->after('username'); // temporary Meta CDN URL
            $table->timestamp('profile_fetched_at')->nullable()->after('profile_pic_url');
            $table->string('email')->nullable()->after('profile_fetched_at');
            $table->string('phone', 40)->nullable()->after('email');
            $table->string('lead_stage', 20)->nullable()->after('phone'); // App\Enums\LeadStage
            $table->json('tags')->nullable()->after('lead_stage'); // list<string>, lowercase
            $table->text('notes')->nullable()->after('tags');
            $table->unsignedInteger('unread_count')->default(0)->after('notes');
            $table->timestamp('lead_captured_at')->nullable()->after('unread_count'); // first auto-captured email/phone

            $table->index('email');
            $table->index('phone');
            $table->index('lead_stage');
            $table->index('lead_captured_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['phone']);
            $table->dropIndex(['lead_stage']);
            $table->dropIndex(['lead_captured_at']);
            $table->dropColumn([
                'first_name', 'last_name', 'username', 'profile_pic_url', 'profile_fetched_at', 'email', 'phone',
                'lead_stage', 'tags', 'notes', 'unread_count', 'lead_captured_at',
            ]);
        });
    }
};
