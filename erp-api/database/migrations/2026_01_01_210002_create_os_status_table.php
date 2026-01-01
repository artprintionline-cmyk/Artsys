<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('os_status', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->integer('ordem');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // Inicializar status padrão em ordem lógica
        $statuses = [
            ['nome' => 'criada', 'ordem' => 1, 'ativo' => true],
            ['nome' => 'arte', 'ordem' => 2, 'ativo' => true],
            ['nome' => 'producao', 'ordem' => 3, 'ativo' => true],
            ['nome' => 'acabamento', 'ordem' => 4, 'ativo' => true],
            ['nome' => 'expedicao', 'ordem' => 5, 'ativo' => true],
            ['nome' => 'finalizada', 'ordem' => 6, 'ativo' => true],
            ['nome' => 'cancelada', 'ordem' => 7, 'ativo' => true],
        ];

        DB::table('os_status')->insert(array_map(function ($s) {
            return array_merge($s, ['created_at' => now(), 'updated_at' => now()]);
        }, $statuses));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('os_status');
    }
};
