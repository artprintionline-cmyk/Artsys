<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Empresa;
use App\Models\User;

class CoreSeeder extends Seeder
{
    public function run()
    {
        $empresa = Empresa::create([
            'nome' => 'Empresa Teste',
            'cnpj' => '',
            'status' => true,
        ]);

        User::create([
            'name' => 'Tester',
            'email' => 'tester@example.com',
            'password' => bcrypt('password'),
            'empresa_id' => $empresa->id,
            'status' => true,
        ]);
    }
}
