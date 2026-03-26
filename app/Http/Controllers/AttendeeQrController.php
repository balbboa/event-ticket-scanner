<?php

namespace App\Http\Controllers;

use App\Models\Attendee;
use Illuminate\Http\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AttendeeQrController extends Controller
{
    public function show(Attendee $attendee): Response
    {
        $png = QrCode::format('png')
            ->size(300)
            ->margin(2)
            ->generate($attendee->ticket_code);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="' . $attendee->ticket_code . '.png"',
        ]);
    }
}
