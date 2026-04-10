<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\AiDiagnosticsService;

/**
 * AiDiagnosticsController — LLM-powered system health diagnostics endpoint.
 *
 * Delegates to AiDiagnosticsService to collect recent metrics and open alerts,
 * then renders the result as an HTML diagnosis card. The service transparently
 * falls back to rule-based analysis when OPENROUTER_API_KEY is absent or the
 * API call fails, ensuring the page always returns a valid diagnosis.
 *
 * Authentication is enforced by BasicAuthMiddleware at the middleware layer;
 * this controller does not repeat the auth check.
 *
 * Route:
 *   GET /ai-diagnostics → index()
 */
class AiDiagnosticsController extends AppController
{
    /**
     * Render the AI diagnostics page.
     *
     * Propagates the correlation_id from the request attribute so that every
     * log entry emitted during diagnosis (AI call, fallback activation) is
     * traceable in the Log Viewer by the same ID as the originating HTTP request.
     *
     * View variables injected:
     *   $diagnosis — DiagnosisResult containing text, source badge, and metadata.
     *
     * @return void CakePHP renders templates/Pages/ai_diagnostics.php.
     */
    public function index(): void
    {
        // Propagate correlation ID so audit logs are traceable to this request.
        $correlationId = (string)$this->request->getAttribute('correlation_id', '');

        $service = new AiDiagnosticsService(
            $this->fetchTable('Metrics'),
            $this->fetchTable('Alerts'),
        );

        $diagnosis = $service->diagnose($correlationId);

        $this->set(compact('diagnosis'));

        // Override both template path and name: CakePHP would default to
        // templates/AiDiagnostics/index.php; the TASK places the file at
        // templates/Pages/ai_diagnostics.php.
        $this->viewBuilder()
            ->setTemplatePath('Pages')
            ->setTemplate('ai_diagnostics');
    }
}
