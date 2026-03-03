<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\EmployeePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;


class PermissionController extends Controller
{
    public function index()
    {
        $requests = EmployeePermission::where('user_id', Auth::id())
            ->latest()
            ->get();

        return view('staff.permission.index', compact('requests'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'     => 'required|in:izin,sakit',
            'date'     => 'required|date|after_or_equal:today',
            'reason'   => 'required_if:type,izin|max:255',
            'document' => 'required_if:type,sakit|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('document')) {
            $data['document'] = $request->file('document')
                ->store('permission-documents', 'public');
        }

        $data['user_id'] = Auth::user()->id;
        $data['status']  = 'pending';

        EmployeePermission::create($data);

        return back()->with('success', 'Pengajuan berhasil dikirim');
    }


    public function destroy($id)
    {
        $permission = EmployeePermission::where('id', $id)
            ->where('user_id', Auth::id())
            ->where('status', 'pending')
            ->firstOrFail();

        if ($permission->document) {
            Storage::disk('public')->delete($permission->document);
        }

        $permission->delete();

        return back()->with('success', 'Pengajuan berhasil dihapus');
    }
}
