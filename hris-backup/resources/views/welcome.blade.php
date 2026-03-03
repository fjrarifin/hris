@extends('layouts.app')

@section('content')
    <div class="grid md:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl shadow p-6 border">
            <h2 class="text-lg font-bold">Card 1</h2>
            <p class="text-sm text-gray-600 mt-2">Ini contoh layout Tailwind.</p>
        </div>

        <div class="bg-white rounded-2xl shadow p-6 border">
            <h2 class="text-lg font-bold">Card 2</h2>
            <p class="text-sm text-gray-600 mt-2">Grid sudah aktif ✅</p>
        </div>

        <div class="bg-white rounded-2xl shadow p-6 border">
            <h2 class="text-lg font-bold">Card 3</h2>
            <p class="text-sm text-gray-600 mt-2">Mantap lanjut bikin HRIS 🔥</p>
        </div>
    </div>
@endsection
