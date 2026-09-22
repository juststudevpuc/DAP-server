<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CompanySummaryNote extends Model
{
   public function up(): void
{
    Schema::create('company_summary_notes', function (Blueprint $table) {
        $table->id();
        $table->integer('year');
        $table->integer('month');
        $table->integer('week_number');
        $table->text('what_worked')->nullable();
        $table->text('what_didnt_work')->nullable();
        $table->text('what_to_improve')->nullable();
        $table->text('what_is_next')->nullable();
        $table->timestamps();

        // Ensure unique notes per year, month, and week
        $table->unique(['year', 'month', 'week_number']);
    });
}
}
