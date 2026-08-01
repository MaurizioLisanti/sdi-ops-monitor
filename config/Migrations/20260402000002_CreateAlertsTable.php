<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Migration: create alerts table.
 *
 * @skeleton M0
 * metric_id references metrics.id but carries no database-level foreign key
 * constraint: the column is nullable and indexed, and the relationship is
 * enforced by the ORM association alone. The consequence worth knowing is that
 * deleting a metric leaves its alerts pointing at a row that no longer exists,
 * so a retention policy on metrics must clear the matching alerts itself.
 */
class CreateAlertsTable extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('alerts');
        $table
            ->addColumn('metric_id', 'integer', ['null' => true, 'default' => null, 'comment' => 'FK → metrics.id'])
            ->addColumn('severity', 'string', ['limit' => 16, 'null' => false, 'default' => 'low'])
            ->addColumn('message', 'text', ['null' => false])
            ->addColumn('status', 'string', ['limit' => 16, 'null' => false, 'default' => 'open'])
            ->addColumn('acknowledged_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['metric_id'], ['name' => 'idx_alerts_metric_id'])
            ->addIndex(['status', 'severity'], ['name' => 'idx_alerts_status_severity'])
            ->create();
    }
}
