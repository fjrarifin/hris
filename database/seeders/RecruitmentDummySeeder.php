<?php

namespace Database\Seeders;

use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentVacancy;
use Illuminate\Database\Seeder;

class RecruitmentDummySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Clear existing recruitment vacancies and candidates to avoid duplicate tests
        RecruitmentCandidate::query()->delete();
        RecruitmentVacancy::query()->delete();

        // 2. Create Dummy Vacancies
        $vacancyIT = RecruitmentVacancy::create([
            'title' => 'IT Programmer',
            'department' => 'IT',
            'unit' => 'Head Office',
            'description' => "Requirements:\n- Proficient in PHP & Laravel\n- Experienced with Vue.js/React.js\n- Minimum 2 years of experience",
            'status' => 'open',
        ]);

        $vacancyKasir = RecruitmentVacancy::create([
            'title' => 'SPV Kasir',
            'department' => 'Sales',
            'unit' => 'Toko Utama',
            'description' => "Requirements:\n- Experience as Cashier Leader\n- Good leadership skills\n- Friendly and communicative",
            'status' => 'open',
        ]);

        $vacancyHRD = RecruitmentVacancy::create([
            'title' => 'Staff HRD',
            'department' => 'HRD',
            'unit' => 'Head Office',
            'description' => "Requirements:\n- Bachelor degree in Psychology/Law\n- Understands Indonesian Labor Law\n- Experienced in recruitment processes",
            'status' => 'open',
        ]);

        $vacancyCS = RecruitmentVacancy::create([
            'title' => 'Customer Service',
            'department' => 'Sales',
            'unit' => 'Toko Utama',
            'description' => "Requirements:\n- Minimum High School graduate\n- Excellent communication skills\n- Able to work in shifts",
            'status' => 'open',
        ]);

        // 3. Create Dummy Candidates (Applied, Screening, Interview, Offered, Hired, Rejected)
        
        // Applied
        RecruitmentCandidate::create([
            'vacancy_id' => $vacancyIT->id,
            'name' => 'Fajar Nugraha',
            'email' => 'fajar.nugraha@example.com',
            'phone' => '081234567890',
            'status' => 'applied',
            'notes' => 'Fresh graduate with strong portfolio in Laravel projects.',
        ]);

        RecruitmentCandidate::create([
            'vacancy_id' => $vacancyKasir->id,
            'name' => 'Putri Amalia',
            'email' => 'putri.amalia@example.com',
            'phone' => '082345678901',
            'status' => 'applied',
            'notes' => 'Experienced as senior cashier at local retail store for 3 years.',
        ]);

        // Screening
        RecruitmentCandidate::create([
            'vacancy_id' => $vacancyIT->id,
            'name' => 'Budi Setiawan',
            'email' => 'budi.setiawan@example.com',
            'phone' => '083456789012',
            'status' => 'screening',
            'notes' => 'Good technical test result, pending administrative document check.',
        ]);

        RecruitmentCandidate::create([
            'vacancy_id' => $vacancyHRD->id,
            'name' => 'Rina Herawati',
            'email' => 'rina.herawati@example.com',
            'phone' => '084567890123',
            'status' => 'screening',
            'notes' => 'Strong background in employee training. Matches the department profile.',
        ]);

        // Interview
        RecruitmentCandidate::create([
            'vacancy_id' => $vacancyIT->id,
            'name' => 'Denny Hidayat',
            'email' => 'denny.hidayat@example.com',
            'phone' => '085678901234',
            'status' => 'interview',
            'notes' => 'Scheduled for technical interview with IT Lead on July 16, 2026.',
        ]);

        RecruitmentCandidate::create([
            'vacancy_id' => $vacancyCS->id,
            'name' => 'Anita Sari',
            'email' => 'anita.sari@example.com',
            'phone' => '086789012345',
            'status' => 'interview',
            'notes' => 'Very polite and fluent in communication. HR interview passed, user interview pending.',
        ]);

        // Offered
        RecruitmentCandidate::create([
            'vacancy_id' => $vacancyHRD->id,
            'name' => 'Hendra Wijaya',
            'email' => 'hendra.wijaya@example.com',
            'phone' => '087890123456',
            'status' => 'offered',
            'notes' => 'Offering letter sent. Waiting for candidate confirmation.',
        ]);

        // Hired
        RecruitmentCandidate::create([
            'vacancy_id' => $vacancyCS->id,
            'name' => 'Reza Pratama',
            'email' => 'reza.pratama@example.com',
            'phone' => '088901234567',
            'status' => 'hired',
            'notes' => 'Onboarding scheduled for August 1, 2026.',
        ]);

        // Rejected
        RecruitmentCandidate::create([
            'vacancy_id' => $vacancyKasir->id,
            'name' => 'Eko Prasetyo',
            'email' => 'eko.prasetyo@example.com',
            'phone' => '089012345678',
            'status' => 'rejected',
            'notes' => 'Did not attend the scheduled interview session without notice.',
        ]);
    }
}
