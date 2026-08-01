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
 * @skeleton M0
 * Status is a free field with two states in practice, open and acknowledged.
 * A resolved state is deliberately absent: an alert stops being relevant when
 * the metric returns below threshold, which the evaluation loop already
 * reflects, so a manual resolution step would record an operator opinion
 * rather than a fact about the system.
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
