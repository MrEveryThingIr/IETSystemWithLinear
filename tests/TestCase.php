<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        // Livewire::test() can leave process-wide hooks set without an HTTP
        // response to clear them. Do not let one test alter the next response.
        if ($this->app !== null && $this->app->bound('livewire')) {
            $this->app->make('livewire')->flushState();
        }
        parent::tearDown();
    }
}
