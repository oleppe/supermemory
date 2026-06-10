<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('metric');
            $table->string('period');
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->unsignedInteger('used')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'metric', 'period', 'period_start'], 'usage_counters_scope_unique');
            $table->index(['user_id', 'metric', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_counters');
    }
};
