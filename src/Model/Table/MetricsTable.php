<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * MetricsTable — persistence layer for metric events.
 *
 * @skeleton M0
 * TODO (Planner): add indexes, scoped finders (bySource, byName, recentN).
 */
class MetricsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('metrics');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->hasMany('Alerts', [
            'foreignKey' => 'metric_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->notEmptyString('source')
            ->notEmptyString('name')
            ->numeric('value')
            ->allowEmptyString('unit')
            ->allowEmptyString('tags');

        return $validator;
    }
}
