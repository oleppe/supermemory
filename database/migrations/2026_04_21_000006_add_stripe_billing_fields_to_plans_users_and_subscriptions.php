<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->unique()->after('is_admin');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->string('stripe_price_id')->nullable()->unique()->after('currency');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('provider')->default('manual')->after('plan_id');
            $table->boolean('cancel_at_period_end')->default(false)->after('ends_at');
            $table->string('stripe_subscription_id')->nullable()->unique()->after('cancel_at_period_end');
            $table->string('stripe_checkout_session_id')->nullable()->unique()->after('stripe_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique(['stripe_checkout_session_id']);
            $table->dropUnique(['stripe_subscription_id']);
            $table->dropColumn([
                'provider',
                'cancel_at_period_end',
                'stripe_subscription_id',
                'stripe_checkout_session_id',
            ]);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropUnique(['stripe_price_id']);
            $table->dropColumn('stripe_price_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['stripe_customer_id']);
            $table->dropColumn('stripe_customer_id');
        });
    }
};