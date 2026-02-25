<?php

namespace Topoff\MailManager\Http\Controllers;

use Illuminate\Routing\Controller;
use Topoff\MailManager\Services\SesSns\SesSnsSetupService;

class SesSnsSetupStatusController extends Controller
{
    public function __invoke(SesSnsSetupService $service)
    {
        return view('mail-manager::ses-sns-status', [
            'status' => $service->check(),
        ]);
    }
}

