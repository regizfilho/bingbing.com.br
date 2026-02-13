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
    Schema::table('coupons', function (Blueprint $table) {
        $table->string('description')->nullable()->after('code');
        $table->decimal('min_order_value', 10, 2)->nullable()->after('value');
        $table->integer('per_user_limit')->default(1)->after('usage_limit');
    });

    Schema::table('coupon_users', function (Blueprint $table) {
        $table->decimal('order_value', 10, 2)->nullable()->after('used_at');
        $table->decimal('discount_amount', 10, 2)->nullable()->after('order_value');
        $table->ipAddress('ip_address')->nullable()->after('discount_amount');
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
