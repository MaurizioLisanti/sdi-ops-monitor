<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Log\Log;
use Closure;

/**
 * SnsSignatureValidator
 *
 * Validates the SHA1-RSA signature (Signature Version 1) on incoming AWS SNS
 * HTTP notification payloads, preventing injection of forged metric events.
 *
 * The validator also handles the SubscriptionConfirmation flow by GET-ing the
 * SubscribeURL — both the certificate URL and the SubscribeURL are checked
 * against an Amazon-domain allowlist before any outbound HTTP request is made.
 *
 * Fail-closed design:
 * - Any validation error (bad domain, unreadable cert, wrong signature, ...) → false
 * - The controller must treat false as an immediate 400 with no data written.
 *
 * Dependency injection:
 * The optional $httpGet callable allows tests to inject a stub that returns
 * a pre-generated certificate PEM without making real network requests. The
 * production default uses file_get_contents() with TLS peer verification.
 *
 * AWS references:
 * https://docs.aws.amazon.com/sns/latest/dg/sns-verify-signature-of-message.html
 */
class SnsSignatureValidator
{
    /**
     * Hostnames (and their subdomains) from which certificate and confirmation
     * URLs are allowed. Any other domain is rejected without an outbound request.
     *
     * @var list<string>
     */
    private const ALLOWED_DOMAINS = ['amazonaws.com', 'amazon.com'];

    /**
     * Payload fields included in the signature string for Notification messages,
     * in the exact order required by the SNS Signature Version 1 specification.
     * Fields absent from the payload are silently skipped.
     *
     * @var list<string>
     */
    private const NOTIFICATION_SIGN_FIELDS = [
        'Message',
        'MessageId',
        'Subject',
        'Timestamp',
        'TopicArn',
        'Type',
    ];

    /**
     * Payload fields included in the signature string for SubscriptionConfirmation
     * and UnsubscribeConfirmation messages (SNS Signature Version 1).
     *
     * @var list<string>
     */
    private const SUBSCRIPTION_SIGN_FIELDS = [
        'Message',
        'MessageId',
        'SubscribeURL',
        'Timestamp',
        'Token',
        'TopicArn',
        'Type',
    ];

    /**
     * HTTP GET callable used for all outbound requests (certificate fetch and
     * subscription confirmation). Injected to enable unit testing without
     * real network calls.
     *
     * @var \Closure(string): (string|false)
     */
    private Closure $httpGet;

