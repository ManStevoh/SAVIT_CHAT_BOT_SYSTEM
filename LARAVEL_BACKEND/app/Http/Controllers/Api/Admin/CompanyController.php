<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\WhatsAppAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    private function companyToArray(Company $c): array
    {
        $c->loadCount(['chats', 'orders']);
        $wa = WhatsAppAccount::where('company_id', $c->id)
            ->where('status', 'active')
            ->first();

        return [
            'id' => (string) $c->id,
            'name' => (string) ($c->name ?? ''),
            'email' => (string) ($c->email ?? ''),
            'phone' => $c->phone ?? '',
            'logo' => $c->logo ? Storage::url($c->logo) : null,
            'plan' => $c->plan ?? 'starter',
            'status' => $c->status ?? 'pending',
            'totalChats' => (int) ($c->chats_count ?? 0),
            'totalOrders' => (int) ($c->orders_count ?? 0),
            'createdAt' => $c->created_at?->format('Y-m-d') ?? '',
            'industry' => $c->industry ?? 'other',
            'isGrowthPilot' => (bool) $c->growth_pilot_at,
            'growthDemoMode' => (bool) $c->growth_demo_mode,
            'growthPilotSince' => $c->growth_pilot_at?->toIso8601String(),
            'whatsappConnected' => (bool) $wa,
            'whatsappDisplayPhone' => $wa?->display_phone_number,
            'whatsappOnboardingStatus' => $wa?->onboarding_status,
            'billingModel' => $c->billing_model,
            'commissionRate' => $c->commission_rate !== null ? (float) $c->commission_rate : null,
            'commissionBasis' => $c->commission_basis ?? 'total',
            'waiveSubscriptionFee' => (bool) ($c->waive_subscription_fee ?? true),
            'commissionInvoiceThreshold' => $c->commission_invoice_threshold !== null ? (float) $c->commission_invoice_threshold : null,
            'commissionBalanceDue' => (float) ($c->commission_balance_due ?? 0.0),
        ];
    }

    public function show(Company $company): JsonResponse
    {
        return response()->json($this->companyToArray($company));
    }

    public function index(Request $request): JsonResponse
    {
        $query = Company::withCount(['chats', 'orders']);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('plan') && $request->plan !== 'all') {
            $query->where('plan', $request->plan);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $companies = $query->orderByDesc('created_at')->get();
        $data = $companies->map(fn (Company $c) => $this->companyToArray($c));

        return response()->json($data->values()->all());
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'nullable|string|max:50',
            'plan' => 'sometimes|in:free,starter,growth,professional,business,enterprise',
            'status' => 'sometimes|in:active,suspended,pending',
            'industry' => 'sometimes|nullable|string|in:retail,restaurant,services,other',
            'isGrowthPilot' => 'sometimes|boolean',
            'growthDemoMode' => 'sometimes|boolean',
            'billingModel' => 'sometimes|nullable|string|in:subscription,commission,hybrid',
            'commissionRate' => 'sometimes|nullable|numeric|min:0|max:100',
            'commissionBasis' => 'sometimes|nullable|string|in:total,subtotal',
            'waiveSubscriptionFee' => 'sometimes|boolean',
            'commissionInvoiceThreshold' => 'sometimes|nullable|numeric|min:0',
        ]);

        if (array_key_exists('isGrowthPilot', $validated)) {
            $company->growth_pilot_at = $validated['isGrowthPilot'] ? ($company->growth_pilot_at ?? now()) : null;
            unset($validated['isGrowthPilot']);
        }
        if (array_key_exists('growthDemoMode', $validated)) {
            $company->growth_demo_mode = (bool) $validated['growthDemoMode'];
            unset($validated['growthDemoMode']);
        }
        if (array_key_exists('billingModel', $validated)) {
            $company->billing_model = $validated['billingModel'] ?: null;
            unset($validated['billingModel']);
        }
        if (array_key_exists('commissionRate', $validated)) {
            $company->commission_rate = $validated['commissionRate'] !== null ? round((float) $validated['commissionRate'], 2) : null;
            unset($validated['commissionRate']);
        }
        if (array_key_exists('commissionBasis', $validated)) {
            $company->commission_basis = $validated['commissionBasis'] ?: 'total';
            unset($validated['commissionBasis']);
        }
        if (array_key_exists('waiveSubscriptionFee', $validated)) {
            $company->waive_subscription_fee = (bool) $validated['waiveSubscriptionFee'];
            unset($validated['waiveSubscriptionFee']);
        }
        if (array_key_exists('commissionInvoiceThreshold', $validated)) {
            $company->commission_invoice_threshold = $validated['commissionInvoiceThreshold'] !== null ? round((float) $validated['commissionInvoiceThreshold'], 2) : null;
            unset($validated['commissionInvoiceThreshold']);
        }

        $company->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Company updated successfully',
            'company' => $this->companyToArray($company->fresh()),
        ]);
    }

    public function updateStatus(Request $request, Company $company): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:active,suspended,pending',
        ]);

        $company->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Company status updated successfully',
        ]);
    }
}
