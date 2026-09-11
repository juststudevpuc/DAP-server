<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('weekly_action_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('weekly_action_plans', 'is_completed')) {
                $table->boolean('is_completed')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weekly_action_plans', function (Blueprint $table) {
            if (Schema::hasColumn('weekly_action_plans', 'is_completed')) {
                $table->dropColumn('is_completed');
            }
        });
    }
};
