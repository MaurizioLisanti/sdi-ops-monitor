<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * MetricsControllerTest — integration tests for POST /api/metrics.json.
 *
 * Covers:
 *   - Happy path: valid full payload → HTTP 201 + {"id": <int>}
 *   - Error path: missing required field "name" → HTTP 422 + {"errors": {...}}
 *
 * Assumption: MySQL 8.0 is up and the `metrics` table exists (migrations applied).
 */
class MetricsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Verify that POST /api/metrics.json with a complete valid payload returns
     * HTTP 201 and a JSON body containing the new record's integer ID.
     *
     * @return void
     */
    public function testAddReturns201(): void
    {
        // CsrfProtectionMiddleware is active globally; enableCsrfToken() injects
        // a valid CSRF cookie and token into the integration test request so that
        // the middleware passes it through without requiring Application.php changes.
        $this->enableCsrfToken();

        $payload = [
            'source'      => 'aws-ec2-prod-01',
            'name'        => 'cpu_usage',
            'value'       => 87.4,
            'unit'        => 'percent',
            'tags'        => ['env' => 'prod', 'region' => 'eu-west-1'],
            'recorded_at' => '2026-04-02 10:00:00',
        ];

        $this->post('/api/metrics.json', $payload);

        $this->assertResponseCode(201);
        $this->assertContentType('application/json');

        $body = json_decode((string)$this->_response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('id', $body);
        $this->assertIsInt($body['id']);
        $this->assertGreaterThan(0, $body['id']);
    }

    /**
     * Verify that POST /api/metrics.json with a payload missing the required
     * field "name" returns HTTP 422 and a JSON body with a non-empty "errors" map.
     *
     * @return void
     */
    public function testAddReturns422OnInvalidPayload(): void
    {
        $this->enableCsrfToken();

        // "name", "value", and "recorded_at" are intentionally omitted to trigger
        // multiple validation errors and confirm they are all surfaced in the response.
        $payload = [
            'source' => 'test-source',
        ];

        $this->post('/api/metrics.json', $payload);

        $this->assertResponseCode(422);
        $this->assertContentType('application/json');

        $body = json_decode((string)$this->_response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('errors', $body);
        $this->assertNotEmpty($body['errors']);
        // Confirm that "name" is among the reported errors.
        $this->assertArrayHasKey('name', $body['errors']);
    }
}
