<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddMoneyRequest;
use App\Http\Requests\SendMoneyRequest;
use App\Http\Resources\LedgerTransactionResource;
use App\Http\Resources\WalletResource;
use App\Models\LedgerTransaction;
use App\Models\Wallet;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WalletController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->is_active, 403, 'This account is unavailable.');

        $wallet = $user->wallet()->firstOrFail();

        return response()->json([
            'wallet' => (new WalletResource($wallet))->resolve($request),
        ]);
    }

    public function addMoney(AddMoneyRequest $request, LedgerService $ledger): JsonResponse
    {
        $wallet = Wallet::query()->findOrFail($request->integer('wallet_id'));
        $transaction = $ledger->addMoney(
            $request->user(),
            $wallet,
            $request->integer('amount_minor'),
            $request->string('idempotency_key')->toString(),
        );

        return response()->json([
            'transaction' => (new LedgerTransactionResource($transaction))->resolve($request),
            'wallet' => (new WalletResource($wallet->refresh()))->resolve($request),
        ]);
    }

    public function sendMoney(SendMoneyRequest $request, LedgerService $ledger): JsonResponse
    {
        $sender = $request->user()->wallet()->firstOrFail();
        $recipient = Wallet::query()->findOrFail($request->integer('recipient_wallet_id'));
        $transaction = $ledger->sendMoney(
            $request->user(),
            $sender,
            $recipient,
            $request->integer('amount_minor'),
            $request->string('idempotency_key')->toString(),
        );

        return response()->json([
            'transaction' => (new LedgerTransactionResource($transaction))->resolve($request),
            'wallet' => (new WalletResource($sender->refresh()))->resolve($request),
        ]);
    }

    public function transactions(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user?->is_active, 403, 'This account is unavailable.');

        $wallet = $user->wallet()->firstOrFail();

        $transactions = LedgerTransaction::query()
            ->join('ledger_entries as wallet_entries', 'wallet_entries.ledger_transaction_id', '=', 'ledger_transactions.id')
            ->join('ledger_accounts as wallet_accounts', 'wallet_accounts.id', '=', 'wallet_entries.ledger_account_id')
            ->where('wallet_accounts.wallet_id', $wallet->id)
            ->select([
                'ledger_transactions.*',
                'wallet_entries.direction as wallet_direction',
            ])
            ->orderByDesc('ledger_transactions.posted_at')
            ->orderByDesc('ledger_transactions.id')
            ->cursorPaginate(20);

        return LedgerTransactionResource::collection($transactions);
    }
}
