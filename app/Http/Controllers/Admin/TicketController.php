<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate(['q' => ['nullable', 'string', 'max:60']]);

        $tickets = Ticket::with('booking:id,booking_code,status')
            ->when($f['q'] ?? null, function ($q, $v) {
                $like = '%'.addcslashes($v, '%_\\').'%';
                $q->where('ticket_number', 'like', $like)->orWhereHas('booking', fn ($b) => $b->where('booking_code', 'like', $like));
            })
            ->latest('id')->paginate(15)->withQueryString();

        return view('admin.tickets.index', ['tickets' => $tickets, 'f' => $f]);
    }
}