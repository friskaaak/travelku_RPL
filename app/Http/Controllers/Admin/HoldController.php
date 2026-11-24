<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hold;
use App\Services\AuditService;
use App\Services\Inventory\HoldService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HoldController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate(['status' => ['nullable', Rule::in(['Active', 'Expired', 'Consumed', 'Released'])]]);
        $status = $f['status'] ?? 'Active';

        $holds = Hold::with('user:id,name,email')->where('status', $status)->latest('id')->paginate(15)->withQueryString();

        return view('admin.holds.index', ['holds' => $holds, 'status' => $status]);
    }

    public function release(Hold $hold, HoldService $holds)
    {
        if ($holds->release($hold, Hold::RELEASED, 'Dilepas oleh admin')) {
            AuditService::log('hold.released_by_admin', $hold, ['hold' => $hold->hold_code]);

            return back()->with('success', 'Penahanan dilepas. Inventori kembali tersedia.');
        }

        return back()->with('error', 'Penahanan ini sudah tidak aktif.');
    }
}