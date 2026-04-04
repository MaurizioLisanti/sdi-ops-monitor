<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CorrelationIdMiddleware
 *
 * Reads the X-Correlation-ID header from the incoming request.
 * If present, the value is propagated as-is; if absent, a new UUID v4 is
 * generated. The resolved ID is:
 *   - stored as a request attribute ('correlation_id') for downstream use
 *   - echoed back in the X-Correlation-ID response header
 *
 * Every log entry produced by the application should include this value so
 * that all records belonging to a single HTTP request can be correlated in
 * Kibana/ELK.
 */
class CorrelationIdMiddleware implements MiddlewareInterface
{
    /**
     * HTTP header name used to carry the correlation identifier.
     */
    public const HEADER_NAME = 'X-Correlation-ID';

    /**
     * Request attribute name under which the correlation ID is stored.
     */
    public const ATTRIBUTE_NAME = 'correlation_id';

    /**
     * Process an incoming server request and return a response.
     *
     * Reads X-Correlation-ID from the request headers. If the header is
     * missing or empty, generates a new UUID v4 using random_bytes(). The
     * resolved value is attached to the request as the 'correlation_id'
     * attribute and appended to the outgoing response headers.
     *
     * @param \Psr\Http\Message\ServerRequestInterface  $request The incoming server request.
     * @param \Psr\Http\Server\RequestHandlerInterface $handler The next handler in the middleware queue.
     * @return \Psr\Http\Message\ResponseInterface The response with X-Correlation-ID header set.
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $correlationId = $request->getHeaderLine(self::HEADER_NAME);

        if ($correlationId === '') {
            $correlationId = $this->generateUuidV4();
        }

        // Attach to request so controllers and services can read it
        $request = $request->withAttribute(self::ATTRIBUTE_NAME, $correlationId);

        $response = $handler->handle($request);

        // Echo back (or propagate) the correlation ID in the response
        return $response->withHeader(self::HEADER_NAME, $correlationId);
    }

    /**
     * Generate a UUID v4 string using random_bytes().
     *
     * Format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
     * where y is one of 8, 9, a, or b (variant 1 / RFC 4122).
     *
     * @return string A randomly generated UUID v4.
     */
    private function generateUuidV4(): string
    {
        $data = random_bytes(16);

        // Set version to 4 (0100 in the high nibble of byte 6)
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);

        // Set variant bits to 10xx (RFC 4122 variant 1, byte 8)
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(substr($data, 0, 4)),
            bin2hex(substr($data, 4, 2)),
            bin2hex(substr($data, 6, 2)),
            bin2hex(substr($data, 8, 2)),
            bin2hex(substr($data, 10, 6))
        );
    }
}
