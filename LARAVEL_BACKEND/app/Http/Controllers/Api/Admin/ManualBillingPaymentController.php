<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingPayment;
use App\Services\PlatformPayments\ManualSubscriptionPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManualBillingPaymentController extends Controller
{
    public function __construct(
        protected ManualSubscriptionPaymentService $manualPayments,
    ) {}

    /**
     * GET /api/admin/manual-payments
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'payments' => $this->manualPayments->listPendingForAdmin(),
        ]);
    }

    /**
     * POST /api/admin/manual-payments/{id}/approve
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $payment = BillingPayment::where('gateway', 'manual')->findOrFail($id);
        $result = $this->manualPayments->approve($payment, $request->user()?->id);

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    /**
     * POST /api/admin/manual-payments/{id}/reject
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $payment = BillingPayment::where('gateway', 'manual')->findOrFail($id);
        $result = $this->manualPayments->reject($payment, $validated['reason'] ?? null, $request->user()?->id);

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    /**
     * GET /api/admin/manual-payments/{id}/proof
     */
    public function proof(int $id): BinaryFileResponse|JsonResponse
    {
        $payment = BillingPayment::where('gateway', 'manual')->findOrFail($id);
        $path = $this->manualPayments->proofAbsolutePath($payment);
        if (! $path) {
            return response()->json(['message' => 'No proof uploaded.'], 404);
        }

        $meta = is_array($payment->metadata) ? $payment->metadata : [];
        $name = (string) ($meta['proof_original_name'] ?? basename($path));

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="'.$name.'"',
        ]);
    }
}
