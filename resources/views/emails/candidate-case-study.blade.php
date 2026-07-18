<!DOCTYPE html>
<html>
<head>
    <title>Instruksi Case Study - Hompim Play</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #dddddd; border-radius: 5px;">
        <h2 style="color: #0f172a; border-bottom: 2px solid #3b82f6; padding-bottom: 10px;">Instruksi Case Study Rekrutmen</h2>
        
        <p>Halo Bapak/Ibu <strong>{{ $candidate->name }}</strong>,</p>
        
        <p>Terima kasih telah mengikuti proses HR Interview di Hompim Play (CV 3 Detik).</p>
        
        <p>Dengan senang hati kami menginformasikan bahwa Bapak/Ibu lolos ke tahap selanjutnya, yaitu <strong>Case Study</strong>.</p>
        
        <p>Silakan mengerjakan case study sesuai dengan instruksi pada dokumen yang kami lampirkan.</p>
        
        <div style="background-color: #f8fafc; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #e2e8f0;">
            <p style="margin: 0 0 5px 0; font-weight: bold;">Detail Case Study:</p>
            <p style="margin: 0 0 5px 0;">Format: Presentasi</p>
            <p style="margin: 0;">Deadline: 3 hari sejak pesan ini dikirimkan.</p>
        </div>

        @if($caseStudyLink || $documentName)
        <div style="background-color: #f8fafc; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #e2e8f0;">
            @if($caseStudyLink && $documentName)
                <p style="margin: 0 0 5px 0;"><strong>Tautan Dokumen / Soal:</strong></p>
                <p style="margin: 0 0 10px 0;"><a href="{{ $caseStudyLink }}" target="_blank" style="color: #3b82f6; font-weight: bold; text-decoration: underline;">Klik di sini untuk membuka Soal Case Study (Tautan)</a></p>
                <p style="margin: 5px 0 0 0; font-size: 13px; color: #64748b; font-style: italic;">*Catatan: Dokumen soal tambahan terlampir pada email ini.</p>
            @elseif($caseStudyLink)
                <p style="margin: 0 0 5px 0;"><strong>Tautan Dokumen / Soal:</strong></p>
                <p style="margin: 0;"><a href="{{ $caseStudyLink }}" target="_blank" style="color: #3b82f6; font-weight: bold; text-decoration: underline;">Klik di sini untuk membuka Soal Case Study</a></p>
            @elseif($documentName)
                <p style="margin: 0; font-size: 14px; color: #334155;"><strong>Dokumen Soal:</strong> Dokumen soal studi kasus terlampir pada email ini.</p>
            @endif
        </div>
        @endif

        @if($uploadLink)
        <div style="background-color: #fffbeb; padding: 15px; border-left: 4px solid #f59e0b; border-radius: 4px; margin: 20px 0;">
            <p style="margin: 0 0 10px 0; font-weight: bold; color: #b45309;">Halaman Pengumpulan Jawaban Case Study:</p>
            <p style="margin: 0 0 8px 0; font-size: 14px;">Untuk mengumpulkan jawaban/penyelesaian, silakan kunjungi tautan berikut:</p>
            <p style="margin: 0 0 10px 0;"><a href="{{ $uploadLink }}" target="_blank" style="color: #d97706; font-weight: bold; text-decoration: underline;">Tautan Unggah Jawaban Case Study</a></p>
            @if($pin)
            <p style="margin: 0 0 8px 0; font-size: 14px;">Gunakan PIN berikut untuk mengakses halaman tersebut: <strong style="font-size: 16px; background-color: #fef3c7; padding: 2px 6px; border-radius: 4px; border: 1px solid #fde68a; letter-spacing: 1px;">{{ $pin }}</strong></p>
            @endif
            <p style="margin: 10px 0 0 0; font-size: 12px; color: #b45309; font-weight: bold;">
                ⚠️ PENTING: Mohon untuk tidak membuka halaman unggah case study tersebut jika Anda belum benar-benar selesai mengerjakan case study Anda.
            </p>
        </div>
        @endif
        
        <p>Case study yang telah disiapkan nantinya akan dipresentasikan pada tahap User Interview sebagai bagian dari proses evaluasi.</p>
        
        <p>Mohon mengirimkan hasil case study sebelum batas waktu yang telah ditentukan. Apabila terdapat pertanyaan, jangan ragu untuk menghubungi kami.</p>
        
        <br>
        <p>Salam,</p>
        <p><strong>HRBP Team – Hompim Play</strong></p>
    </div>
</body>
</html>
