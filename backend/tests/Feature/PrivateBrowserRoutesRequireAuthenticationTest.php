<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrivateBrowserRoutesRequireAuthenticationTest extends TestCase
{
    public function test_private_browser_routes_reject_unauthenticated_requests(): void
    {
        $routes = [
            ['GET', '/api/niat/me'],

            ['POST', '/api/cad/calls'],
            ['POST', '/api/cad/intakes'],
            ['POST', '/api/cad/intakes/1/validate'],
            ['POST', '/api/cad/intakes/1/duplicate-check'],
            ['POST', '/api/cad/intakes/1/submit-to-brave'],
            ['GET', '/api/cad/queue'],

            ['PUT', '/api/fire-incidents/1'],
            ['DELETE', '/api/fire-incidents/1'],

            ['GET', '/api/operator/public-reports'],
            ['POST', '/api/operator/public-reports/1/status'],
        ];

        foreach ($routes as [$method, $uri]) {
            $this->json($method, $uri)
                ->assertUnauthorized();
        }
    }
    public function test_arcgis_token_is_not_exposed_by_a_public_endpoint(): void
{
    $this->getJson('/api/arcgis/token')
        ->assertNotFound();
}
}
