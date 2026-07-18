<!DOCTYPE html>
<html>
<head>
    <title>Formulir Onboarding Karyawan Baru - Hompim Play</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #dddddd; border-radius: 5px;">
        <h2 style="color: #0f172a; border-bottom: 2px solid #10b981; padding-bottom: 10px;">Formulir Onboarding Karyawan Baru</h2>
        
        <p>Halo Bapak/Ibu <strong>{{ $candidate->name }}</strong>,</p>
        
        <p>Selamat! Kami dengan senang hati menyambut Bapak/Ibu sebagai bagian dari Hompim Play (CV 3 Detik).</p>
        
        <p>Sebagai bagian dari proses administrasi sebelum bergabung, mohon kesediaannya untuk melengkapi data diri melalui Biodata Form pada tautan berikut:</p>
        
        <div style="text-align: center; margin: 25px 0;">
            <a href="{{ $onboardingLink }}" target="_blank" style="background-color: #10b981; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">Lengkapi Biodata Onboarding</a>
        </div>

        <p>Untuk menjaga keamanan data Anda, tautan di atas dilindungi oleh kata sandi (password). Gunakan kata sandi 6-digit berikut ini untuk masuk:</p>
        
        <div style="background-color: #f1f5f9; padding: 15px; border-radius: 5px; text-align: center; font-size: 20px; font-weight: bold; letter-spacing: 3px; color: #0f172a; border: 1px dashed #cbd5e1; max-width: 200px; margin: 0 auto 20px auto;">
            {{ $password }}
        </div>
        
        <p>Mohon agar formulir tersebut diisi dengan lengkap dan benar untuk keperluan administrasi perusahaan.</p>
        
        <p>Apabila terdapat pertanyaan atau kendala dalam proses pengisian, silakan menghubungi kami.</p>
        
        <p>Selamat bergabung, dan kami nantikan kehadiran Bapak/Ibu di Hompim Play.</p>
        
        <br>
        <p>Salam,</p>
        <p><strong>HRBP Team – Hompim Play</strong></p>
    </div>
</body>
</html>
