<?php
declare(strict_types=1);

namespace App\Middleware;

use Cake\Http\Response;
use Cake\Log\Log;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * BasicAuthMiddleware
 *
 * Enforces HTTP Basic Authentication on all routes except those listed in
 * EXEMPT_PATHS. The exempt list includes /health so that AWS liveness probes
 * always receive a response without needing credentials.
 *
 * Expected environment variables:
 * APP_AUTH_USER — required username (plain text)
 * APP_AUTH_PASSWORD — required password (plain text)
 *
 * If either variable is unset or empty the middleware denies every request
 * (fail-closed design). Credentials are never logged; only the attempted
 * username is included in warning entries to support abuse detection.
 */
class BasicAuthMiddleware implements MiddlewareInterface
{
    /**
     * Realm string sent in the WWW-Authenticate challenge header.
     */
    public const AUTH_REALM = 'SDI Ops Monitor';

    /**
     * URI paths that bypass authentication unconditionally.
     *
     * /health must remain exempt so AWS ECS/EC2 liveness probes can
     * reach the endpoint without needing credentials.
     */
    private const EXEMPT_PATHS = ['/health'];

    /**
     * Process an incoming request and enforce HTTP Basic Authentication.
     *
     * Requests whose URI path matches an exempt path pass through immediately.
     * All other requests must supply a valid Authorization: Basic header whose
     * decoded credentials match APP_AUTH_USER and APP_AUTH_PASSWORD.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The incoming server request.
     * @param \Psr\Http\Server\RequestHandlerInterface $handler The next handler in the middleware stack.
     * @return \Psr\Http\Message\ResponseInterface 401 with WWW-Authenticate on failure, downstream response on success.
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $path = $request->getUri()->getPath();

        // Let exempt paths through before any credential check.
        if ($this->isExemptPath($path)) {
            return $handler->handle($request);
        }

        [$providedUser, $providedPassword] = $this->extractCredentials($request);

        if (!$this->isAuthenticated($providedUser, $providedPassword)) {
            $this->logAuthFailure($request, $providedUser);

            return $this->buildUnauthorizedResponse();
        }

        return $handler->handle($request);
    }

    /**
     * Determine whether the given URI path is exempt from authentication.
     *
     * @param string $path The request URI path (e.g. '/health').
     * @return bool True when the path must bypass authentication.
     */
    private function isExemptPath(string $path): bool
    {
        return in_array($path, self::EXEMPT_PATHS, true);
    }

    /**
     * Parse Basic Auth credentials from the Authorization request header.
     *
     * Returns ['', ''] when the header is absent, does not use the Basic scheme,
     * or contains a base64 payload that cannot be decoded. Passwords may contain
     * colons; only the first colon is treated as a username/password separator.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The incoming request.
     * @return array{0: string, 1: string} A two-element array: [username, password].
     */
    private function extractCredentials(ServerRequestInterface $request): array
    {
        $header = $request->getHeaderLine('Authorization');

        if (!str_starts_with($header, 'Basic ')) {
            return ['', ''];
        }

        $decoded = base64_decode(substr($header, 6), true);

        if ($decoded === false || !str_contains($decoded, ':')) {
            return ['', ''];
        }

        // Split on the first colon only — passwords are allowed to contain colons.
        $colonPos = strpos($decoded, ':');
        $username = substr($decoded, 0, (int)$colonPos);
        $password = substr($decoded, (int)$colonPos + 1);

        return [$username, $password];
    }

    /**
     * Validate provided credentials against the configured environment values.
     *
     * Both username and password comparisons use hash_equals() to prevent
     * timing-based side-channel attacks. The method returns false immediately
     * when either environment variable is absent — fail-closed default.
     *
     * @param string $providedUser Username decoded from the Authorization header.
     * @param string $providedPassword Password decoded from the Authorization header.
     * @return bool True only when both username and password match exactly.
     */
    private function isAuthenticated(string $providedUser, string $providedPassword): bool
    {
        $expectedUser = (string)env('APP_AUTH_USER', '');
        $expectedPassword = (string)env('APP_AUTH_PASSWORD', '');

        // Deny when credentials are not configured — prevents accidental open access.
        if ($expectedUser === '' || $expectedPassword === '') {
            return false;
        }

        // hash_equals() is constant-time for equal-length strings and safe for
        // unequal-length strings (returns false without early exit).
        return hash_equals($expectedUser, $providedUser)
            && hash_equals($expectedPassword, $providedPassword);
    }

    /**
     * Build a 401 Unauthorized response with the Basic Auth challenge header.
     *
     * The WWW-Authenticate header prompts RFC 7235-compliant clients to
     * display a credentials dialog or retry with an Authorization header.
     *
     * @return \Psr\Http\Message\ResponseInterface A 401 response with WWW-Authenticate set.
     */
    private function buildUnauthorizedResponse(): ResponseInterface
    {
        $response = new Response();

        return $response
            ->withStatus(401)
            ->withHeader('WWW-Authenticate', sprintf('Basic realm="%s"', self::AUTH_REALM));
    }

    /**
     * Emit a structured JSON warning log entry for a failed authentication attempt.
     *
     * Only the attempted username is logged (not the password) to support
     * brute-force detection while avoiding credential exposure in log storage.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request that failed authentication.
     * @param string $attemptedUser Username from the Authorization header (may be empty).
     * @return void
     */
    private function logAuthFailure(ServerRequestInterface $request, string $attemptedUser): void
    {
        $correlationId = (string)$request->getAttribute('correlation_id', '');

        Log::warning('HTTP Basic Auth failed', [
            'correlation_id' => $correlationId,
            'attempted_user' => $attemptedUser !== '' ? $attemptedUser : null,
            'path' => $request->getUri()->getPath(),
            'method' => $request->getMethod(),
            'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? null,
        ]);
    }
}
