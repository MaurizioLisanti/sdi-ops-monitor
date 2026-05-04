<?php
declare(strict_types=1);

namespace App\Test\TestCase\Middleware;

use App\Middleware\BasicAuthMiddleware;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * BasicAuthMiddlewareTest
 *
 * Covers the three critical behaviours of BasicAuthMiddleware:
 *
 *  1. Requests to protected paths without credentials receive HTTP 401
 *     with a WWW-Authenticate: Basic challenge header.
 *
 *  2. Requests to protected paths with valid credentials are forwarded
 *     to the next handler and receive HTTP 200.
 *
 *  3. Requests to /health bypass authentication entirely and receive
 *     HTTP 200 regardless of credentials — required for AWS liveness probes.
 */
class BasicAuthMiddlewareTest extends TestCase
{
    /**
     * Test credentials injected via putenv() — never real production values.
     */
    private const TEST_USER     = 'testuser';
    private const TEST_PASSWORD = 'testpassword';

    /**
     * System under test.
     *
     * @var \App\Middleware\BasicAuthMiddleware
     */
    private BasicAuthMiddleware $middleware;

    /**
     * Configure the test environment with known credentials and instantiate
     * the middleware before every test method.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Inject test credentials via environment — never hardcode real values.
        putenv('APP_AUTH_USER=' . self::TEST_USER);
        putenv('APP_AUTH_PASSWORD=' . self::TEST_PASSWORD);
        $_ENV['APP_AUTH_USER']     = self::TEST_USER;
        $_ENV['APP_AUTH_PASSWORD'] = self::TEST_PASSWORD;
        $_SERVER['APP_AUTH_USER']     = self::TEST_USER;
        $_SERVER['APP_AUTH_PASSWORD'] = self::TEST_PASSWORD;

        $this->middleware = new BasicAuthMiddleware();
    }

    /**
     * Clear injected test credentials after each test to prevent state leaking
     * between test methods.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        putenv('APP_AUTH_USER');
        putenv('APP_AUTH_PASSWORD');
        unset($_ENV['APP_AUTH_USER'], $_ENV['APP_AUTH_PASSWORD']);
        unset($_SERVER['APP_AUTH_USER'], $_SERVER['APP_AUTH_PASSWORD']);

        parent::tearDown();
    }

    /**
     * Build a minimal PSR-15 handler stub that returns a plain 200 response.
     *
     * The returned handler does NOT call into the application stack, so tests
     * remain isolated from the database and routing layers.
     *
     * @param \Psr\Http\Message\ServerRequestInterface|null $capturedRequest Populated with the request the handler received.
     * @return \Psr\Http\Server\RequestHandlerInterface
     */
    private function makePassthroughHandler(?ServerRequestInterface &$capturedRequest = null): RequestHandlerInterface
    {
        return new class($capturedRequest) implements RequestHandlerInterface {
            /** @var \Psr\Http\Message\ServerRequestInterface|null */
            private mixed $ref;

            /**
             * @param \Psr\Http\Message\ServerRequestInterface|null $ref Reference for capturing the handled request.
             */
            public function __construct(mixed &$ref)
            {
                $this->ref = &$ref;
            }

            /**
             * @param \Psr\Http\Message\ServerRequestInterface $request The forwarded request.
             * @return \Psr\Http\Message\ResponseInterface A plain 200 response.
             */
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->ref = $request;
                return new Response();
            }
        };
    }

    /**
     * Build a ServerRequest for the given path and optional Authorization header.
     *
     * @param string      $path          Request URI path (e.g. '/' or '/health').
     * @param string|null $authHeader    Value for the Authorization header, or null to omit it.
     * @param string      $method        HTTP method, defaults to 'GET'.
     * @return \Cake\Http\ServerRequest
     */
    private function buildRequest(
        string $path,
        ?string $authHeader = null,
        string $method = 'GET'
    ): ServerRequest {
        $environment = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI'    => $path,
        ];

        if ($authHeader !== null) {
            // CakePHP's ServerRequest maps HTTP_AUTHORIZATION from $_SERVER.
            $environment['HTTP_AUTHORIZATION'] = $authHeader;
        }

        return new ServerRequest(['environment' => $environment]);
    }

    /**
     * Encode username and password into an RFC 7617 Basic Auth header value.
     *
     * @param string $user     Username.
     * @param string $password Password.
     * @return string Ready-to-use Authorization header value (e.g. "Basic dXNlcjpwYXNz").
     */
    private function basicAuthHeader(string $user, string $password): string
    {
        return 'Basic ' . base64_encode($user . ':' . $password);
    }

    /**
     * A GET / request without an Authorization header must receive HTTP 401
     * and a WWW-Authenticate: Basic challenge header.
     *
     * @return void
     */
    public function testDashboardReturns401WithoutCredentials(): void
    {
        $request  = $this->buildRequest('/');
        $handler  = $this->makePassthroughHandler();
        $response = $this->middleware->process($request, $handler);

        $this->assertSame(401, $response->getStatusCode(), 'Missing credentials must produce HTTP 401.');
        $this->assertStringContainsString(
            'Basic realm="' . BasicAuthMiddleware::AUTH_REALM . '"',
            $response->getHeaderLine('WWW-Authenticate'),
            'Response must include a WWW-Authenticate: Basic challenge header.'
        );
    }

    /**
     * A GET / request with valid Basic Auth credentials must be forwarded to
     * the next handler and receive HTTP 200.
     *
     * @return void
     */
    public function testDashboardReturns200WithValidCredentials(): void
    {
        $authHeader      = $this->basicAuthHeader(self::TEST_USER, self::TEST_PASSWORD);
        $capturedRequest = null;
        $request         = $this->buildRequest('/', $authHeader);
        $handler         = $this->makePassthroughHandler($capturedRequest);
        $response        = $this->middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode(), 'Valid credentials must produce HTTP 200.');
        // Confirm the handler was actually invoked (not short-circuited).
        $this->assertNotNull($capturedRequest, 'Handler must be called when credentials are valid.');
    }

    /**
     * A GET /health request without credentials must bypass authentication and
     * reach the next handler — ensuring AWS ECS/EC2 liveness probes always succeed.
     *
     * @return void
     */
    public function testHealthIsExemptFromAuth(): void
    {
        $capturedRequest = null;
        // Intentionally no Authorization header.
        $request  = $this->buildRequest('/health');
        $handler  = $this->makePassthroughHandler($capturedRequest);
        $response = $this->middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode(), '/health must return HTTP 200 without credentials.');
        $this->assertNotNull($capturedRequest, 'Handler must be called for exempt /health path.');
    }

    /**
     * A request with wrong credentials must still receive HTTP 401, not 403.
     *
     * HTTP 401 ("Unauthorized") is the correct status when credentials are
     * invalid. HTTP 403 ("Forbidden") would imply authentication succeeded
     * but authorisation was denied — which is not the case here.
     *
     * @return void
     */
    public function testWrongCredentialsReturn401(): void
    {
        $authHeader = $this->basicAuthHeader('wrong', 'credentials');
        $request    = $this->buildRequest('/', $authHeader);
        $handler    = $this->makePassthroughHandler();
        $response   = $this->middleware->process($request, $handler);

        $this->assertSame(401, $response->getStatusCode(), 'Wrong credentials must produce HTTP 401, not 403.');
    }
}
