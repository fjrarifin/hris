<!DOCTYPE html>
<html>
<head>
    <title>Undangan Wawancara User - Hompim Play</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #dddddd; border-radius: 5px;">
        <h2 style="color: #0f172a; border-bottom: 2px solid #3b82f6; padding-bottom: 10px;">Undangan Wawancara Rekrutmen</h2>
        
        <p>Halo Bapak/Ibu <strong>{{ $candidate->name }}</strong>,</p>
        
        @if($userInterview->round == 2)
            <p>Terima kasih telah mengikuti proses seleksi di Hompim Play (CV 3 Detik).</p>
            <p>Dengan senang hati kami menginformasikan bahwa Bapak/Ibu lolos ke tahap selanjutnya, yaitu <strong>User Interview II</strong>.</p>
        @else
            <p>Terima kasih telah mengikuti proses HR Interview di Hompim Play (CV 3 Detik).</p>
            <p>Dengan senang hati kami menginformasikan bahwa Bapak/Ibu lolos ke tahap selanjutnya, yaitu <strong>User Interview</strong>.</p>
        @endif
        
        <p>Berikut detail jadwal interview:</p>
        
        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
             <tr>
                <td style="padding: 8px; font-weight: bold; width: 150px; background-color: #f8fafc; border: 1px solid #e2e8f0;">Metode</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ ucfirst($type) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; font-weight: bold; background-color: #f8fafc; border: 1px solid #e2e8f0;">Hari/Tanggal</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ $formattedDate }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; font-weight: bold; background-color: #f8fafc; border: 1px solid #e2e8f0;">Waktu</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ $time }} WIB</td>
            </tr>
            @if($type === 'online')
                @if($meetLink)
                <tr>
                    <td style="padding: 8px; font-weight: bold; background-color: #f8fafc; border: 1px solid #e2e8f0;">Tautan (Link Meet)</td>
                    <td style="padding: 8px; border: 1px solid #e2e8f0;"><a href="{{ $meetLink }}" target="_blank" style="color: #3b82f6; text-decoration: underline;">Klik di sini untuk bergabung</a></td>
                </tr>
                @endif
            @else
            <tr>
                <td style="padding: 8px; font-weight: bold; background-color: #f8fafc; border: 1px solid #e2e8f0;">Lokasi</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ $location }}</td>
            </tr>
            @endif
        </table>
        
        <p>Mohon konfirmasi kesediaan Bapak/Ibu untuk mengikuti interview sesuai jadwal tersebut. Apabila berhalangan hadir, silakan menginformasikan ketersediaan waktu lainnya agar kami dapat menyesuaikan jadwal.</p>
        
        @if($whatsappLink)
            <p>Silakan konfirmasi kehadiran Anda dengan membalas email ini, atau menghubungi PIC kami langsung melalui WhatsApp di <a href="{{ $whatsappLink }}" target="_blank" style="color: #3b82f6; font-weight: bold; text-decoration: underline;">Tautan WhatsApp PIC Screening</a>.</p>
        @else
            <p>Silakan konfirmasi kehadiran Anda dengan membalas email ini.</p>
        @endif
        
        <p>Terima kasih, kami tunggu konfirmasinya.</p>
        
        <div style="margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 15px; font-size: 13px; color: #475569;">
            <p style="margin: 0 0 5px 0; font-weight: bold;">Catatan:</p>
            @if($type === 'online')
                <p style="margin: 0;"><strong>Interview online:</strong> Link meeting akan kami kirimkan setelah Bapak/Ibu mengonfirmasi kehadiran (atau Anda dapat menggunakan tautan di atas jika sudah tersedia).</p>
            @else
                <p style="margin: 0;"><strong>Interview offline:</strong> Lokasi: Hompim Play, {{ $location ?? '[alamat/lokasi]' }}. Mohon hadir 10–15 menit sebelum jadwal interview dimulai.</p>
            @endif
        </div>
        
        <br>
        <p>Salam,</p>
        <p><strong>HRBP Team – Hompim Play</strong></p>
    </div>
</body>
</html>
