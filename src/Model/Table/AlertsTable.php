<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * AlertsTable — persistence layer for operational alerts.
 *
 * Maps to the `alerts` database table (see migration 20260402000002).
 *
 * @todo (Planner): integrate alert engine service (M1); add acknowledge action.
 */
class AlertsTable extends Table
{
    /**
     * Configure the table, associations, and behaviours.
     *
     * @param array<string, mixed> $config Table configuration options.
     * @return void
     */
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

    /**
     * Define default validation rules for Alert entities.
     *
     * @param \Cake\Validation\Validator $validator The validator instance to configure.
     * @return \Cake\Validation\Validator The fully configured validator.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->notEmptyString('severity')
            ->inList('severity', ['low', 'medium', 'high', 'critical'])
            ->notEmptyString('message')
            ->inList('status', ['open', 'acknowledged', 'resolved']);

        return $validator;
    }

    /**
     * Custom finder: open alerts sorted by severity (most severe first).
     *
     * Filters to status = 'open' and sorts by severity DESC.
     * Note: ordering is alphabetical DESC ('medium' > 'low' > 'high' > 'critical');
     * semantic severity ordering via FIELD() is deferred to M1.
     *
     * @param \Cake\ORM\Query\SelectQuery $query The base query to filter.
     * @return \Cake\ORM\Query\SelectQuery The query restricted to open alerts.
     */
    public function findOpen(SelectQuery $query): SelectQuery
    {
        return $query
            ->where(['status' => 'open'])
            ->orderByDesc('severity');
    }
}
