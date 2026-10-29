<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Merchant;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->attributes->get('authenticated_merchant');
        $data = $request->safe()->except('idempotency_key');
        $keyHash = hash('sha256', $request->string('idempotency_key')->toString());
        $scope = 'merchant:'.$merchant->id.':payment:create';
        $publicId = 'pay_'.Str::lower((string) Str::ulid());
        $checkoutToken = $this->checkoutToken($publicId);

        [$payment, $created] = DB::transaction(function () use ($merchant, $data, $keyHash, $scope, $checkoutToken, $publicId): array {
            $now = now();

            DB::table('idempotency_keys')->insertOrIgnore([
                'scope' => $scope,
                'key_hash' => $keyHash,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('idempotency_keys')
                ->where('scope', $scope)
                ->where('key_hash', $keyHash)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = $merchant->payments()->where('idempotency_key_hash', $keyHash)->first();

            if ($existing !== null) {
                abort_unless($this->matches($existing, $data), 409, 'The idempotency key was already used for a different payment.');

                return [$existing, false];
            }

            $payment = $merchant->payments()->create([
                ...$data,
                'public_id' => $publicId,
                'status' => Payment::STATUS_PENDING,
                'checkout_token_hash' => hash('sha256', $checkoutToken),
                'idempotency_key_hash' => $keyHash,
                'expires_at' => $now->copy()->addMinutes(30),
            ]);

            return [$payment, true];
        }, 3);

        $response = (new PaymentResource($payment))->resolve($request);

        $response['checkout_url'] = url('/checkout/'.($created ? $checkoutToken : $this->checkoutToken($payment->public_id)));

        return response()->json($response, $created ? 201 : 200);
    }

    public function show(Request $request, string $payment): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->attributes->get('authenticated_merchant');
        $ownedPayment = $merchant->payments()->where('public_id', $payment)->firstOrFail();

        return response()->json((new PaymentResource($ownedPayment))->resolve($request));
    }

    /** @param array<string, mixed> $data */
    private function matches(Payment $payment, array $data): bool
    {
        return $payment->merchant_reference === $data['merchant_reference']
            && $payment->amount_minor === $data['amount_minor']
            && $payment->currency === $data['currency']
            && $payment->description === $data['description']
            && $payment->return_url === $data['return_url']
            && $payment->cancel_url === $data['cancel_url'];
    }

    private function checkoutToken(string $publicId): string
    {
        return 'chk_'.hash_hmac('sha256', $publicId, (string) config('app.key'));
    }
}
