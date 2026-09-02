<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_plan_id')->constrained('weekly_action_plans')->cascadeOnDelete();

            $table->enum('day_name', ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']);
            $table->date('record_date');

            // Training Counts
            $table->integer('train_expected')->default(0);
            $table->integer('train_completed')->default(0);
            $table->integer('train_cancel_delay')->default(0);

            // Onboarding Counts
            $table->integer('onboard_company_info')->default(0);
            $table->integer('onboard_system_analysis')->default(0);
            $table->integer('onboard_configure_hr')->default(0);
            $table->integer('onboard_provide_lesson')->default(0);
            $table->integer('onboard_success')->default(0);

            // Graduated Counts
            $table->integer('grad_certificate')->default(0);
            $table->integer('grad_hr_policy')->default(0);
            $table->integer('grad_book')->default(0);

            $table->text('comment')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_metrics');
    }
};
