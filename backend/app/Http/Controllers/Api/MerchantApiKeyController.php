<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMerchantApiKeyRequest;
use App\Http\Resources\MerchantApiKeyResource;
use App\Models\Merchant;
use App\Models\MerchantApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MerchantApiKeyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, Merchant $merchant): JsonResponse
    {
        Gate::authorize('view', $merchant);

        $apiKeys = $merchant->apiKeys()
            ->with('merchant')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return response()->json([
            'api_keys' => MerchantApiKeyResource::collection($apiKeys->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $apiKeys->currentPage(),
                'from' => $apiKeys->firstItem(),
                'last_page' => $apiKeys->lastPage(),
                'per_page' => $apiKeys->perPage(),
                'to' => $apiKeys->lastItem(),
                'total' => $apiKeys->total(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMerchantApiKeyRequest $request, Merchant $merchant): JsonResponse
    {
        $issued = MerchantApiKey::issueFor(
            $request->merchant(),
            $request->string('name')->toString(),
            $request->date('expires_at'),
        );
        $issued['key']->setRelation('merchant', $request->merchant());

        return response()->json([
            'api_key' => (new MerchantApiKeyResource($issued['key']))->resolve($request),
            'secret' => $issued['secret'],
        ], 201)->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Merchant $merchant, MerchantApiKey $apiKey): JsonResponse
    {
        Gate::authorize('update', $merchant);

        if ($apiKey->revoked_at === null) {
            $apiKey->forceFill(['revoked_at' => now()])->save();
        }

        return response()->json(null, 204);
    }

    public function revoke(MerchantApiKey $apiKey): JsonResponse
    {
        $apiKey->loadMissing('merchant');
        Gate::authorize('update', $apiKey->merchant);

        if ($apiKey->revoked_at === null) {
            $apiKey->forceFill(['revoked_at' => now()])->save();
        }

        return response()->json([
            'api_key' => (new MerchantApiKeyResource($apiKey->refresh()->load('merchant')))->resolve(request()),
        ]);
    }
}
