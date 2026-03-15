<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
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
        $data = $companies->map(fn (Company $c) => [
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
        ]);

        return response()->json($data->values()->all());
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
