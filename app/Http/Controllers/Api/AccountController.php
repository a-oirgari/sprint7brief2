<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\CreateAccountRequest;
use App\Http\Requests\Account\AddCoOwnerRequest;
use App\Models\Account;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(private AccountService $service) {}

    public function index(): JsonResponse
    {
        $accounts = auth()->user()->accounts()->with(['users', 'guardian'])->get();
        return response()->json($accounts);
    }

    public function store(CreateAccountRequest $request): JsonResponse
    {
        $account = $this->service->createAccount($request->validated(), auth()->user());
        return response()->json($account, 201);
    }

    public function show(Account $account): JsonResponse
    {
        $this->authorizeAccountAccess($account);
        return response()->json($account->load('users', 'guardian'));
    }

    public function addCoOwner(AddCoOwnerRequest $request, Account $account): JsonResponse
    {
        $newOwner = User::findOrFail($request->user_id);
        $this->service->addCoOwner($account, auth()->user(), $newOwner);
        return response()->json(['message' => 'Co-titulaire ajouté avec succès.']);
    }

    public function removeCoOwner(Account $account, User $user): JsonResponse
    {
        $this->authorizeAccountAccess($account);
        $this->service->removeCoOwner($account, auth()->user(), $user);
        return response()->json(['message' => 'Co-titulaire retiré avec succès.']);
    }

    public function requestClosure(Account $account): JsonResponse
    {
        $this->authorizeAccountAccess($account);
        $result = $this->service->requestClosure($account, auth()->user());
        return response()->json($result);
    }

    public function convert(Account $account): JsonResponse
    {
        $result = $this->service->convertMinorToCurrentAccount($account, auth()->user());
        return response()->json($result);
    }

    private function authorizeAccountAccess(Account $account): void
    {
        $user = auth()->user();
        $isOwner    = $account->users->contains($user->id);
        $isGuardian = $account->guardian_id === $user->id;

        if (!$isOwner && !$isGuardian) {
            abort(403, 'Accès non autorisé à ce compte.');
        }
    }
}