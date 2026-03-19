<?php

namespace App\Repositories;

use App\Models\Account;
use Illuminate\Pagination\LengthAwarePaginator;

class AccountRepository
{
    public function create(array $data): Account
    {
        return Account::create($data);
    }

    public function update(Account $account, array $data): Account
    {
        $account->update($data);
        return $account->fresh();
    }

    public function findByIdWithRelations(int $id): ?Account
    {
        return Account::with(['users', 'guardian', 'transactions'])->find($id);
    }

    public function allForUser(int $userId)
    {
        return Account::whereHas('users', fn($q) => $q->where('users.id', $userId))
                      ->with(['users', 'guardian'])
                      ->get();
    }

    public function all(): LengthAwarePaginator
    {
        return Account::with(['users', 'guardian'])->paginate(20);
    }
}