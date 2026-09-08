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
            'acceptTerms' => ['accepted'],
            'marketingConsent' => ['sometimes', 'boolean'],
        ], [
            'acceptTerms.accepted' => 'Please agree to this store\'s terms and conditions.',
        ]);

        $marketingConsent = $request->boolean('marketingConsent');

        $email = strtolower(trim($validated['email']));

        // Check if an account already exists for this company
        $existing = StorefrontCustomer::where('company_id', $company->id)
            ->where('email', $email)
            ->first();

        if ($existing && ! empty($existing->password)) {
            throw ValidationException::withMessages([
                'email' => ['An account with this email address already exists. Please sign in or use Forgot Password.'],
            ]);
        }

        $consent = [
            'terms_accepted_at' => now(),
            'marketing_consent' => $marketingConsent,
            'marketing_consent_at' => $marketingConsent ? now() : null,
        ];

        if ($existing) {
            $existing->update(array_merge([
                'name' => trim($validated['name']),
                'password' => Hash::make($validated['password']),
            ], $consent));
            $customer = $existing;
        } else {
            $customer = StorefrontCustomer::create(array_merge([
                'company_id' => $company->id,
                'email' => $email,
                'name' => trim($validated['name']),
                'password' => Hash::make($validated['password']),
            ], $consent));
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

    /**
     * Check if a storefront customer account exists with a password set.
     * Used by checkout to gently prompt customers who have an account to log in.
     */
    public function checkEmail(Request $request, string $slug): JsonResponse
    {
        $company = Company::where('store_slug', $slug)->firstOrFail();
        $email = strtolower(trim((string) $request->input('email', '')));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['exists' => false, 'hasPassword' => false]);
        }

        $customer = StorefrontCustomer::where('company_id', $company->id)
            ->where('email', $email)
            ->first();

        return response()->json([
            'exists' => (bool) $customer,
            'hasPassword' => (bool) ($customer && ! empty($customer->password)),
            'name' => $customer?->name,
        ]);
    }

    /**
     * Send a secure signed password setup/reset link to the customer's email.
     */
    public function forgotPassword(Request $request, string $slug): JsonResponse|RedirectResponse
    {
        $company = Company::where('store_slug', $slug)->firstOrFail();
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $email = strtolower(trim($validated['email']));
        $customer = StorefrontCustomer::where('company_id', $company->id)
            ->where('email', $email)
            ->first();

        if ($customer) {
            $setupUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'storefront.account.set-password',
                now()->addDays(7),
                ['slug' => $company->store_slug, 'customer' => $customer->id]
            );

            try {
                app(\App\Services\MailService::class)->sendStorefrontCustomerPasswordSetupEmail(
                    $customer,
                    $company,
                    $setupUrl,
                    ! empty($customer->password)
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Storefront forgot-password email failed', [
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $message = 'If an account exists for this email, a password link has been sent.';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('status', $message);
    }

    /**
     * Show password setup page (validated via signed URL).
     */
    public function showSetPassword(Request $request, string $slug, StorefrontCustomer $customer): mixed
    {
        $company = Company::where('store_slug', $slug)->firstOrFail();

        if ($customer->company_id !== $company->id) {
            abort(404);
        }

        return \Inertia\Inertia::render('store/set-password', [
            'slug' => $slug,
            'company' => [
                'name' => $company->name,
                'storeSlug' => $company->store_slug,
                'theme' => is_array($company->storefront_theme) ? $company->storefront_theme : [],
            ],
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'hasExistingPassword' => ! empty($customer->password),
            ],
        ]);
    }

    /**
     * Set password from signed URL.
     */
    public function setPassword(Request $request, string $slug, StorefrontCustomer $customer): RedirectResponse|JsonResponse
    {
        $company = Company::where('store_slug', $slug)->firstOrFail();

        if ($customer->company_id !== $company->id) {
            abort(404);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $customer->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Auto log in after setting password
        $sessionKey = 'storefront_customer_id_'.$company->id;
        session([$sessionKey => $customer->id]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully!',
            ]);
        }

        return redirect()->to(url("/s/{$slug}"))->with('status', 'Your password has been set and you are now signed in!');
    }
}
