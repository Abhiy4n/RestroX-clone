<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMerchantRequest;
use App\Http\Requests\UpdateMerchantRequest;
use App\Http\Resources\MerchantResource;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MerchantController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Merchant::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,disabled'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $ownedMerchants = $request->user()->merchants();
        $summary = [
            'total' => (clone $ownedMerchants)->count(),
            'active' => (clone $ownedMerchants)->where('status', Merchant::STATUS_ACTIVE)->count(),
            'disabled' => (clone $ownedMerchants)->where('status', Merchant::STATUS_DISABLED)->count(),
        ];
        $merchantsQuery = $request->user()->merchants();

        if (! empty($validated['search'])) {
            $search = '%'.$validated['search'].'%';
            $merchantsQuery->where(function ($query) use ($search): void {
                $query->where('name', 'like', $search)
                    ->orWhere('code', 'like', $search)
                    ->orWhere('website_url', 'like', $search);
            });
        }

        if (! empty($validated['status'])) {
            $merchantsQuery->where('status', $validated['status']);
        }

        $merchants = $merchantsQuery
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return response()->json([
            'merchants' => MerchantResource::collection($merchants->getCollection())->resolve($request),
            'summary' => $summary,
            'meta' => [
                'current_page' => $merchants->currentPage(),
                'from' => $merchants->firstItem(),
                'last_page' => $merchants->lastPage(),
                'per_page' => $merchants->perPage(),
                'to' => $merchants->lastItem(),
                'total' => $merchants->total(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMerchantRequest $request): JsonResponse
    {
        $merchant = $request->user()->merchants()->create($request->validated());

        return response()->json([
            'merchant' => (new MerchantResource($merchant))->resolve($request),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Merchant $merchant): JsonResponse
    {
        Gate::authorize('view', $merchant);

        return response()->json([
            'merchant' => (new MerchantResource($merchant))->resolve($request),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMerchantRequest $request, Merchant $merchant): JsonResponse
    {
        $merchant->update($request->validated());

        return response()->json([
            'merchant' => (new MerchantResource($merchant->refresh()))->resolve($request),
        ]);
    }
}
