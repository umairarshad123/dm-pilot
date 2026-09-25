<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20); // facebook | instagram
            // facebook_login: Page token via graph.facebook.com (Messenger + IG via FB Login)
            // instagram_login: Instagram user token via graph.instagram.com
            $table->string('auth_type', 30)->default('facebook_login');
            $table->string('page_id')->nullable();
            $table->string('instagram_account_id')->nullable();
            $table->string('page_name')->nullable();
            $table->text('access_token'); // encrypted cast
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('token_checked_at')->nullable();
            $table->boolean('active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'page_id']);
            $table->unique(['platform', 'instagram_account_id']);
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_accounts');
    }
};
