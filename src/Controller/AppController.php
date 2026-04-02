<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Controller\Controller;

/**
 * AppController — base controller.
 *
 * @skeleton M0
 * TODO (Planner): add auth component, rate-limit middleware, correlation_id injection.
 */
class AppController extends Controller
{
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Flash');
    }
}
