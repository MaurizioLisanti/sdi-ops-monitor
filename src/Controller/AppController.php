<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Controller\Controller;

/**
 * AppController — base controller.
 *
 * Auth (BasicAuthMiddleware) and correlation_id propagation (CorrelationIdMiddleware)
 * are handled at the middleware layer since M1.
 *
 * TODO: add rate-limit middleware.
 */
class AppController extends Controller
{
    /**
     * Initialize controller.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Flash');
    }
}
