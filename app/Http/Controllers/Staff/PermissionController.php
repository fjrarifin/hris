<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Services\ApprovalNotificationService;
use App\Models\EmployeePermission;
use App\Models\LeaveRequest;
use App\Models\PublicHolidayRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;


class PermissionController extends Controller
{
    public function __construct(private ApprovalNotificationService $approvalNotificationService)
    {
    }

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

        $user = Auth::user();
        $date = $data['date'];

        $exists = EmployeePermission::where('user_id', $user->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->whereDate('date', $date)
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['date' => 'Tanggal izin/sakit sudah pernah diajukan.'])
                ->withInput();
        }

        $leaveBentrok = LeaveRequest::where('user_id', $user->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();

        if ($leaveBentrok) {
            return back()
                ->withErrors(['date' => 'Tanggal izin bentrok dengan pengajuan cuti.'])
                ->withInput();
        }

        $phBentrok = PublicHolidayRequest::where('user_id', $user->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->whereDate('claim_date', $date)
            ->exists();

        if ($phBentrok) {
            return back()
                ->withErrors(['date' => 'Tanggal izin bentrok dengan pengajuan Hari Libur.'])
                ->withInput();
        }

        if ($request->hasFile('document')) {
            $data['document'] = $request->file('document')
                ->store('permission-documents', 'public');
        }

        $data['user_id'] = $user->id;
        $data['status']  = 'pending';
        $data['approval_token'] = (string) Str::uuid();
        $data['approval_token_expires_at'] = now()->addHours(24);

        $permission = EmployeePermission::create($data);

        $this->approvalNotificationService
            ->notifyManager($permission, 'IZIN');

        return back()->with('success', 'Pengajuan izin/sakit berhasil dikirim');
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
