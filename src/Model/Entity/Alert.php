<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Alert entity.
 *
 * Fields (see migration 20260402000002):
 *   id, metric_id (fk), severity, message, status, acknowledged_at, created, modified
 *
 * @property int $id
 * @property int|null $metric_id
 * @property string $severity
 * @property string $message
 * @property string $status
 * @property \Cake\I18n\DateTime|null $acknowledged_at
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @skeleton M0
 * TODO (Planner): implement status transitions (open → ack → resolved).
 */
class Alert extends Entity
{
    protected array $_accessible = [
        'metric_id' => true,
        'severity' => true,
        'message' => true,
        'status' => true,
        'acknowledged_at' => true,
    ];
}
