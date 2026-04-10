<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * LogViewerController — web UI for inspecting structured JSON application logs.
 *
 * Provides a read-only, BasicAuth-protected view of the application log file
 * (ROOT/logs/app.log). Each log line is parsed as JSON; malformed lines are
 * surfaced as raw entries so no output is silently lost.
 *
 * Operators can filter by log level and correlation ID without needing SSH
 * access to the server.
 *
 * Security: the log file path is hardcoded to ROOT/logs/app.log and is never
 * derived from user input, preventing path-traversal attacks (TASK A5).
 *
 * Routes:
 *   GET /logs  → index()
 */
class LogViewerController extends AppController
{
    /**
     * Absolute path to the application log file.
     *
     * Intentionally hardcoded — never derive this from user input.
     */
    private const LOG_FILE_PATH = ROOT . DS . 'logs' . DS . 'app.log';

    /**
     * Default number of tail lines to display when ?lines= is absent.
     */
    private const DEFAULT_LINES = 200;

    /**
     * Hard cap on ?lines= to prevent out-of-memory on large log files (TASK A4).
     */
    private const MAX_LINES = 1000;

    /**
     * Valid log level strings accepted by the ?level= filter.
     *
     * @var array<string>
     */
    private const VALID_LEVELS = ['debug', 'info', 'warning', 'error', 'critical'];

    /**
     * Render the log viewer page.
     *
     * Reads the last N lines from ROOT/logs/app.log, parses each line as JSON,
     * and applies optional query-string filters. Results are displayed in
     * reverse-chronological order (newest entry at the top) so the most recent
     * events are immediately visible without scrolling.
     *
     * When the log file does not exist the view receives an empty entry list and
     * logFileFound=false; the template shows "No log file found" instead of
     * triggering a 500 error.
     *
     * Query parameters accepted:
     *   ?lines=N               — tail line count (default: 200, max: 1000)
     *   ?level=<string>        — filter by level (debug|info|warning|error|critical)
     *   ?correlation_id=<uuid> — filter by exact correlation ID
     *
     * @return void CakePHP renders templates/LogViewer/index.php automatically.
     */
    public function index(): void
    {
        $lineCount         = $this->resolveLineCount();
        $levelFilter       = $this->resolveLevelFilter();
        $correlationFilter = trim((string)($this->request->getQuery('correlation_id') ?? ''));

        if (!file_exists(self::LOG_FILE_PATH)) {
            $this->set([
                'entries'           => [],
                'logFileFound'      => false,
                'lineCount'         => $lineCount,
                'levelFilter'       => $levelFilter,
                'correlationFilter' => $correlationFilter,
                'validLevels'       => self::VALID_LEVELS,
            ]);

            return;
        }

        $rawLines = $this->readLastLines(self::LOG_FILE_PATH, $lineCount);
        $entries  = $this->parseLines($rawLines);
        $entries  = $this->applyFilters($entries, $levelFilter, $correlationFilter);

        // Reverse so the newest log entry appears at the top of the table.
        $entries = array_reverse($entries);

        $this->set([
            'entries'           => $entries,
            'logFileFound'      => true,
            'lineCount'         => $lineCount,
            'levelFilter'       => $levelFilter,
            'correlationFilter' => $correlationFilter,
            'validLevels'       => self::VALID_LEVELS,
        ]);
    }

    /**
     * Resolve and clamp the ?lines= query parameter to a safe integer.
     *
     * Returns DEFAULT_LINES when the parameter is absent or non-numeric.
     * Clamps the value to [1, MAX_LINES] to prevent empty-page and OOM scenarios.
     *
     * @return int Number of tail lines to read from the log file.
     */
    private function resolveLineCount(): int
    {
        $raw = (int)($this->request->getQuery('lines') ?? self::DEFAULT_LINES);

        return max(1, min($raw, self::MAX_LINES));
    }

    /**
     * Resolve the ?level= query parameter, rejecting unknown level strings.
     *
     * Returns an empty string when the parameter is absent or does not match
     * one of VALID_LEVELS, which disables level filtering.
     *
     * @return string Validated log level, or '' when no valid filter is active.
     */
    private function resolveLevelFilter(): string
    {
        $raw = strtolower(trim((string)($this->request->getQuery('level') ?? '')));

        return in_array($raw, self::VALID_LEVELS, true) ? $raw : '';
    }

    /**
     * Read the last N non-empty lines from a file.
     *
     * Uses file() with FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES, then
     * takes a tail slice. This loads the whole file into memory; suitable for
     * log files up to several hundred MiB at the configured MAX_LINES cap.
     *
     * @param  string       $path  Absolute path to the log file.
     * @param  int          $count Number of tail lines to return (≥ 1).
     * @return array<string>       Raw log line strings, oldest first.
     */
    private function readLastLines(string $path, int $count): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return [];
        }

        return array_slice($lines, -$count);
    }

    /**
     * Parse raw log line strings into structured entry arrays.
     *
     * A line that decodes to a valid JSON object yields a structured entry with
     * the standard fields (timestamp, level, message, correlation_id, context).
     * A line that cannot be decoded is returned as a 'raw' entry so no log
     * output is silently discarded (e.g. startup banners or plain-text entries).
     *
     * @param  array<string>          $lines Raw log line strings.
     * @return array<array<string, mixed>> Parsed log entry arrays.
     */
    private function parseLines(array $lines): array
    {
        $entries = [];

        foreach ($lines as $line) {
            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                $contextRaw = $decoded['context'] ?? null;
                $entries[] = [
                    'timestamp'      => (string)($decoded['timestamp'] ?? ''),
                    'level'          => strtolower((string)($decoded['level'] ?? 'info')),
                    'message'        => (string)($decoded['message'] ?? ''),
                    'correlation_id' => (string)($decoded['correlation_id'] ?? ''),
                    // Compact JSON for context column — reduces visual noise in the table.
                    'context'        => $contextRaw !== null
                        ? (string)json_encode($contextRaw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                        : '',
                    'raw'            => false,
                ];
            } else {
                // Non-JSON line surfaced verbatim — no log output is silently lost.
                $entries[] = [
                    'timestamp'      => '',
                    'level'          => 'raw',
                    'message'        => $line,
                    'correlation_id' => '',
                    'context'        => '',
                    'raw'            => true,
                ];
            }
        }

        return $entries;
    }

    /**
     * Filter parsed log entries by level and/or correlation_id.
     *
     * An empty string for either parameter disables that filter dimension.
     * When both are set they are combined with AND logic (both must match).
     *
     * @param  array<array<string, mixed>> $entries           Parsed log entry arrays.
     * @param  string                      $levelFilter       Log level to keep, or '' for all.
     * @param  string                      $correlationFilter Exact correlation ID to keep, or '' for all.
     * @return array<array<string, mixed>> Filtered and re-indexed log entry arrays.
     */
    private function applyFilters(array $entries, string $levelFilter, string $correlationFilter): array
    {
        if ($levelFilter === '' && $correlationFilter === '') {
            return $entries;
        }

        return array_values(array_filter(
            $entries,
            function (array $entry) use ($levelFilter, $correlationFilter): bool {
                if ($levelFilter !== '' && $entry['level'] !== $levelFilter) {
                    return false;
                }

                if ($correlationFilter !== '' && $entry['correlation_id'] !== $correlationFilter) {
                    return false;
                }

                return true;
            }
        ));
    }
}
