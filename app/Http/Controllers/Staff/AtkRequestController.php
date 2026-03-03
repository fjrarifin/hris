<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AtkRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class AtkRequestController extends Controller
{
    public function index()
    {
        $requests = AtkRequest::where('user_id', Auth::user()->id)
            ->latest()
            ->get();

        return view('staff.atk.index', compact('requests'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_name' => 'required|string|max:100',
            'quantity'  => 'required|integer|min:1',
            'note'      => 'nullable|string|max:255',
        ]);

        $data['user_id'] = Auth::user()->id;

        AtkRequest::create($data);

        return back()->with('success', 'Pengajuan ATK berhasil dikirim');
    }

    public function destroy($id)
    {
        AtkRequest::where('id', $id)
            ->where('user_id', Auth::user()->id)
            ->where('status', 'pending')
            ->delete();

        return back()->with('success', 'Pengajuan ATK berhasil dihapus');
    }
}