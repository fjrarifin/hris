<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LeaveRequest;
use App\Models\Karyawan;
use App\Http\Services\WhatsAppService;
use App\Notifications\LeaveStatusNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AutoRejectExpiredLeave extends Command
{
    protected $signature = 'leave:auto-reject';
    protected $description = 'Auto reject leave request if start date has passed';



    public function handle()
    {
        // Log::info('CRON TEST', [
        //     'env' => app()->environment(),
        //     'app_env' => config('app.env'),
        //     'db' => config('database.default'),
        //     'time' => now()->toDateTimeString(),
        // ]);


        Log::info('=== AUTO REJECT CUTI STARTED ===');
        $this->info('=== Starting Auto Reject Process ===');

        $expiredLeaves = LeaveRequest::with('user')
            ->where('status', 'pending')
            ->whereDate('start_date', '<', Carbon::today())
            ->get();

        Log::info("Found {$expiredLeaves->count()} expired leave(s)");
        $this->info("Found {$expiredLeaves->count()} expired leave(s)");

        if ($expiredLeaves->isEmpty()) {
            Log::info('No expired leaves found');
            $this->info('Tidak ada cuti expired.');
            return;
        }

        $waService = app(WhatsAppService::class);

        foreach ($expiredLeaves as $leave) {
            Log::info("Processing Leave ID: {$leave->id}", [
                'user_id' => $leave->user_id,
                'username' => $leave->user->username,
                'start_date' => $leave->start_date,
            ]);

            // Update status
            $leave->update([
                'status' => 'rejected',
                'reject_reason' => 'Pengajuan otomatis ditolak karena tanggal mulai sudah terlewat.',
            ]);

            Log::info("Leave ID {$leave->id} status updated to rejected");
            $this->info("✓ Status updated to rejected");

            // Send notification
            try {
                $leave->user->notify(
                    new LeaveStatusNotification(
                        $leave,
                        'rejected',
                        'Pengajuan otomatis ditolak karena tanggal mulai sudah terlewat.'
                    )
                );
                Log::info("Email notification sent for Leave ID {$leave->id}");
                $this->info("✓ Email notification sent");
            } catch (\Throwable $e) {
                Log::error("Email notification failed for Leave ID {$leave->id}", [
                    'error' => $e->getMessage()
                ]);
                $this->error("✗ Email notification failed: " . $e->getMessage());
            }

            // 📱 Kirim WA ke staff
            $karyawan = Karyawan::where('nik', $leave->user->username)->first();

            if (!$karyawan) {
                Log::warning("Karyawan not found for NIK: {$leave->user->username}");
                $this->error("✗ Karyawan not found");
                continue;
            }

            Log::info("Karyawan found", [
                'nik' => $karyawan->nik,
                'nama' => $karyawan->nama_karyawan,
                'no_hp' => $karyawan->no_hp,
            ]);

            if (!$karyawan->no_hp) {
                Log::warning("No phone number for karyawan NIK: {$karyawan->nik}");
                $this->error("✗ No phone number");
                continue;
            }

            // Format phone number
            $phone = preg_replace('/[^0-9]/', '', $karyawan->no_hp);

            if (str_starts_with($phone, '0')) {
                $phone = '62' . substr($phone, 1);
            } elseif (!str_starts_with($phone, '62')) {
                $phone = '62' . $phone;
            }

            Log::info("Phone formatted", [
                'original' => $karyawan->no_hp,
                'formatted' => $phone,
            ]);

            // Prepare message
            $durasi = Carbon::parse($leave->start_date)->diffInDays($leave->end_date) + 1;

            $message =
                "❌ *CUTI OTOMATIS DITOLAK*\n\n" .
                "Jenis: *" . (LeaveRequest::LEAVE_TYPES[$leave->leave_type] ?? $leave->leave_type) . "*\n" .
                "Tanggal: *" .
                Carbon::parse($leave->start_date)->format('d M Y') .
                " - " .
                Carbon::parse($leave->end_date)->format('d M Y') .
                "*\n" .
                "Durasi: *{$durasi} hari*\n\n" .
                "Alasan:\n" .
                "Tanggal mulai sudah terlewat (H+1).\n\n" .
                "Silakan ajukan kembali jika diperlukan.\n\n— HRIS System";

            // Send WA
            try {
                Log::info("Attempting to send WA", [
                    'leave_id' => $leave->id,
                    'phone' => $phone,
                ]);

                $response = $waService->sendMessage($phone, $message);

                Log::info("WA sent successfully", [
                    'leave_id' => $leave->id,
                    'phone' => $phone,
                    'response' => $response,
                ]);

                $this->info("✓ WhatsApp sent to {$phone}");
            } catch (\Throwable $e) {
                Log::error("WA send failed", [
                    'leave_id' => $leave->id,
                    'phone' => $phone,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $this->error("✗ WhatsApp failed: " . $e->getMessage());
            }

            $this->info("Leave ID {$leave->id} processed.\n");
        }

        Log::info('=== AUTO REJECT CUTI COMPLETED ===');
        $this->info('=== Auto Reject Process Completed ===');
    }
}
