<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_settings', function (Blueprint $table) {
            // openai | claude; NULL = inherit (account row → global row → config('ai.provider')).
            $table->string('ai_provider', 20)->nullable()->after('channel_instructions');
        });
    }

    public function down(): void
    {
        Schema::table('bot_settings', function (Blueprint $table) {
            $table->dropColumn('ai_provider');
        });
    }
};
