<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformSettingsController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platformName' => 'nullable|string|max:255',
            'supportEmail' => 'nullable|email',
            'maintenanceMode' => 'sometimes|boolean',
            'aiModel' => 'nullable|string|max:255',
            'maxTokensPerRequest' => 'nullable|integer|min:1',
            'rateLimitPerMinute' => 'nullable|integer|min:1',
        ]);

        $settings = PlatformSetting::firstOrNew([]);
        $settings->fill($validated);
        $settings->save();

        return response()->json([
            'success' => true,
            'message' => 'Platform settings updated successfully',
        ]);
    }
}
