<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Merchant;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PaymentDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Merchant::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'merchant_id' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:pending,succeeded,failed,cancelled,expired'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $ownedPayments = Payment::query()
            ->whereHas('merchant', fn (Builder $query): Builder => $query->where('user_id', $request->user()->id));
        $summary = [
            'total' => (clone $ownedPayments)->count(),
            'succeeded' => (clone $ownedPayments)->where('status', Payment::STATUS_SUCCEEDED)->count(),
            'volume_minor' => (int) (clone $ownedPayments)->where('status', Payment::STATUS_SUCCEEDED)->sum('amount_minor'),
        ];
        $paymentsQuery = (clone $ownedPayments)->with('merchant');

        if (! empty($validated['merchant_id'])) {
            $paymentsQuery->whereHas(
                'merchant',
                fn (Builder $query): Builder => $query->where('code', $validated['merchant_id']),
            );
        }

        if (! empty($validated['status'])) {
            $paymentsQuery->where('status', $validated['status']);
        }

        if (! empty($validated['search'])) {
            $search = '%'.$validated['search'].'%';
            $paymentsQuery->where(function (Builder $query) use ($search): void {
                $query->where('public_id', 'like', $search)
                    ->orWhere('merchant_reference', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhereHas('merchant', fn (Builder $merchantQuery): Builder => $merchantQuery->where('name', 'like', $search));
            });
        }

        $payments = $paymentsQuery
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return response()->json([
            'payments' => PaymentResource::collection($payments->getCollection())->resolve($request),
            'merchants' => $request->user()->merchants()
                ->orderBy('name')
                ->get(['code', 'name'])
                ->map(fn (Merchant $merchant): array => [
                    'merchant_id' => $merchant->code,
                    'name' => $merchant->name,
                ]),
            'summary' => $summary,
            'meta' => [
                'current_page' => $payments->currentPage(),
                'from' => $payments->firstItem(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'to' => $payments->lastItem(),
                'total' => $payments->total(),
            ],
        ]);
    }
}
