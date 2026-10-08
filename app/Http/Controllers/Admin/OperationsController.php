<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Operations\Queries\OperationsMetrics;
use App\Modules\Operations\Support\Readiness;
use Illuminate\Contracts\View\View;

/**
 * Admin-only operations page: 7-day turn, tool, and request counts, pilot
 * alerts, and readiness. Figures are counts only; no request content.
 */
class OperationsController extends Controller
{
    public function __invoke(OperationsMetrics $metrics, Readiness $readiness): View
    {
        return view('admin.operations', [
            'metrics' => $metrics->handle(),
            'readiness' => $readiness->handle(),
        ]);
    }
}
