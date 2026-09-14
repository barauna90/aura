<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use App\Services\Billing\AccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AnalyticsService $analytics, AccessService $access): View
    {
        $user = $request->user();

        return view('app.dashboard', $analytics->dashboard($user) + ['access' => $access->resolve($user)]);
    }
}
