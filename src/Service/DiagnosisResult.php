<?php
declare(strict_types=1);

namespace App\Service;

/**
 * DiagnosisResult — immutable value object returned by AiDiagnosticsService::diagnose().
 *
 * Carries the outcome of a single AI diagnostics request: the diagnosis text,
 * the source (AI or deterministic fallback), the model used, timing information,
 * and the input counts so the view can display context alongside the diagnosis.
 */
final class DiagnosisResult
{
    /**
     * @param string $diagnosis Natural-language diagnosis text.
     * @param string $source Origin of the diagnosis: 'ai' or 'fallback'.
     * @param string $model LLM model identifier, or 'deterministic-fallback'.
     * @param string $correlationId Correlation ID propagated from the HTTP request.
     * @param string $generatedAt ISO-8601 UTC timestamp of generation.
     * @param int $metricsCount Number of recent metric events analysed.
     * @param int $alertsCount Number of open alerts included in the analysis.
     */
    public function __construct(
        public readonly string $diagnosis,
        public readonly string $source,
        public readonly string $model,
        public readonly string $correlationId,
        public readonly string $generatedAt,
        public readonly int $metricsCount,
        public readonly int $alertsCount,
    ) {
    }
}