    /**
     * @param callable(string):(string|false)|null $httpGet Optional HTTP GET callable.
     * Receives a URL string; must return the response body as a string,
     * or false on failure. Defaults to file_get_contents() with TLS verification.
     */
    public function __construct(?callable $httpGet = null)
    {
        // Default: use file_get_contents with TLS peer verification enabled.
        $this->httpGet = $httpGet !== null
            ? Closure::fromCallable($httpGet)
            : static function (string $url): string|false {
                $ctx = stream_context_create([
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                    ],
                ]);

                return file_get_contents($url, false, $ctx);
            };
    }

    /**
     * Validate the SHA1-RSA signature of an SNS Notification message.
     *
     * Steps:
     * 1. Decode the raw JSON body into a payload array.
     * 2. Validate the SigningCertURL domain against the Amazon allowlist.
     * 3. Fetch the signing certificate via the injected HTTP GET callable.
     * 4. Build the canonical string-to-sign from the payload fields.
     * 5. Decode the base64 Signature field.
     * 6. Verify using openssl_verify() with OPENSSL_ALGO_SHA1.
     *
     * Returns false on ANY failure — the caller must not process the payload.
     *
     * @param array<string, mixed> $headers HTTP headers from the incoming request (reserved for future use).
     * @param string $rawBody Raw JSON request body as received from AWS SNS.
     * @return bool True if the signature is valid, false on any validation failure.
     */
    public function validate(array $headers, string $rawBody): bool
    {
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return false;
        }

        $certUrl = (string)($payload['SigningCertURL'] ?? '');

        // Reject immediately if the cert URL is not from an Amazon-owned domain.
        // This prevents fetching attacker-controlled certificates.
        if (!$this->isDomainAllowed($certUrl)) {
            Log::warning('SNS validation rejected: SigningCertURL domain not in allowlist', [
                'cert_url' => $certUrl,
            ]);

            return false;
        }

        $certContent = ($this->httpGet)($certUrl);
        if (!is_string($certContent) || $certContent === '') {
            Log::error('SNS validation failed: could not fetch signing certificate', [
                'cert_url' => $certUrl,
            ]);

            return false;
        }

        $messageType = (string)($payload['Type'] ?? '');
        $stringToSign = $this->buildStringToSign($messageType, $payload);
        if ($stringToSign === null) {
            return false;
        }

        $rawSignature = base64_decode((string)($payload['Signature'] ?? ''), true);
        if ($rawSignature === false) {
            return false;
        }

        $pubKey = openssl_get_publickey($certContent);
        if ($pubKey === false) {
            return false;
        }

        // openssl_verify returns 1 on match, 0 on mismatch, -1 on error.
        return openssl_verify($stringToSign, $rawSignature, $pubKey, OPENSSL_ALGO_SHA1) === 1;
    }

    /**
     * Confirm an SNS subscription by GET-ing the SubscribeURL.
     *
     * The SubscribeURL domain is validated against the Amazon allowlist to
     * prevent Server-Side Request Forgery (SSRF) attacks. If the URL is not on
     * an allowed domain the confirmation is silently skipped and a warning is
     * logged — this is safe because real AWS SubscribeURLs are always on
     * sns.<region>.amazonaws.com.
     *
     * This method is idempotent: sending the same SubscribeURL multiple times
     * has no effect beyond the first successful confirmation (SNS handles dedup).
     *
     * @param array<string, mixed> $payload Decoded SNS SubscriptionConfirmation payload.
     * @return void
     */
    public function handleSubscriptionConfirmation(array $payload): void
    {
        $subscribeUrl = (string)($payload['SubscribeURL'] ?? '');

        if ($subscribeUrl === '') {
            return;
        }

        // Validate the SubscribeURL domain before making any outbound request.
        // Real AWS SubscribeURLs are always on amazonaws.com; anything else is suspect.
        if (!$this->isDomainAllowed($subscribeUrl)) {
            Log::warning('SNS subscription confirmation skipped: SubscribeURL domain not allowed', [
                'subscribe_url' => $subscribeUrl,
            ]);

            return;
        }

        ($this->httpGet)($subscribeUrl);
    }

    /**
     * Check whether the hostname of a URL belongs to an allowed Amazon domain.
     *
     * Accepts exact matches and subdomain matches (e.g. sns.us-east-1.amazonaws.com
     * is accepted because it ends with .amazonaws.com).
     *
     * @param string $url The URL whose hostname will be validated.
     * @return bool True if the host is within an allowed domain, false otherwise.
     */
    private function isDomainAllowed(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }

        foreach (self::ALLOWED_DOMAINS as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build the canonical string-to-sign for a given SNS message type.
     *
     * Each field is serialised as: "<FieldName>\n<FieldValue>\n"
     * Fields absent from the payload are skipped (per AWS spec, Subject is optional).
     * Returns null for unknown message types (caller should reject the message).
     *
     * @param string $messageType SNS message Type field value.
     * @param array<string, mixed> $payload Decoded SNS payload.
     * @return string|null The string-to-sign, or null for unknown message types.
     */
    private function buildStringToSign(string $messageType, array $payload): ?string
    {
        $fields = match ($messageType) {
            'Notification' => self::NOTIFICATION_SIGN_FIELDS,
            'SubscriptionConfirmation',
            'UnsubscribeConfirmation' => self::SUBSCRIPTION_SIGN_FIELDS,
            default => null,
        };

        if ($fields === null) {
            return null;
        }

        $parts = [];
        foreach ($fields as $field) {
            if (isset($payload[$field])) {
                $parts[] = $field . "\n" . $payload[$field] . "\n";
            }
        }

        return implode('', $parts);
    }
}
