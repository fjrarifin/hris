<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\PublicHolidayRequest;
use App\Notifications\LeaveStatusNotification;
use App\Notifications\PublicHolidayStatusNotification;
use Illuminate\Http\Request;

class PublicApprovalController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | SHOW APPROVAL PAGE
    |--------------------------------------------------------------------------
    */
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

        return view('approval.show', [
            'request' => $request,
            'type'    => $this->detectType($request)
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | APPROVE
    |--------------------------------------------------------------------------
    */
    public function approve($token)
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

        $request->update([
            'manager_approved_at' => now(),
            'manager_approved_by' => null, // karena via token
            'status' => 'approved',
            'approval_token' => null,
            'approval_token_expires_at' => null,
        ]);

        // 🔔 Notify Staff
        $this->notifyUser($request, 'approved');

        return view('approval.approved-success');
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT
    |--------------------------------------------------------------------------
    */
    public function reject($token)
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

        $request->update([
            'manager_approved_at' => now(),
            'manager_approved_by' => null, // karena via token
            'status' => 'rejected',
            'approval_token' => null,
            'approval_token_expires_at' => null,
        ]);

        // 🔔 Notify Staff
        $this->notifyUser($request, 'rejected');

        return view('approval.rejected-success');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER: FIND REQUEST
    |--------------------------------------------------------------------------
    */
    private function findRequestByToken($token)
    {
        return LeaveRequest::with('user')
            ->where('approval_token', $token)
            ->first()
            ?? PublicHolidayRequest::with('user', 'holiday')
            ->where('approval_token', $token)
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER: DETECT TYPE
    |--------------------------------------------------------------------------
    */
    private function detectType($request)
    {
        if ($request instanceof LeaveRequest) {
            return 'leave';
        }

        if ($request instanceof PublicHolidayRequest) {
            return 'ph';
        }

        return 'unknown';
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER: NOTIFY USER
    |--------------------------------------------------------------------------
    */
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
