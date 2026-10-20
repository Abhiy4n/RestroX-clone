<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class WalletOperationException extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private function __construct(
        string $message,
        private readonly int $status,
        private readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public static function invalidAmount(): self
    {
        return new self(
            'The amount must be positive and within wallet limits.',
            422,
            ['amount_minor' => ['The amount must be positive and within wallet limits.']],
        );
    }

    public static function invalidIdempotencyKey(): self
    {
        return new self(
            'The Idempotency-Key header is invalid.',
            422,
            ['idempotency_key' => ['The Idempotency-Key header is invalid.']],
        );
    }

    public static function selfTransfer(): self
    {
        return new self(
            'The recipient wallet must be different from the sender wallet.',
            422,
            ['recipient_wallet_id' => ['The recipient wallet must be different from the sender wallet.']],
        );
    }

    public static function walletNotFound(): self
    {
        return new self('Wallet not found.', 404);
    }

    public static function unsupportedCurrency(): self
    {
        return new self('Only NPR wallets are supported.', 422);
    }

    public static function insufficientBalance(): self
    {
        return new self(
            'The wallet has insufficient balance.',
            422,
            ['amount_minor' => ['The wallet has insufficient balance.']],
        );
    }

    public static function balanceLimitExceeded(): self
    {
        return new self(
            'The recipient wallet balance limit would be exceeded.',
            422,
            ['amount_minor' => ['The recipient wallet balance limit would be exceeded.']],
        );
    }

    public static function idempotencyConflict(): self
    {
        return new self('The Idempotency-Key was already used for a different operation.', 409);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        $payload = ['message' => $this->getMessage()];

        if ($this->errors !== []) {
            $payload['errors'] = $this->errors;
        }

        return response()->json($payload, $this->status);
    }
}
