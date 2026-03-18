<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Repositories\AccountRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAccountController extends Controller
{
    public function __construct(private AccountRepository $repo) {}

    public function index(): JsonResponse
    {
        return response()->json($this->repo->all());
    }

    public function block(Request $request, Account $account): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:255']);

        if ($account->isClosed()) {
            return response()->json(['message' => 'Impossible de bloquer un compte clôturé.'], 422);
        }

        $account->update([
            'status'         => Account::STATUS_BLOCKED,
            'blocked_reason' => $request->reason,
        ]);

        return response()->json(['message' => 'Compte bloqué.', 'account' => $account]);
    }

    public function unblock(Account $account): JsonResponse
    {
        if (!$account->isBlocked()) {
            return response()->json(['message' => 'Le compte n\'est pas bloqué.'], 422);
        }

        $account->update([
            'status'         => Account::STATUS_ACTIVE,
            'blocked_reason' => null,
        ]);

        return response()->json(['message' => 'Compte débloqué.', 'account' => $account]);
    }

    public function close(Account $account): JsonResponse
    {
        if ($account->balance != 0) {
            return response()->json(['message' => 'Le solde doit être à zéro pour clôturer.'], 422);
        }

        if ($account->isClosed()) {
            return response()->json(['message' => 'Compte déjà clôturé.'], 422);
        }

        $account->update(['status' => Account::STATUS_CLOSED]);
        return response()->json(['message' => 'Compte clôturé.']);
    }
}