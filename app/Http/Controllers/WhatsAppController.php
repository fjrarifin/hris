<?php

namespace App\Http\Controllers;

use App\Http\Services\WhatsAppService;

class WhatsAppController extends Controller
{
    public function test()
    {
        $wa = new WhatsAppService();

        return $wa->sendMessage(
            '6282117289833',
            'Test manual'
        );
    }
}
