<?php

namespace App\Http\Controllers\GA;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        $kpi = [
            'sla' => 97.2,
            'downtime' => 12,
            'budget_usage' => 82,
            'incident' => 3,
            'compliance' => 96,
        ];

        $slaTrend = [
            'labels' => ['Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan'],
            'data' => [95, 96, 94, 97, 98, 97.2],
        ];

        return view('ga.dashboard.index', compact('kpi', 'slaTrend'));
    }
}
