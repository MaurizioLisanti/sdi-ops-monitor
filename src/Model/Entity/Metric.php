<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Metric entity.
 *
 * Fields (see migration 20260402000001):
 *   id, source, name, value, unit, tags (json), recorded_at, created, modified
 *
 * @skeleton M0
 * TODO (Planner): add accessor methods, validation rules in Table layer.
 */
class Metric extends Entity
{
    protected array $_accessible = [
        'source'      => true,
        'name'        => true,
        'value'       => true,
        'unit'        => true,
        'tags'        => true,
        'recorded_at' => true,
    ];
}
