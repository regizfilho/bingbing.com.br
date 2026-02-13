<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Adicionando o que faltou para o perfil completo
            $table->string('document')->nullable()->after('phone_number'); // CPF
            $table->string('instagram')->nullable()->after('language');
            $table->text('bio')->nullable()->after('instagram');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['document', 'instagram', 'bio']);
        });
    }
};