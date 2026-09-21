<?php

namespace Tests\Feature;

use Tests\TestCase;

class OperationsBaselineTest extends TestCase
{
    public function test_liveness_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_web_responses_include_request_correlation_id(): void
    {
        $this->withoutVite();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('X-Request-Id');
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            (string) $response->headers->get('X-Request-Id'),
        );
    }
}
