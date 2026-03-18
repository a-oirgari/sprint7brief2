<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transfer\CreateTransferRequest;
use App\Models\Transaction;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;

class TransferController extends Controller
{
    public function __construct(private TransferService $service) {}

    public function store(CreateTransferRequest $request): JsonResponse
    {
        $transaction = $this->service->transfer($request->validated(), auth()->user());
        return response()->json($transaction, 201);
    }

    public function show(Transaction $transaction): JsonResponse
    {
        $user = auth()->user();
        $account = $transaction->account()->with('users')->first();

        if (!$account->users->contains($user->id) && $account->guardian_id !== $user->id) {
            abort(403);
        }

        return response()->json($transaction->load('account', 'relatedAccount', 'initiatedBy'));
    }
}