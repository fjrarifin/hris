<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PublicHolidayRequest;
use Illuminate\Support\Facades\Auth;

class PublicHolidayController extends Controller
{
    public function index()
    {
        $requests = PublicHolidayRequest::with(['user', 'holiday'])
            ->whereNotNull('manager_approved_at')
            ->latest()
            ->get();

        return view('hr.public-holiday.index', compact('requests'));
    }

    public function approve($id)
    {
        $ph = PublicHolidayRequest::with('user')->findOrFail($id);

        $ph->update([
            'hr_approved_at' => now(),
            'hr_approved_by' => Auth::id(),
            'status' => 'approved'
        ]);

        return back()->with('success', 'PH disetujui HR');
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'reject_reason' => 'required|string|max:255'
        ]);

        $ph = PublicHolidayRequest::with('user')->findOrFail($id);

        $ph->update([
            'status' => 'rejected',
            'reject_reason' => $request->reject_reason,
            'hr_approved_by' => Auth::id(),
        ]);

        return back()->with('success', 'PH ditolak HR');
    }

    public function cancel(Request $request, $id)
    {
        $request->validate([
            'reject_reason' => 'required|string|max:255'
        ]);

        $ph = PublicHolidayRequest::with('user')->findOrFail($id);

        $ph->update([
            'status' => 'cancelled',
            'reject_reason' => $request->reject_reason,
            'hr_approved_by' => Auth::id(),
        ]);

        return back()->with('success', 'PH dibatalkan HR');
    }
}
