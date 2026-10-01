<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Las respuestas Inertia no necesitan los assets compilados para probarse.
        $this->withoutVite();
    }
}
