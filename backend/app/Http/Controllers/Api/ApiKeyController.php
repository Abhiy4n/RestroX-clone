<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMerchantApiKeyRequest;
use App\Http\Resources\MerchantApiKeyResource;
use App\Models\Merchant;
use App\Models\MerchantApiKey;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ApiKeyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Merchant::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'merchant_id' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,expired,revoked'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $ownedKeys = MerchantApiKey::query()
            ->whereHas('merchant', fn (Builder $query): Builder => $query->where('user_id', $request->user()->id));
        $now = now();
        $summary = [
            'total' => (clone $ownedKeys)->count(),
            'active' => (clone $ownedKeys)
                ->whereNull('revoked_at')
                ->where(fn (Builder $query): Builder => $query->whereNull('expires_at')->orWhere('expires_at', '>', $now))
                ->count(),
            'inactive' => (clone $ownedKeys)
                ->where(fn (Builder $query): Builder => $query
                    ->whereNotNull('revoked_at')
                    ->orWhere(fn (Builder $query): Builder => $query->whereNull('revoked_at')->where('expires_at', '<=', $now)))
                ->count(),
        ];
        $apiKeysQuery = (clone $ownedKeys)->with('merchant');

        if (! empty($validated['merchant_id'])) {
            $apiKeysQuery->whereHas(
                'merchant',
                fn (Builder $query): Builder => $query->where('code', $validated['merchant_id']),
            );
        }

        if (! empty($validated['search'])) {
            $search = '%'.$validated['search'].'%';
            $apiKeysQuery->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', $search)
                    ->orWhere('key_id', 'like', $search)
                    ->orWhere('secret_hint', 'like', $search)
                    ->orWhereHas('merchant', fn (Builder $merchantQuery): Builder => $merchantQuery->where('name', 'like', $search));
            });
        }

        if (! empty($validated['status'])) {
            $this->applyStatusFilter($apiKeysQuery, $validated['status'], $now);
        }

        $apiKeys = $apiKeysQuery
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return response()->json([
            'api_keys' => MerchantApiKeyResource::collection($apiKeys->getCollection())->resolve($request),
            'merchants' => $request->user()->merchants()
                ->orderBy('name')
                ->get(['code', 'name', 'status'])
                ->map(fn (Merchant $merchant): array => [
                    'merchant_id' => $merchant->code,
                    'name' => $merchant->name,
                    'status' => $merchant->status,
                ]),
            'summary' => $summary,
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

    public function store(StoreMerchantApiKeyRequest $request): JsonResponse
    {
        $merchant = $request->merchant();
        $issued = MerchantApiKey::issueFor(
            $merchant,
            $request->string('name')->toString(),
            $request->date('expires_at'),
        );
        $issued['key']->setRelation('merchant', $merchant);

        return response()->json([
            'api_key' => (new MerchantApiKeyResource($issued['key']))->resolve($request),
            'secret' => $issued['secret'],
        ], 201)->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
        ]);
    }

    private function applyStatusFilter(Builder $query, string $status, CarbonInterface $now): void
    {
        if ($status === 'revoked') {
            $query->whereNotNull('revoked_at');

            return;
        }

        $query->whereNull('revoked_at');

        if ($status === 'expired') {
            $query->where('expires_at', '<=', $now);

            return;
        }

        $query->where(fn (Builder $query): Builder => $query->whereNull('expires_at')->orWhere('expires_at', '>', $now));
    }
}
