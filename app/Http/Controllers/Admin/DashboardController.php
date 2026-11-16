<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Discount;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'stats' => [
                'discounts_active' => Discount::activeNow()->count(),
                'discounts_total' => Discount::count(),
                'campaigns_active' => Campaign::activeNow()->count(),
                'campaigns_upcoming' => Campaign::upcoming()->count(),
                'campaigns_draft' => Campaign::where('is_published', false)->count(),
            ],
            'logs' => AuditLog::with('user:id,name')->latest('id')->limit(8)->get(),
        ]);
    }

    public function audit()
    {
        return view('admin.audit', [
            'logs' => AuditLog::with('user:id,name')->latest('id')->paginate(25),
        ]);
    }
}