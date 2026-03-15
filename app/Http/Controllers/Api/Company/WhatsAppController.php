<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    public function connect(Request $request): JsonResponse
    {
        $request->validate(['phoneNumber' => 'required|string|max:50']);

        // Placeholder: integrate with WhatsApp Business API or a service like Twilio
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=whatsapp-' . urlencode($request->phoneNumber);

        return response()->json([
            'success' => true,
            'qrCode' => $qrCodeUrl,
            'message' => 'Scan the QR code with WhatsApp to connect',
        ]);
    }
}
