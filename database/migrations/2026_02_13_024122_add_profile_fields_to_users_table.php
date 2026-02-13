<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname')->unique()->after('name');
            $table->string('avatar_path')->nullable()->after('email');
            $table->string('phone_number')->nullable()->after('password');
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            
            // Location
            $table->string('country')->default('Brasil');
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('language')->default('pt_BR');

            // Access & Status
            $table->enum('role', ['admin', 'user'])->default('user');
            $table->enum('status', ['active', 'banned'])->default('active');
            $table->text('ban_reason')->nullable();
            
            // Verification
            $table->boolean('is_verified')->default(false);
            
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'nickname', 'avatar_path', 'phone_number', 'birth_date', 
                'gender', 'country', 'state', 'city', 'language', 
                'role', 'status', 'ban_reason', 'is_verified'
            ]);
            $table->dropSoftDeletes();
        });
    }
};