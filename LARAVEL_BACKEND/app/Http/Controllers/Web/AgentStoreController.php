<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Deploy\DeployAuthService;
use App\Services\Store\AgentStoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AgentStoreController extends Controller
{
    public function __construct(
        private readonly DeployAuthService $authService,
        private readonly AgentStoreService $storeService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $key = (string) (
            $request->header('X-Deploy-Agent-Key')
            ?: $request->bearerToken()
            ?: $request->input('key')
            ?: ''
        );

        if (! $this->authService->validateAgentKey($key)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Invalid or missing agent deploy key.',
            ], 401);
        }

        $action = strtolower((string) ($request->input('action') ?: $request->query('action') ?: 'list_stores'));

        try {
            return match ($action) {
                'list_stores', 'stores' => $this->handleListStores($request),
                'list_products', 'products' => $this->handleListProducts($request),
                'add_product', 'create', 'add' => $this->handleAddProduct($request),
                'update_product', 'update' => $this->handleUpdateProduct($request),
                'update_store', 'store_settings', 'settings' => $this->handleUpdateStore($request),
                'assign_free_plan', 'free_plan' => $this->handleAssignFreePlan($request),
                'remove_product', 'delete', 'archive' => $this->handleDeleteProduct($request),
                'bulk_import', 'bulk' => $this->handleBulkImport($request),
                'clone_store', 'seed_account', 'clone' => $this->handleCloneStore($request),
                'list_memories', 'memories' => $this->handleListMemories($request),
                'clear_memories', 'delete_memories', 'purge_memories' => $this->handleClearMemories($request),
                'upload_image', 'upload' => $this->handleUploadImage($request),
                'verify_email', 'verify_user' => $this->handleVerifyEmail($request),
                default => response()->json([
                    'success' => false,
                    'message' => "Unknown action '{$action}'. Valid actions: list_stores, list_products, add_product, update_product, update_store, assign_free_plan, remove_product, bulk_import, clone_store, list_memories, clear_memories, upload_image, verify_email.",
                ], 400),
            };
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function handleListStores(Request $request): JsonResponse
    {
        $search = $request->input('search') ?: $request->query('search');
        $stores = $this->storeService->listStores($search);

        return response()->json([
            'success' => true,
            'count'   => count($stores),
            'stores'  => $stores,
        ]);
    }

    private function handleListProducts(Request $request): JsonResponse
    {
        $storeId = $request->input('company_id') ?: $request->input('store') ?: $request->query('store');
        $company = $this->storeService->resolveCompany($storeId);

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => "Store '{$storeId}' not found. Specify a valid company_id or store_slug.",
            ], 404);
        }

        $filters = [
            'status'   => $request->input('status') ?: $request->query('status'),
            'category' => $request->input('category') ?: $request->query('category'),
            'search'   => $request->input('search') ?: $request->query('search'),
            'limit'    => $request->input('limit') ?: $request->query('limit', 50),
        ];

        $products = $this->storeService->listProducts($company, $filters);

        return response()->json([
            'success'      => true,
            'company_id'   => $company->id,
            'company_name' => $company->name,
            'store_slug'   => $company->store_slug,
            'count'        => count($products),
            'products'     => $products,
        ]);
    }

    private function handleAddProduct(Request $request): JsonResponse
    {
        $storeId = $request->input('company_id') ?: $request->input('store');
        $company = $this->storeService->resolveCompany($storeId);

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => "Store '{$storeId}' not found. Specify a valid company_id or store_slug.",
            ], 404);
        }

        $data = (array) ($request->input('product') ?: $request->all());

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products/' . $company->id, 'public');
            $data['image'] = $path;
        } elseif ($request->hasFile('file')) {
            $path = $request->file('file')->store('products/' . $company->id, 'public');
            $data['image'] = $path;
        }

        $result = $this->storeService->createProduct($company, $data);

        return response()->json($result, 201);
    }

    private function handleUpdateProduct(Request $request): JsonResponse
    {
        $storeId = $request->input('company_id') ?: $request->input('store');
        $company = $this->storeService->resolveCompany($storeId);

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => "Store '{$storeId}' not found. Specify a valid company_id or store_slug.",
            ], 404);
        }

        $productId = $request->input('product_id') ?: $request->input('id') ?: $request->input('name');
        if (empty($productId)) {
            return response()->json([
                'success' => false,
                'message' => 'Product identifier (product_id or name) is required.',
            ], 400);
        }

        $data = (array) ($request->input('updates') ?: $request->input('product') ?: $request->all());

        $result = $this->storeService->updateProduct($company, $productId, $data);

        return response()->json($result);
    }

    private function handleDeleteProduct(Request $request): JsonResponse
    {
        $storeId = $request->input('company_id') ?: $request->input('store');
        $company = $this->storeService->resolveCompany($storeId);

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => "Store '{$storeId}' not found. Specify a valid company_id or store_slug.",
            ], 404);
        }

        $productId = $request->input('product_id') ?: $request->input('id') ?: $request->input('name');
        if (empty($productId)) {
            return response()->json([
                'success' => false,
                'message' => 'Product identifier (product_id or name) is required.',
            ], 400);
        }

        $force = (bool) ($request->input('force_delete') ?: $request->input('force') ?: false);

        $result = $this->storeService->deleteProduct($company, $productId, $force);

        return response()->json($result);
    }

    private function handleBulkImport(Request $request): JsonResponse
    {
        $storeId = $request->input('company_id') ?: $request->input('store');
        $company = $this->storeService->resolveCompany($storeId);

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => "Store '{$storeId}' not found. Specify a valid company_id or store_slug.",
            ], 404);
        }

        $items = (array) ($request->input('items') ?: $request->input('products') ?: []);

        $result = $this->storeService->bulkImport($company, $items);

        return response()->json($result, $result['success'] ? 200 : 207);
    }

    private function handleUpdateStore(Request $request): JsonResponse
    {
        $storeId = $request->input('company_id') ?: $request->input('store');
        $company = $this->storeService->resolveCompany($storeId);

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => "Store '{$storeId}' not found. Specify a valid company_id or store_slug.",
            ], 404);
        }

        $settings = (array) ($request->input('settings') ?: $request->all());

        $result = $this->storeService->updateStoreSettings($company, $settings);

        return response()->json($result);
    }

    private function handleAssignFreePlan(Request $request): JsonResponse
    {
        $storeId = $request->input('company_id') ?: $request->input('store');
        $company = $this->storeService->resolveCompany($storeId);

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => "Store '{$storeId}' not found. Specify a valid company_id or store_slug.",
            ], 404);
        }

        return response()->json($this->storeService->assignFreePlan($company));
    }

    private function handleListMemories(Request $request): JsonResponse
    {
        $storeId = $request->input('company_id') ?: $request->input('store') ?: $request->query('store');
        $company = $this->storeService->resolveCompany($storeId);

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => "Store '{$storeId}' not found. Specify a valid company_id or store_slug.",
            ], 404);
        }

        $phone = $request->input('phone') ?: $request->query('phone') ?: $request->input('customer_phone');
        $result = $this->storeService->listMemories($company, $phone ? (string) $phone : null);

        return response()->json($result);
    }

    private function handleClearMemories(Request $request): JsonResponse
    {
        $storeId = $request->input('company_id') ?: $request->input('store') ?: $request->query('store');
        $company = $this->storeService->resolveCompany($storeId);

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => "Store '{$storeId}' not found. Specify a valid company_id or store_slug.",
            ], 404);
        }

        $phone = $request->input('phone') ?: $request->query('phone') ?: $request->input('customer_phone');
        $key = $request->input('key') ?: $request->query('key') ?: $request->input('memory_key');
        $result = $this->storeService->clearMemories(
            $company,
            $phone ? (string) $phone : null,
            $key ? (string) $key : null
        );

        return response()->json($result);
    }

    private function handleCloneStore(Request $request): JsonResponse
    {
        $data = $request->all();
        $result = $this->storeService->cloneStore($data);

        return response()->json($result, 201);
    }

    private function handleUploadImage(Request $request): JsonResponse
    {
        $storeId = $request->input('company_id') ?: $request->input('store');
        $company = $this->storeService->resolveCompany($storeId);

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => "Store '{$storeId}' not found. Specify a valid company_id or store_slug.",
            ], 404);
        }

        $file = $request->file('image') ?: $request->file('file');
        if (! $file) {
            return response()->json([
                'success' => false,
                'message' => 'No image file provided in request.',
            ], 400);
        }

        $path = $file->store('products/' . $company->id, 'public');

        return response()->json([
            'success' => true,
            'company_id' => $company->id,
            'path' => $path,
            'url' => \Illuminate\Support\Facades\Storage::disk('public')->url($path),
        ]);
    }

    private function handleVerifyEmail(Request $request): JsonResponse
    {
        $email = trim((string) ($request->input('email') ?: 'admin@essem.local'));
        $user = \App\Models\User::where('email', $email)->first();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => "User '{$email}' not found.",
            ], 404);
        }

        $user->markEmailAsVerified();
        if ($request->boolean('make_admin')) {
            $user->role = 'admin';
        }
        $user->save();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            ],
            'message' => "User '{$email}' email marked as verified.",
        ]);
    }
}
