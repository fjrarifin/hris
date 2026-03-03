<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveRequest;
use App\Models\PublicHolidayRequest;
use App\Notifications\LeaveStatusNotification;
use App\Notifications\PublicHolidayStatusNotification;

class PublicLeaveApprovalController extends Controller
{
    public function show($token)
    {
        $request = $this->findRequestByToken($token);

        if (!$request) {
            abort(404);
        }

        if ($request->status !== 'pending') {
            return view('approval.already-processed');
        }

        if (
            !$request->approval_token_expires_at ||
            now()->greaterThan($request->approval_token_expires_at)
        ) {
            return view('approval.expired');
        }

        return view('approval.page', compact('request'));
    }

    public function approve($token)
    {
        $request = $this->findRequestByToken($token);

        if (!$request || $request->status !== 'pending') {
            return view('approval.already-processed');
        }

        $request->update([
            'status' => 'approved',
            'approval_token' => null,
            'approval_token_expires_at' => null,
        ]);

        $this->notifyUser($request, 'approved');

        return view('approval.success', ['type' => 'approved']);
    }

    public function reject($token)
    {
        $request = $this->findRequestByToken($token);

        if (!$request || $request->status !== 'pending') {
            return view('approval.already-processed');
        }

        $request->update([
            'status' => 'rejected',
            'approval_token' => null,
            'approval_token_expires_at' => null,
        ]);

        $this->notifyUser($request, 'rejected');

        return view('approval.success', ['type' => 'rejected']);
    }

    private function findRequestByToken($token)
    {
        return LeaveRequest::where('approval_token', $token)->first()
            ?? PublicHolidayRequest::where('approval_token', $token)->first();
    }

    private function notifyUser($request, $status)
    {
        if ($request instanceof LeaveRequest) {
            $request->user->notify(
                new LeaveStatusNotification($request, $status)
            );
        }

        if ($request instanceof PublicHolidayRequest) {
            $request->user->notify(
                new PublicHolidayStatusNotification($request, $status)
            );
        }
    }
}
