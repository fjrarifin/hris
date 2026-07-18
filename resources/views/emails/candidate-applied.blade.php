<!DOCTYPE html>
<html>
<head>
    <title>Lamaran Terkirim - Hompim Play</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #dddddd; border-radius: 5px;">
        <h2 style="color: #0f172a; border-bottom: 2px solid #3b82f6; padding-bottom: 10px;">Lamaran Diterima</h2>
        
        <p>Halo Bapak/Ibu <strong>{{ $candidate->name }}</strong>,</p>
        
        <p>Terima kasih telah mengirimkan lamaran untuk posisi <strong>{{ $candidate->vacancy->title ?? '[Nama Posisi]' }}</strong> di Hompim Play (CV 3 Detik).</p>
        
        <p>Lamaran Bapak/Ibu telah kami terima dengan baik dan saat ini sedang dalam proses review oleh tim terkait. Apabila kualifikasi Bapak/Ibu sesuai dengan kebutuhan kami, kami akan menghubungi Bapak/Ibu untuk tahapan seleksi berikutnya.</p>
        
        <br>
        <p>Salam,</p>
        <p><strong>HRBP Team – Hompim Play</strong></p>
    </div>
</body>
</html>
