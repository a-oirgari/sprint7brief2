<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Transaction;
use App\Repositories\TransactionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(private TransactionRepository $repo) {}

    public function indexForAccount(Request $request, Account $account): JsonResponse
    {
        $this->authorizeAccountAccess($account);

        $filters = $request->only(['type', 'from', 'to']);
        $transactions = $this->repo->getForAccount($account->id, $filters);

        return response()->json($transactions);
    }

    public function show(Transaction $transaction): JsonResponse
    {
        $user    = auth()->user();
        $account = $transaction->account()->with('users')->first();

        $isOwner    = $account->users->contains($user->id);
        $isGuardian = $account->guardian_id === $user->id;

        if (!$isOwner && !$isGuardian && !$user->is_admin) {
            abort(403, 'Accès non autorisé à cette transaction.');
        }

        return response()->json(
            $transaction->load('account', 'relatedAccount', 'initiatedBy')
        );
    }

    private function authorizeAccountAccess(Account $account): void
    {
        $user = auth()->user();

        $isOwner    = $account->users->contains($user->id);
        $isGuardian = $account->guardian_id === $user->id;

        if (!$isOwner && !$isGuardian && !$user->is_admin) {
            abort(403, 'Accès non autorisé à ce compte.');
        }
    }
}