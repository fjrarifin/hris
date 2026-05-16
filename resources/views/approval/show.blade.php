@extends('layouts.public')

@section('title', 'Approval Pengajuan')

@section('content')

<div class="approval-card">

    {{-- HEADER --}}
    <div class="header-gradient">
        <i class="fas fa-envelope-open-text fa-2x mb-2"></i>
        <h2 class="text-lg font-bold mb-1">
            Approval Pengajuan {{ strtoupper($type) }}
        </h2>
        <p class="text-xs opacity-90">
            Tinjau dan berikan keputusan
        </p>
    </div>

    <div class="p-4">

        {{-- INFO KARYAWAN --}}
        <div class="flex items-center gap-3 mb-4 pb-3 border-b">
            <div class="bg-indigo-100 rounded-full p-3">
                <i class="fas fa-user fa-lg text-indigo-600"></i>
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-gray-800 text-base">
                    {{ $request->user->name }}
                </h3>
            </div>
        </div>


        {{-- ============================= --}}
        {{-- DETAIL LEAVE --}}
        {{-- ============================= --}}
        @if($type === 'leave')

            <div class="space-y-3 mb-4">

                <div class="flex items-start gap-2 text-sm">
                    <i class="fas fa-list-alt text-indigo-600 mt-0.5 text-xs"></i>
                    <div class="flex-1">
                        <p class="text-xs text-gray-500">Jenis Cuti</p>
                        <p class="font-semibold text-gray-800">
                            {{ \App\Models\LeaveRequest::LEAVE_TYPES[$request->leave_type] ?? $request->leave_type }}
                        </p>
                    </div>
                </div>

                <div class="flex items-start gap-2 text-sm">
                    <i class="fas fa-calendar-alt text-blue-600 mt-0.5 text-xs"></i>
                    <div class="flex-1">
                        <p class="text-xs text-gray-500">Periode</p>
                        <p class="font-semibold text-gray-800 text-sm">
                            {{ \Carbon\Carbon::parse($request->start_date)->isoFormat('D MMM YYYY') }}
                            -
                            {{ \Carbon\Carbon::parse($request->end_date)->isoFormat('D MMM YYYY') }}
                        </p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ \Carbon\Carbon::parse($request->start_date)->diffInDays($request->end_date) + 1 }} hari
                        </p>
                    </div>
                </div>

                @if($request->reason)
                    <div class="flex items-start gap-2 text-sm">
                        <i class="fas fa-comment-alt text-purple-600 mt-0.5 text-xs"></i>
                        <div class="flex-1">
                            <p class="text-xs text-gray-500">Keterangan</p>
                            <p class="text-gray-700 text-sm">
                                {{ $request->reason }}
                            </p>
                        </div>
                    </div>
                @endif

            </div>

        @endif


        {{-- ============================= --}}
        {{-- DETAIL PUBLIC HOLIDAY --}}
        {{-- ============================= --}}
        @if($type === 'ph')

            <div class="space-y-3 mb-4">

                <div class="flex items-start gap-2 text-sm">
                    <i class="fas fa-calendar text-green-600 mt-0.5 text-xs"></i>
                    <div class="flex-1">
                        <p class="text-xs text-gray-500">Hari Libur</p>
                        <p class="font-semibold text-gray-800">
                            {{ $request->holiday->name }}
                        </p>
                        <p class="text-xs text-gray-500">
                            {{ \Carbon\Carbon::parse($request->holiday->holiday_date)->isoFormat('D MMM YYYY') }}
                        </p>
                    </div>
                </div>

                <div class="flex items-start gap-2 text-sm">
                    <i class="fas fa-calendar-check text-blue-600 mt-0.5 text-xs"></i>
                    <div class="flex-1">
                        <p class="text-xs text-gray-500">Tanggal Claim</p>
                        <p class="font-semibold text-gray-800">
                            {{ \Carbon\Carbon::parse($request->claim_date)->isoFormat('D MMM YYYY') }}
                        </p>
                    </div>
                </div>

            </div>

        @endif


        {{-- WARNING --}}
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
            <div class="flex items-start gap-2">
                <i class="fas fa-exclamation-triangle text-yellow-600 text-xs mt-0.5"></i>
                <p class="text-xs text-yellow-800">
                    Keputusan bersifat final dan tidak dapat diubah
                </p>
            </div>
        </div>


        {{-- ACTION BUTTONS --}}
        <div class="grid grid-cols-2 gap-2">

            {{-- REJECT --}}
            <form method="POST"
                  action="{{ route('approval.reject', $request->approval_token) }}"
                  id="formReject">
                @csrf
                <button type="button"
                        onclick="confirmReject()"
                        class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 px-4 rounded-lg transition text-sm">
                    <i class="fas fa-times-circle mr-1"></i>
                    Tolak
                </button>
            </form>

            {{-- APPROVE --}}
            <form method="POST"
                  action="{{ route('approval.approve', $request->approval_token) }}"
                  id="formApprove">
                @csrf
                <button type="button"
                        onclick="confirmApprove()"
                        class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2.5 px-4 rounded-lg transition text-sm">
                    <i class="fas fa-check-circle mr-1"></i>
                    Setujui
                </button>
            </form>

        </div>

    </div>

    {{-- FOOTER --}}
    <div class="bg-gray-50 px-4 py-2 text-center border-t">
        <p class="text-xs text-gray-500">
            <i class="fas fa-shield-alt mr-1"></i>
            Link rahasia khusus untuk Anda
        </p>
    </div>

</div>

@endsection


@push('scripts')
<script>
function confirmApprove() {
    Swal.fire({
        title: 'Setujui Pengajuan?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Setujui',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#16a34a'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('formApprove').submit();
        }
    });
}

function confirmReject() {
    Swal.fire({
        title: 'Tolak Pengajuan?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Tolak',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('formReject').submit();
        }
    });
}
</script>
@endpush
