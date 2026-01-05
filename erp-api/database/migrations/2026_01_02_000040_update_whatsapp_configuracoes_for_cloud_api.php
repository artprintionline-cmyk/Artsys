<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_configuracoes', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_configuracoes', 'phone_number_id')) {
                $table->string('phone_number_id')->nullable()->after('token');
            }
            if (! Schema::hasColumn('whatsapp_configuracoes', 'verify_token')) {
                $table->string('verify_token')->nullable()->after('phone_number_id');
            }
            if (! Schema::hasColumn('whatsapp_configuracoes', 'app_secret')) {
                $table->text('app_secret')->nullable()->after('verify_token');
            }
            if (! Schema::hasColumn('whatsapp_configuracoes', 'api_version')) {
                $table->string('api_version')->default('v19.0')->after('app_secret');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_configuracoes', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_configuracoes', 'api_version')) {
                $table->dropColumn('api_version');
            }
            if (Schema::hasColumn('whatsapp_configuracoes', 'app_secret')) {
                $table->dropColumn('app_secret');
            }
            if (Schema::hasColumn('whatsapp_configuracoes', 'verify_token')) {
                $table->dropColumn('verify_token');
            }
            if (Schema::hasColumn('whatsapp_configuracoes', 'phone_number_id')) {
                $table->dropColumn('phone_number_id');
            }
        });
    }
};
