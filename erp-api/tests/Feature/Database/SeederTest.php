<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_seeder_cria_empresa_e_usuarios_padrao(): void
    {
        $this->seed(\Database\Seeders\CoreSeeder::class);

        $this->assertTrue(DB::table('empresas')->where('id', 1)->exists());

        $this->assertTrue(DB::table('users')->where('email', 'admin@teste.com')->where('empresa_id', 1)->exists());
        $this->assertTrue(DB::table('users')->where('email', 'teste@teste.com')->where('empresa_id', 1)->exists());
        $this->assertTrue(DB::table('users')->where('email', 'art@gmail.com')->where('empresa_id', 1)->exists());
    }
}
