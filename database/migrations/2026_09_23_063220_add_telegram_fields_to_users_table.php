<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Check if column doesn't exist before adding to prevent errors
            if (!Schema::hasColumn('users', 'telegram_chat_id')) {
                $table->string('telegram_chat_id')->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'telegram_notifications_enabled')) {
                $table->boolean('telegram_notifications_enabled')->default(true)->after('telegram_chat_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telegram_chat_id', 'telegram_notifications_enabled']);
        });
    }
};
