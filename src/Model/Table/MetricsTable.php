<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * MetricsTable — persistence layer for metric events.
 *
 * Maps to the `metrics` database table (see migration 20260402000001).
 * The `tags` column is stored as TEXT and automatically serialised/deserialised
 * as JSON via the ORM type system.
 *
 * @todo (Planner): add scoped finders (bySource, byName, recentN).
 */
class MetricsTable extends Table
{
    /**
     * Configure the table, schema type overrides, associations, and behaviours.
     *
     * The `tags` column is mapped to the `json` ORM type so that array/object
     * values are automatically serialised on save and deserialised on load,
     * even though the underlying column type is TEXT.
     *
     * @param array<string, mixed> $config Table configuration options.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('metrics');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        // Map the TEXT column to the json ORM type so that arrays are
        // automatically serialised on save and deserialised on load.
        $this->getSchema()->setColumnType('tags', 'json');

        $this->hasMany('Alerts', [
            'foreignKey' => 'metric_id',
        ]);
    }

    /**
     * Define default validation rules for Metric entities.
     *
     * Required fields on create: source, name, value, recorded_at.
     * Optional fields: unit, tags.
     *
     * @param \Cake\Validation\Validator $validator The validator instance to configure.
     * @return \Cake\Validation\Validator The fully configured validator.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('source')
            ->maxLength('source', 128)
            ->requirePresence('source', 'create')
            ->notEmptyString('source');

        $validator
            ->scalar('name')
            ->maxLength('name', 128)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->decimal('value')
            ->requirePresence('value', 'create')
            ->notEmptyString('value');

        $validator
            ->dateTime('recorded_at')
            ->requirePresence('recorded_at', 'create')
            ->notEmptyDateTime('recorded_at');

        $validator
            ->scalar('unit')
            ->maxLength('unit', 32)
            ->allowEmptyString('unit');

        $validator
            ->allowEmptyArray('tags');

        return $validator;
    }
}
