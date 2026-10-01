<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AccesoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_el_login_responde_con_su_pagina(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component('auth/Login'));
    }

    public function test_sin_sesion_redirige_al_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_horizon_solo_para_superadmin(): void
    {
        $this->get('/horizon')->assertForbidden();

        $director = User::factory()->create()->assignRole('director');
        $this->actingAs($director)->get('/horizon')->assertForbidden();

        $superadmin = User::factory()->create()->assignRole('superadmin');
        $this->actingAs($superadmin)->get('/horizon')->assertOk();
    }

    public function test_rutas_de_desarrollo_no_existen_fuera_de_local(): void
    {
        $superadmin = User::factory()->create()->assignRole('superadmin');

        $this->post('/dev/entrar')->assertNotFound();
        $this->actingAs($superadmin)->get('/ui')->assertNotFound();
    }

    public function test_los_permisos_compartidos_reflejan_el_rol(): void
    {
        $superadmin = User::factory()->create()->assignRole('superadmin');

        $this->actingAs($superadmin)->get('/')->assertInertia(fn (AssertableInertia $p) => $p
            ->component('Inicio')
            ->where('auth.roles', ['superadmin'])
            ->where('auth.can', ['configuracion.gestionar']));
    }
}
