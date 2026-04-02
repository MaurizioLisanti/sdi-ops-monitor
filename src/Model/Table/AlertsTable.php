<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * AlertsTable — persistence layer for operational alerts.
 *
 * @skeleton M0
 * TODO (Planner): add finders (open, bySeverity), integrate alert engine service.
 */
class AlertsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('alerts');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Metrics', [
            'foreignKey' => 'metric_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->notEmptyString('severity')
            ->inList('severity', ['low', 'medium', 'high', 'critical'])
            ->notEmptyString('message')
            ->inList('status', ['open', 'acknowledged', 'resolved']);

        return $validator;
    }
}
