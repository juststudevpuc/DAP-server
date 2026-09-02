<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_action_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->integer('week_number');
            $table->date('start_date');
            $table->date('end_date');

            // Targets
            $table->integer('target_completed_training')->default(0);
            $table->integer('target_completed_onboarding')->default(0);
            $table->integer('target_graduated')->default(0);

            // Last Week Summary (Nullable because the very first week won't have last week's data)
            $table->integer('last_week_training_qty')->nullable();
            $table->decimal('last_week_training_pct', 5, 2)->nullable();
            $table->integer('last_week_onboarding_qty')->nullable();
            $table->decimal('last_week_onboarding_pct', 5, 2)->nullable();
            $table->integer('last_week_graduated_qty')->nullable();
            $table->decimal('last_week_graduated_pct', 5, 2)->nullable();

            // Reflections
            $table->text('what_worked')->nullable();
            $table->text('what_didnt_work')->nullable();
            $table->text('what_to_improve')->nullable();
            $table->text('what_is_next')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_action_plans');
    }
};
