<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_summary_notes', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->integer('month');
            $table->integer('week_number');
            $table->text('what_worked')->nullable();
            $table->text('what_didnt_work')->nullable();
            $table->text('what_to_improve')->nullable();
            $table->text('what_is_next')->nullable();
            $table->timestamps();

            // Unique constraint so each year/month/week only has one summary note
            $table->unique(['year', 'month', 'week_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_summary_notes');
    }
};
