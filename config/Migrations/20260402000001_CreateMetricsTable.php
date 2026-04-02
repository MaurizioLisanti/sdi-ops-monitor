<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Migration: create metrics table.
 *
 * @skeleton M0
 * TODO (Planner): review column types/precision; add composite indexes after load testing.
 */
class CreateMetricsTable extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('metrics');
        $table
            ->addColumn('source', 'string', ['limit' => 128, 'null' => false, 'comment' => 'Origin system/service'])
            ->addColumn('name', 'string', ['limit' => 128, 'null' => false, 'comment' => 'Metric name, e.g. cpu_usage'])
            ->addColumn('value', 'decimal', ['precision' => 18, 'scale' => 4, 'null' => false])
            ->addColumn('unit', 'string', ['limit' => 32, 'null' => true, 'default' => null])
            ->addColumn('tags', 'text', ['null' => true, 'default' => null, 'comment' => 'JSON key-value pairs'])
            ->addColumn('recorded_at', 'datetime', ['null' => false])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['source', 'name'], ['name' => 'idx_metrics_source_name'])
            ->addIndex(['recorded_at'], ['name' => 'idx_metrics_recorded_at'])
            ->create();
    }
}
