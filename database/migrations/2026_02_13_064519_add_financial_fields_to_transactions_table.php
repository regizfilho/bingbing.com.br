<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {

            $table->decimal('original_amount', 15, 2)->nullable()->after('amount');
            $table->decimal('discount_amount', 15, 2)->nullable()->after('original_amount');
            $table->decimal('final_amount', 15, 2)->nullable()->after('discount_amount');

        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'original_amount',
                'discount_amount',
                'final_amount'
            ]);
        });
    }
};
