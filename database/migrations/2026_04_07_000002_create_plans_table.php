<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('billing_interval_months')->default(1);
            $table->unsignedInteger('monthly_file_limit')->nullable();
            $table->unsignedInteger('monthly_question_limit')->nullable();
            $table->unsignedInteger('daily_question_limit')->nullable();
            $table->unsignedInteger('daily_file_limit')->nullable();
            $table->unsignedInteger('max_pdf_pages')->default(10);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
