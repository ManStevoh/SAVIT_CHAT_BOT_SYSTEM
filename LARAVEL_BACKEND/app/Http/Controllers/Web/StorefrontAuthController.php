<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\StorefrontCustomer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StorefrontAuthController extends Controller
{
    public function register(Request $request, string $slug): RedirectResponse|JsonResponse
    {
        $company = Company::where('store_slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $email = strtolower(trim($validated['email']));

        // Check if an account already exists for this company
        $existing = StorefrontCustomer::where('company_id', $company->id)
            ->where('email', $email)
            ->first();

        if ($existing && ! empty($existing->password)) {
            throw ValidationException::withMessages([
                'email' => ['An account with this email address already exists. Please log in.'],
            ]);
        }

        if ($existing) {
            $existing->update([
                'name' => trim($validated['name']),
                'password' => Hash::make($validated['password']),
            ]);
            $customer = $existing;
        } else {
            $customer = StorefrontCustomer::create([
                'company_id' => $company->id,
                'email' => $email,
                'name' => trim($validated['name']),
                'password' => Hash::make($validated['password']),
            ]);
        }

        // Store customer session for this store
        $sessionKey = 'storefront_customer_id_'.$company->id;
        session([$sessionKey => $customer->id]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                ],
            ]);
        }

        return back()->with('status', 'Account created successfully!');
    }

    public function login(Request $request, string $slug): RedirectResponse|JsonResponse
    {
        $company = Company::where('store_slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower(trim($validated['email']));

        $customer = StorefrontCustomer::where('company_id', $company->id)
            ->where('email', $email)
            ->first();

        if (! $customer || empty($customer->password) || ! Hash::check($validated['password'], $customer->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email address or password.'],
            ]);
        }

        $sessionKey = 'storefront_customer_id_'.$company->id;
        session([$sessionKey => $customer->id]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                ],
            ]);
        }

        return back()->with('status', 'Logged in successfully!');
    }

    public function logout(Request $request, string $slug): RedirectResponse|JsonResponse
    {
        $company = Company::where('store_slug', $slug)->firstOrFail();

        $sessionKey = 'storefront_customer_id_'.$company->id;
        session()->forget($sessionKey);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('status', 'Logged out.');
    }

    public static function getAuthenticatedCustomer(Company $company): ?StorefrontCustomer
    {
        $sessionKey = 'storefront_customer_id_'.$company->id;
        $customerId = session($sessionKey);

        if (! $customerId) {
            return null;
        }

        return StorefrontCustomer::where('company_id', $company->id)
            ->where('id', $customerId)
            ->first();
    }
}
