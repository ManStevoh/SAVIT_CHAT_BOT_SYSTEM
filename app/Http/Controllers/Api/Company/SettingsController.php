<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->company;
        if (!$company) {
            return response()->json(['message' => 'No company.'], 403);
        }
        $settings = $company->settings()->first();
        return response()->json([
            'companyName' => $company->name,
            'email' => $company->email,
            'phone' => $company->phone,
            'address' => $company->address ?? '',
            'logo' => $company->logo ? asset('storage/' . $company->logo) : null,
            'whatsappNumber' => $settings?->whatsapp_number,
            'aiGreeting' => $settings?->ai_greeting,
            'aiTone' => $settings?->ai_tone,
            'fallbackMessage' => $settings?->fallback_message,
            'awayMessage' => $settings?->away_message,
            'timezone' => $settings?->timezone ?? 'UTC',
            'workingHours' => $settings?->working_hours,
            'autoReplyEnabled' => (bool) ($settings?->auto_reply_enabled ?? false),
            'notificationsEnabled' => (bool) ($settings?->notifications_enabled ?? false),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->company;
        if (!$company) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        if ($request->hasFile('logo')) {
            $request->validate(['logo' => 'image|max:2048']);
            if ($company->logo) {
                Storage::disk('public')->delete($company->logo);
            }
            $company->logo = $request->file('logo')->store('logos/' . $company->id, 'public');
            $company->save();
        }

        $companyValidated = $request->validate([
            'companyName' => 'sometimes|string|max:255',
            'email' => 'sometimes|email',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'whatsappNumber' => 'nullable|string|max:50',
            'aiGreeting' => 'nullable|string',
            'aiTone' => 'nullable|string|max:255',
            'fallbackMessage' => 'nullable|string',
            'awayMessage' => 'nullable|string',
            'timezone' => 'nullable|string|max:50',
            'workingHours' => 'nullable|array',
            'workingHours.*' => 'nullable|string|max:50',
            'autoReplyEnabled' => 'sometimes|boolean',
            'notificationsEnabled' => 'sometimes|boolean',
        ]);

        if (isset($companyValidated['companyName'])) {
            $company->update(['name' => $companyValidated['companyName']]);
        }
        if (isset($companyValidated['email'])) {
            $company->update(['email' => $companyValidated['email']]);
        }
        if (array_key_exists('phone', $companyValidated)) {
            $company->update(['phone' => $companyValidated['phone']]);
        }
        if (array_key_exists('address', $companyValidated)) {
            $company->update(['address' => $companyValidated['address']]);
        }

        $settings = $company->settings()->firstOrNew([]);
        $settings->company_id = $company->id;
        if (array_key_exists('whatsappNumber', $companyValidated)) {
            $settings->whatsapp_number = $companyValidated['whatsappNumber'];
        }
        if (array_key_exists('aiGreeting', $companyValidated)) {
            $settings->ai_greeting = $companyValidated['aiGreeting'];
        }
        if (array_key_exists('aiTone', $companyValidated)) {
            $settings->ai_tone = $companyValidated['aiTone'];
        }
        if (array_key_exists('fallbackMessage', $companyValidated)) {
            $settings->fallback_message = $companyValidated['fallbackMessage'];
        }
        if (array_key_exists('awayMessage', $companyValidated)) {
            $settings->away_message = $companyValidated['awayMessage'];
        }
        if (array_key_exists('timezone', $companyValidated)) {
            $settings->timezone = $companyValidated['timezone'] ?? 'UTC';
        }
        if (array_key_exists('workingHours', $companyValidated)) {
            $settings->working_hours = $companyValidated['workingHours'];
        }
        if (array_key_exists('autoReplyEnabled', $companyValidated)) {
            $settings->auto_reply_enabled = $companyValidated['autoReplyEnabled'];
        }
        if (array_key_exists('notificationsEnabled', $companyValidated)) {
            $settings->notifications_enabled = $companyValidated['notificationsEnabled'];
        }
        $settings->save();

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
        ]);
    }
}
