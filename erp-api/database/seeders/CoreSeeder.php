<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class CoreSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure empresa id = 1 exists
        if (! DB::table('empresas')->where('id', 1)->exists()) {
            DB::table('empresas')->insert([
                'id' => 1,
                'nome' => 'Empresa Local',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create or update admin user
        User::updateOrCreate(
            ['email' => 'admin@teste.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'status' => 'ativo',
                'empresa_id' => 1,
            ]
        );

        // Create or update quick-test user with email '123' and password '123'
        User::updateOrCreate(
            ['email' => '123'],
            [
                'name' => 'Quick Test',
                'password' => Hash::make('123'),
                'status' => 'ativo',
                'empresa_id' => 1,
            ]
        );

        // Create or update user 'art@gmail.com' with password '123' for local testing
        User::updateOrCreate(
            ['email' => 'art@gmail.com'],
            [
                'name' => 'Art Test',
                'password' => Hash::make('123'),
                'status' => 'ativo',
                'empresa_id' => 1,
            ]
        );
    }
}
