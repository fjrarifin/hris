<!DOCTYPE html>
<html>
<head>
    <title>Permintaan Referensi Kerja - Hompim Play</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #dddddd; border-radius: 5px;">
        <h2 style="color: #0f172a; border-bottom: 2px solid #3b82f6; padding-bottom: 10px;">Permintaan Referensi Kerja</h2>
        
        <p>Halo Bapak/Ibu <strong>{{ $candidate->name }}</strong>,</p>
        
        <p>Terima kasih telah mengikuti proses seleksi di Hompim Play (CV 3 Detik).</p>
        
        <p>Sebagai bagian dari tahapan rekrutmen, kami akan melakukan reference check untuk memperoleh gambaran mengenai pengalaman kerja dan kompetensi Bapak/Ibu.</p>
        
        <p>Mohon kesediaannya untuk mengisi data 3 orang referensi melalui tautan berikut:</p>
        
        <div style="text-align: center; margin: 25px 0;">
            <a href="{{ $referenceLink }}" target="_blank" style="background-color: #3b82f6; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">Isi Formulir Referensi Kerja</a>
        </div>

        @if($referencePassword)
        <div style="background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; margin: 20px 0; padding: 16px; text-align: center;">
            <div style="color: #475569; font-size: 13px; margin-bottom: 6px;">Password akses formulir</div>
            <div style="color: #0f172a; font-size: 26px; font-weight: bold; letter-spacing: 6px;">{{ $referencePassword }}</div>
            <div style="color: #64748b; font-size: 12px; margin-top: 8px;">Masukkan 6 digit angka ini saat membuka tautan.</div>
        </div>
        @endif
        
        <p>Referensi yang diminta terdiri dari:</p>
        <ol style="margin-left: 20px; padding-left: 0; margin-bottom: 20px;">
            <li>1 orang Atasan Langsung (Direct Report/Supervisor)</li>
            <li>1 orang Rekan Kerja (Peer)</li>
            <li>1 orang Bawahan (Subordinate) (jika pernah memiliki bawahan)</li>
        </ol>
        
        <p>Mohon pastikan nomor telepon dan informasi yang diberikan masih aktif serta bahwa pihak yang bersangkutan bersedia dihubungi.</p>
        
        <p>Kami juga mengharapkan agar referensi yang diberikan berasal dari masing-masing kategori yang berbeda (Atasan, Rekan Kerja, dan Bawahan), sehingga kami dapat memperoleh sudut pandang yang lebih komprehensif. Apabila Bapak/Ibu belum pernah memiliki bawahan, silakan menggantinya dengan referensi lain yang pernah bekerja sama secara profesional dengan Bapak/Ibu.</p>

        <p>Apabila belum pernah memiliki bawahan, silakan diganti dengan referensi lain yang pernah bekerja sama secara profesional dengan Bapak/Ibu.</p>
        
        <p>Terima kasih atas kerja samanya.</p>
        
        <br>
        <p>Salam,</p>
        <p><strong>HRBP Team – Hompim Play</strong></p>
    </div>
</body>
</html>
