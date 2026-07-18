<!DOCTYPE html>
<html>
<head>
    <title>Surat Penawaran Kerja (Offering Letter) - Hompim Play</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #dddddd; border-radius: 5px;">
        <h2 style="color: #0f172a; border-bottom: 2px solid #10b981; padding-bottom: 10px;">Surat Penawaran Kerja (Offering Letter)</h2>
        
        <p>Halo Bapak/Ibu <strong>{{ $candidate->name }}</strong>,</p>
        
        <p>Terima kasih telah mengikuti seluruh rangkaian proses rekrutmen di Hompim Play (CV 3 Detik).</p>
        
        <p>Dengan senang hati kami menginformasikan bahwa Bapak/Ibu dinyatakan lolos dan kami ingin menawarkan kesempatan untuk bergabung bersama Hompim Play (CV 3 Detik) sebagai <strong>{{ $candidate->vacancy->title ?? '[Nama Posisi]' }}</strong>.</p>
        
        <p>Terlampir kami sampaikan Offering Letter untuk dapat Bapak/Ibu review.</p>

        @if($signatureLink)
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $signatureLink }}" target="_blank" style="background-color: #10b981; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">Tinjau &amp; Tanda Tangani Offering Letter</a>
        </div>

        @if($offeringPassword)
        <div style="background-color: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 6px; margin: 20px 0; padding: 16px; text-align: center;">
            <div style="color: #475569; font-size: 13px; margin-bottom: 6px;">PIN akses Offering Letter</div>
            <div style="color: #0f172a; font-size: 26px; font-weight: bold; letter-spacing: 6px;">{{ $offeringPassword }}</div>
            <div style="color: #64748b; font-size: 12px; margin-top: 8px;">Masukkan 6 digit angka ini saat membuka tautan.</div>
        </div>
        @endif
        @endif

        @php
            $deadline = $candidate->offering_letter_sent_at 
                ? \Carbon\Carbon::parse($candidate->offering_letter_sent_at)->addDays(3) 
                : \Carbon\Carbon::now()->addDays(3);
            $formattedDeadlineDate = $deadline->locale('id')->translatedFormat('l, d F Y');
            $formattedDeadlineTime = $deadline->format('H:i');
        @endphp

        <p>Mohon kesediaannya untuk memberikan konfirmasi atau tanggapan paling lambat pada <strong>{{ $formattedDeadlineDate }}</strong> pukul <strong>{{ $formattedDeadlineTime }} WIB</strong>.</p>
        
        <p>Apabila terdapat pertanyaan, jangan ragu untuk menghubungi kami.</p>
        
        <br>
        <p>Salam,</p>
        <p><strong>HRBP Team – Hompim Play</strong></p>
    </div>
</body>
</html>
