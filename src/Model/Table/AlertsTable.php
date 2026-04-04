<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Database\Expression\FunctionExpression;
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
     * Custom finder: open alerts sorted by semantic severity (most severe first).
     *
     * Filters to status = 'open' and applies MySQL FIELD() to order rows by
     * severity in the sequence critical → high → medium → low, so that the most
     * actionable alerts appear at the top of any dashboard or API response.
     *
     * FIELD(col, v1, v2, ...) returns the 1-based position of col in the list;
     * rows with col not in the list get position 0 and sort last. Ascending order
     * therefore puts critical (position 1) first.
     *
     * @param \Cake\ORM\Query\SelectQuery $query The base query to filter.
     * @return \Cake\ORM\Query\SelectQuery The query restricted to open alerts, ordered by severity.
     */
    public function findOpen(SelectQuery $query): SelectQuery
    {
        // FIELD(severity, 'critical', 'high', 'medium', 'low') ASC
        // → critical=1, high=2, medium=3, low=4 → ascending gives critical first.
        $severityOrder = new FunctionExpression(
            'FIELD',
            ['Alerts.severity' => 'identifier', "'critical'", "'high'", "'medium'", "'low'"]
        );

        return $query
            ->where(['Alerts.status' => 'open'])
            ->orderByAsc($severityOrder);
    }
}
