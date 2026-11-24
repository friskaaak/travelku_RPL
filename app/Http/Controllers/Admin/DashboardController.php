<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Campaign;
use App\Models\Discount;
use App\Models\EmailDelivery;
use App\Models\Hold;
use App\Models\Payment;

class DashboardController extends Controller
{
    public function index()
    {
        $gross = (int) Payment::whereIn('status', [Payment::PAID, Payment::REFUNDED])->sum('amount');
        $refunded = (int) Payment::sum('refunded_amount');

        return view('admin.dashboard', [
            'stats' => [
                'discounts_active' => Discount::activeNow()->count(),
                'discounts_total' => Discount::count(),
                'campaigns_active' => Campaign::activeNow()->count(),
                'campaigns_upcoming' => Campaign::upcoming()->count(),
                'campaigns_draft' => Campaign::where('is_published', false)->count(),
            ],
            'money' => ['gross' => $gross, 'refunded' => $refunded, 'net' => $gross - $refunded],
            'byStatus' => Booking::selectRaw('status, COUNT(*) AS c')->groupBy('status')->pluck('c', 'status'),
            'byService' => Booking::where('status', Booking::CONFIRMED)
                ->selectRaw('service_type, COUNT(*) AS c, SUM(total_amount) AS s')->groupBy('service_type')->get()->keyBy('service_type'),
            'attention' => [
                'reconciliation' => Payment::where('status', Payment::RECONCILIATION)->count(),
                'refund_pending' => Booking::where('refund_status', 'pending')->count(),
                'holds_active' => Hold::where('status', Hold::ACTIVE)->count(),
                'emails_failed' => EmailDelivery::where('status', 'failed')->count(),
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