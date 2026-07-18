<!DOCTYPE html>
<html>
<head>
    <title>Notifikasi Karyawan Baru bergabung</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #dddddd; border-radius: 5px;">
        <h2 style="color: #0f172a; border-bottom: 2px solid #3b82f6; padding-bottom: 10px;">Notifikasi Onboarding Selesai</h2>
        
        <p>Halo <strong>Tim HRD</strong>,</p>
        
        <p>Ada karyawan baru yang telah menyelesaikan pengisian biodata onboarding secara mandiri:</p>
        
        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
            <tr>
                <td style="padding: 8px; font-weight: bold; width: 150px; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">Nama Karyawan</td>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">{{ $employee->nama_karyawan }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; font-weight: bold; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">Posisi / Jabatan</td>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">{{ $employee->jabatan }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; font-weight: bold; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">Email</td>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">{{ $employee->email }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; font-weight: bold; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">No. HP</td>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">{{ $employee->no_hp }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; font-weight: bold; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">NIK Sementara</td>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">{{ $employee->nik }}</td>
            </tr>
        </table>
        
        <p style="color: #dc2626; font-weight: bold;">*PENTING: Harap segera masuk ke portal HRIS internal untuk memberikan NIK resmi dan PIN kepada karyawan baru di atas agar yang bersangkutan dapat mengakses sistem operasional.</p>
        
        <p>Terima kasih.</p>
        
        <br>
        <p>Salam hangat,</p>
        <p><strong>Sistem Otomasi HRIS</strong></p>
    </div>
</body>
</html>
