<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Metric entity — represents a single metric event ingested from an external system.
 *
 * Database columns (see migration 20260402000001_CreateMetricsTable):
 *   - id          (int, PK, auto-increment)
 *   - source      (string, 128) — origin system/service identifier, e.g. "aws-ec2-prod-01"
 *   - name        (string, 128) — metric name, e.g. "cpu_usage"
 *   - value       (decimal 18,4) — measured value
 *   - unit        (string, 32, nullable) — measurement unit, e.g. "percent"
 *   - tags        (text/JSON, nullable) — key-value metadata pairs; serialised as JSON by ORM
 *   - recorded_at (datetime) — when the metric was recorded at the source; cast to DateTimeImmutable
 *   - created     (datetime) — set automatically by TimestampBehavior
 *   - modified    (datetime) — set automatically by TimestampBehavior
 */
class Metric extends Entity
{
    /**
     * Fields that may be mass-assigned via newEntity() / patchEntity().
     *
     * `id`, `created`, and `modified` are intentionally excluded to prevent
     * accidental overwrites from user-supplied data.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'source'      => true,
        'name'        => true,
        'value'       => true,
        'unit'        => true,
        'tags'        => true,
        'recorded_at' => true,
    ];
}
