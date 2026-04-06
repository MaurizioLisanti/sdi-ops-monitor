<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\SnsSignatureValidator;
use Cake\TestSuite\TestCase;

/**
 * SnsSignatureValidatorTest
 *
 * Unit tests for SnsSignatureValidator. All tests are fully isolated — no
 * outbound network requests are made. Certificates and signatures are generated
 * with PHP's built-in openssl_* functions at test startup.
 *
 * Coverage:
 *   1. testValidSignatureReturnsTrue         — valid RSA-signed Notification → true
 *   2. testInvalidSignatureReturnsFalse      — tampered signature → false
 *   3. testRejectsCertFromNonAmazonDomain    — attacker cert URL → false, no HTTP fetch
 */
class SnsSignatureValidatorTest extends TestCase
{
    /**
     * SNS Notification payload with a real RSA signature over the test key.
     * Populated during setUp().
     *
     * @var array<string, mixed>
     */
    private array $signedPayload;

    /**
     * PEM-encoded self-signed certificate derived from the test private key.
     * Returned by the stub certFetcher so no network call is needed.
     *
     * @var string
     */
    private string $certPem;

    /**
     * Generate a throwaway RSA key pair, build a self-signed certificate,
     * construct a canonical SNS Notification payload, and sign it.
     *
     * The resulting $signedPayload is ready for use in testValidSignatureReturnsTrue.
     * Tampered variants are derived inline within each test method.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Generate a 2048-bit RSA key — throwaway, used only within this test run.
        $privKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        $this->assertNotFalse($privKey, 'openssl_pkey_new() must succeed; check PHP openssl extension.');

        // Self-signed cert: SNS verification uses the public key extracted from the cert.
        $dn  = ['commonName' => 'test.amazonaws.com'];
        $csr = openssl_csr_new($dn, $privKey);
        $x509 = openssl_csr_sign($csr, null, $privKey, 365);
        $certPem = '';
        openssl_x509_export($x509, $certPem);
        $this->certPem = $certPem;

        // Build the base SNS Notification payload (without Signature yet).
        $this->signedPayload = [
            'Type'             => 'Notification',
            'MessageId'        => 'test-msg-id-a1b2c3d4',
            'TopicArn'         => 'arn:aws:sns:us-east-1:123456789012:test-topic',
            'Message'          => '{"source":"test","name":"cpu_usage","value":50}',
            'Timestamp'        => '2026-04-05T10:00:00.000Z',
            'SignatureVersion' => '1',
            // Use a real amazonaws.com URL so the domain allowlist check passes.
            'SigningCertURL'   => 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-test.pem',
        ];

        // Build the canonical string-to-sign per SNS Signature Version 1.
        $stringToSign = $this->buildNotificationStringToSign($this->signedPayload);

        // Sign with SHA1withRSA — matches OPENSSL_ALGO_SHA1 used by the validator.
        $rawSig = '';
        openssl_sign($stringToSign, $rawSig, $privKey, OPENSSL_ALGO_SHA1);
        $this->signedPayload['Signature'] = base64_encode($rawSig);
    }

    /**
     * A Notification payload signed with the test private key must pass validation
     * when the validator is given a cert fetcher that returns the matching certificate.
     *
     * @return void
     */
    public function testValidSignatureReturnsTrue(): void
    {
        $certPem   = $this->certPem;
        $validator = new SnsSignatureValidator(
            // Stub: return the test certificate PEM without any network call.
            static function (string $url) use ($certPem): string {
                return $certPem;
            }
        );

        $result = $validator->validate([], json_encode($this->signedPayload, JSON_THROW_ON_ERROR));

        $this->assertTrue($result, 'A correctly signed SNS Notification must return true.');
    }

    /**
     * A Notification payload whose Signature has been tampered with must fail
     * validation even when the certificate is valid.
     *
     * @return void
     */
    public function testInvalidSignatureReturnsFalse(): void
    {
        $certPem = $this->certPem;
        $validator = new SnsSignatureValidator(
            static function (string $url) use ($certPem): string {
                return $certPem;
            }
        );

        // Replace the valid signature with random bytes encoded as base64.
        $tampered              = $this->signedPayload;
        $tampered['Signature'] = base64_encode(random_bytes(256));

        $result = $validator->validate([], json_encode($tampered, JSON_THROW_ON_ERROR));

        $this->assertFalse($result, 'A tampered signature must return false.');
    }

    /**
     * A payload whose SigningCertURL points to a non-Amazon domain must be
     * rejected immediately — the HTTP GET callable must never be invoked.
     *
     * This test verifies both the security property (no SSRF fetch to attacker
     * infrastructure) and the fail-closed contract (returns false, not an exception).
     *
     * @return void
     */
    public function testRejectsCertFromNonAmazonDomain(): void
    {
        $fetchCalled = false;
        $validator   = new SnsSignatureValidator(
            // This stub must NEVER be called for a non-Amazon cert URL.
            static function (string $url) use (&$fetchCalled): string {
                $fetchCalled = true;

                return '';
            }
        );

        $payload                   = $this->signedPayload;
        $payload['SigningCertURL'] = 'https://evil.attacker.example.com/fake-cert.pem';

        $result = $validator->validate([], json_encode($payload, JSON_THROW_ON_ERROR));

        $this->assertFalse($result, 'A non-Amazon cert domain must return false.');
        $this->assertFalse($fetchCalled, 'HTTP fetch must not be attempted for a non-Amazon cert URL.');
    }

    /**
     * Build the canonical string-to-sign for a Notification payload.
     *
     * Mirrors the logic in SnsSignatureValidator::buildStringToSign() so the
     * test can sign the payload with the same byte sequence the validator expects.
     * This helper exists only in the test — it is not a copy of production code,
     * but a reference implementation used to produce test fixtures.
     *
     * @param array<string, mixed> $payload SNS Notification payload (without Signature).
     * @return string The canonical string that was/will be signed.
     */
    private function buildNotificationStringToSign(array $payload): string
    {
        // Fields and order per SNS Signature Version 1 for Notification type.
        $fields = ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'];

        $parts = [];
        foreach ($fields as $field) {
            if (isset($payload[$field])) {
                $parts[] = $field . "\n" . $payload[$field] . "\n";
            }
        }

        return implode('', $parts);
    }
}
