<?php

namespace App\Http\Controllers;

use App\Models\RecruitmentShortUrl;
use Illuminate\Http\RedirectResponse;

class RecruitmentShortUrlController extends Controller
{
    /**
     * Redirect a short URL code to its destination.
     *
     * @param string $code
     * @return RedirectResponse
     */
    public function redirect(string $code)
    {
        $shortUrl = RecruitmentShortUrl::where('code', $code)->first();

        if (! $shortUrl) {
            abort(404, 'Tautan tidak valid atau tidak ditemukan.');
        }

        if ($shortUrl->expires_at && $shortUrl->expires_at->isPast()) {
            abort(410, 'Tautan ini sudah kedaluwarsa.');
        }

        $shortUrl->increment('clicks_count');

        return redirect()->away($shortUrl->destination_url);
    }
}
