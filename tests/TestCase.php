<?php

namespace Tests;

use App\Jobs\ClasificarExpediente;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Las respuestas Inertia no necesitan los assets compilados para probarse.
        $this->withoutVite();
        // Ningún test habla con servicios externos (IA, Google) sin simularlos.
        Http::preventStrayRequests();
        // La clasificación con IA corre en cola; los tests que la prueban ejecutan el job a mano.
        Queue::fake([ClasificarExpediente::class]);
    }
}
