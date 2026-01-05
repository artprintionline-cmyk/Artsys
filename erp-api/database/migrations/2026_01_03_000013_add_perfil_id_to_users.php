<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'perfil_id')) {
                $table->unsignedBigInteger('perfil_id')->nullable()->after('empresa_id');
                $table->index(['empresa_id', 'perfil_id']);
                $table->foreign('perfil_id')->references('id')->on('perfis')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'perfil_id')) {
                $table->dropForeign(['perfil_id']);
                $table->dropColumn('perfil_id');
            }
        });
    }
};
