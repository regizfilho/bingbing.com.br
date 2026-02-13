<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Origem do cadastro
            $table->string('signup_source')->nullable()->after('is_verified')->index();
            $table->string('utm_source')->nullable()->after('signup_source');
            $table->string('utm_medium')->nullable()->after('utm_source');
            $table->string('utm_campaign')->nullable()->after('utm_medium');
            $table->string('utm_content')->nullable()->after('utm_campaign');
            $table->string('utm_term')->nullable()->after('utm_content');
            $table->string('referrer_url')->nullable()->after('utm_term');
            $table->ipAddress('signup_ip')->nullable()->after('referrer_url');
            $table->text('user_agent')->nullable()->after('signup_ip');
        });

        // Tabela para rastrear sessões e atividades
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->ipAddress('ip_address')->index();
            $table->text('user_agent')->nullable();
            $table->string('device_type')->nullable(); // mobile, desktop, tablet
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'started_at']);
            $table->index('last_activity_at');
        });

        // Tabela para rastrear origens de tráfego detalhadas
        Schema::create('traffic_sources', function (Blueprint $table) {
            $table->id();
            $table->string('source_type')->index(); // organic, direct, referral, social, campaign
            $table->string('source_name')->index(); // google, facebook, instagram, etc
            $table->string('referrer_domain')->nullable()->index();
            $table->string('landing_page')->nullable();
            $table->json('utm_params')->nullable(); // armazena todos os parâmetros UTM
            $table->integer('visits_count')->default(0);
            $table->integer('signups_count')->default(0);
            $table->integer('conversions_count')->default(0);
            $table->decimal('revenue', 10, 2)->default(0);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            
            $table->unique(['source_type', 'source_name', 'referrer_domain']);
        });

        // Tabela para vincular usuários a origens de tráfego
        Schema::create('user_traffic_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('traffic_source_id')->constrained()->onDelete('cascade');
            $table->string('landing_page')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('visited_at');
            $table->boolean('converted')->default(false); // se comprou algo
            $table->timestamps();
            
            $table->index(['user_id', 'traffic_source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'signup_source',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_content',
                'utm_term',
                'referrer_url',
                'signup_ip',
                'user_agent',
            ]);
        });

        Schema::dropIfExists('user_traffic_sources');
        Schema::dropIfExists('traffic_sources');
        Schema::dropIfExists('user_sessions');
    }
};