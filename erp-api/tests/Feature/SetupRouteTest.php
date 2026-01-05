<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_rota_existe_quando_nao_instalado(): void
    {
        $this->get('/setup')->assertStatus(200);
    }

    public function test_setup_rota_bloqueia_quando_instalado(): void
    {
        SystemSetting::create(['installed' => true, 'installed_at' => now()]);
        $this->get('/setup')->assertStatus(404);
    }
}
