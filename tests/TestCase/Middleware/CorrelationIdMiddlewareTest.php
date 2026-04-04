<?php
declare(strict_types=1);

namespace App\Test\TestCase\Middleware;

use App\Middleware\CorrelationIdMiddleware;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CorrelationIdMiddlewareTest
 *
 * Verifies the two core behaviours of CorrelationIdMiddleware:
 *
 *  1. When the client provides X-Correlation-ID, the same value is echoed
 *     back in the response header and stored as a request attribute.
 *
 *  2. When the client omits X-Correlation-ID, a fresh UUID v4 is generated,
 *     set in the response header, and stored as a request attribute.
 */
class CorrelationIdMiddlewareTest extends TestCase
{
    /**
     * System under test.
     *
     * @var \App\Middleware\CorrelationIdMiddleware
     */
    private CorrelationIdMiddleware $middleware;

    /**
     * Set up a fresh middleware instance before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new CorrelationIdMiddleware();
    }

    /**
     * Build a minimal PSR-15 handler stub that captures the processed request
     * and returns a plain 200 response.
     *
     * @param \Psr\Http\Message\ServerRequestInterface|null $capturedRequest Reference populated with the request seen by the handler.
     * @return \Psr\Http\Server\RequestHandlerInterface
     */
    private function makeHandler(?ServerRequestInterface &$capturedRequest = null): RequestHandlerInterface
    {
        return new class($capturedRequest) implements RequestHandlerInterface {
            /** @var \Psr\Http\Message\ServerRequestInterface|null */
            private ?ServerRequestInterface $ref;

            /**
             * @param \Psr\Http\Message\ServerRequestInterface|null $ref Reference to capture.
             */
            public function __construct(?ServerRequestInterface &$ref)
            {
                $this->ref = &$ref;
            }

            /**
             * @param \Psr\Http\Message\ServerRequestInterface $request Incoming request.
             * @return \Psr\Http\Message\ResponseInterface
             */
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->ref = $request;
                return new Response();
            }
        };
    }

    /**
     * When the client sends X-Correlation-ID, the middleware must:
     *  - preserve the exact value in the response header
     *  - store it under the 'correlation_id' request attribute
     *
     * @return void
     */
    public function testCorrelationIdIsEchoedWhenProvidedByClient(): void
    {
        $clientId = 'test-uuid-1234';

        $request = new ServerRequest([
            'environment' => [
                'HTTP_X_CORRELATION_ID' => $clientId,
                'REQUEST_METHOD'        => 'GET',
                'REQUEST_URI'           => '/health',
            ],
        ]);

        $capturedRequest = null;
        $handler  = $this->makeHandler($capturedRequest);
        $response = $this->middleware->process($request, $handler);

        // Response header must echo the client-supplied value
        $this->assertSame(
            $clientId,
            $response->getHeaderLine(CorrelationIdMiddleware::HEADER_NAME),
            'Response X-Correlation-ID must match the value sent by the client.'
        );

        // Request attribute must be set for downstream consumers
        $this->assertNotNull($capturedRequest);
        $this->assertSame(
            $clientId,
            $capturedRequest->getAttribute(CorrelationIdMiddleware::ATTRIBUTE_NAME),
            "Request attribute 'correlation_id' must match the client-supplied value."
        );
    }

    /**
     * When the client omits X-Correlation-ID, the middleware must:
     *  - generate a non-empty UUID v4 in the response header
     *  - store the same generated value as the 'correlation_id' request attribute
     *
     * @return void
     */
    public function testCorrelationIdIsGeneratedWhenAbsentFromRequest(): void
    {
        $request = new ServerRequest([
            'environment' => [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI'    => '/health',
            ],
        ]);

        $capturedRequest = null;
        $handler  = $this->makeHandler($capturedRequest);
        $response = $this->middleware->process($request, $handler);

        $generatedId = $response->getHeaderLine(CorrelationIdMiddleware::HEADER_NAME);

        // A non-empty ID must have been generated
        $this->assertNotEmpty(
            $generatedId,
            'Middleware must generate a non-empty correlation ID when none is provided.'
        );

        // The generated value must look like a UUID v4
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $generatedId,
            'Generated correlation ID must be a valid UUID v4.'
        );

        // Request attribute must carry the same generated value
        $this->assertNotNull($capturedRequest);
        $this->assertSame(
            $generatedId,
            $capturedRequest->getAttribute(CorrelationIdMiddleware::ATTRIBUTE_NAME),
            "Request attribute 'correlation_id' must match the generated response header value."
        );
    }
}
