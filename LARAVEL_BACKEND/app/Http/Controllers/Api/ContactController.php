<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required|string|max:5000',
        ]);

        $settings = PlatformSetting::first();
        $to = $settings?->support_email ?? config('mail.from.address');

        if ($to) {
            $body = "Contact form submission\n\n"
                . "Name: {$validated['name']}\n"
                . "Email: {$validated['email']}\n\n"
                . "Message:\n{$validated['message']}";

            try {
                $html = '<p>' . nl2br(e($body)) . '</p>';
                (new MailService)->send($to, 'Contact form: ' . $validated['name'], $html, $body);
            } catch (\Throwable) {
                // Still accept the submission if mail fails in dev
            }
        }

        return response()->json(['success' => true]);
    }
}
