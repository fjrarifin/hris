<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OvertimeController extends Controller
{
    public function index()
    {
        $requests = OvertimeRequest::where('user_id', Auth::user()->id)
            ->latest()
            ->get();

        return view('staff.overtime.index', compact('requests'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date'       => 'required|date|after_or_equal:today',
            'start_time' => 'required',
            'end_time'   => 'required|after:start_time',
            'reason'     => 'required|max:255',
        ]);

        $data['user_id'] = Auth::user()->id;
        $data['status']  = 'pending';

        OvertimeRequest::create($data);

        return back()->with('success', 'Pengajuan lembur berhasil dikirim');
    }


    public function destroy($id)
    {
        OvertimeRequest::where('id', $id)
            ->where('user_id', Auth::user()->id)
            ->where('status', 'pending')
            ->delete();

        return back()->with('success', 'Pengajuan lembur berhasil dihapus');
    }
}