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
 * No rate limiting: Basic Auth is the only access control, which is adequate
 * for a single-operator deployment behind a private network but not for a
 * public endpoint. See the security notes in README before exposing this.
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
