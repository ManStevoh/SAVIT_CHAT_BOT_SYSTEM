<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    private function companyToArray(Company $c): array
    {
        $c->loadCount(['chats', 'orders']);
        return [
            'id' => (string) $c->id,
            'name' => $c->name,
            'email' => $c->email,
            'phone' => $c->phone ?? '',
            'logo' => $c->logo ? Storage::url($c->logo) : null,
            'plan' => $c->plan ?? 'starter',
            'status' => $c->status,
            'totalChats' => (int) $c->chats_count,
            'totalOrders' => (int) $c->orders_count,
            'createdAt' => $c->created_at->format('Y-m-d'),
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

        $companies = $query->orderBy('name')->get();
        $data = $companies->map(fn (Company $c) => $this->companyToArray($c));

        return response()->json($data->values()->all());
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'nullable|string|max:50',
            'plan' => 'sometimes|in:starter,professional,enterprise',
            'status' => 'sometimes|in:active,suspended,pending',
        ]);

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
