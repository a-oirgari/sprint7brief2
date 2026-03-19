<?php

namespace App\Services;

use App\Models\Account;
use App\Models\User;
use App\Repositories\AccountRepository;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountService
{
    public function __construct(private AccountRepository $repo) {}

    public function createAccount(array $data, User $owner): Account
    {
        if ($data['type'] === Account::TYPE_MINEUR) {
            $this->validateMinorAccount($data, $owner);
        }

        $account = $this->repo->create([
            'account_number'  => $this->generateAccountNumber(),
            'type'            => $data['type'],
            'status'          => Account::STATUS_ACTIVE,
            'balance'         => 0,
            'overdraft_limit' => $data['type'] === Account::TYPE_COURANT
                                    ? (float) config('banking.overdraft_limit', 1000)
                                    : 0,
            'interest_rate'   => $data['interest_rate'] ?? 0,
            'monthly_fee'     => $data['type'] === Account::TYPE_COURANT
                                    ? (float) config('banking.monthly_fee', 50)
                                    : 0,
            'guardian_id'     => $data['guardian_id'] ?? null,
        ]);

    
        $account->users()->attach($owner->id, ['role' => 'owner']);

        return $account->load('users', 'guardian');
    }

    private function validateMinorAccount(array $data, User $owner): void
    {
        if (!$owner->isMinor()) {
            throw ValidationException::withMessages([
                'owner' => 'Le titulaire d\'un compte MINEUR doit avoir moins de 18 ans.',
            ]);
        }

        if (empty($data['guardian_id'])) {
            throw ValidationException::withMessages([
                'guardian_id' => 'Un tuteur adulte est obligatoire pour un compte mineur.',
            ]);
        }

        $guardian = User::findOrFail($data['guardian_id']);
        if ($guardian->isMinor()) {
            throw ValidationException::withMessages([
                'guardian_id' => 'Le tuteur doit être majeur (18 ans ou plus).',
            ]);
        }
    }

    public function addCoOwner(Account $account, User $initiator, User $newOwner): void
    {
        if (!$account->isActive()) {
            throw ValidationException::withMessages([
                'account' => 'Le compte doit être ACTIF pour ajouter un co-titulaire.',
            ]);
        }

        if (!$account->users->contains($initiator->id)) {
            throw ValidationException::withMessages([
                'initiator' => 'Vous n\'êtes pas titulaire de ce compte.',
            ]);
        }

        if ($account->users->contains($newOwner->id)) {
            throw ValidationException::withMessages([
                'user_id' => 'Cet utilisateur est déjà co-titulaire de ce compte.',
            ]);
        }

        $account->users()->attach($newOwner->id, ['role' => 'co_owner']);
    }

    public function removeCoOwner(Account $account, User $initiator, User $target): void
    {
        if (!$account->isActive()) {
            throw ValidationException::withMessages([
                'account' => 'Le compte doit être ACTIF.',
            ]);
        }

        if (!$account->users->contains($initiator->id)) {
            throw ValidationException::withMessages([
                'initiator' => 'Vous n\'êtes pas titulaire de ce compte.',
            ]);
        }

        $account->users()->detach($target->id);
    }

    public function assignGuardian(Account $account, User $guardian): void
    {
        if ($account->type !== Account::TYPE_MINEUR) {
            throw ValidationException::withMessages([
                'account' => 'Seul un compte MINEUR peut avoir un tuteur.',
            ]);
        }

        if ($guardian->isMinor()) {
            throw ValidationException::withMessages([
                'guardian_id' => 'Le tuteur doit être majeur.',
            ]);
        }

        $account->update(['guardian_id' => $guardian->id]);
    }

    public function requestClosure(Account $account, User $requester): array
    {
        if ($account->isClosed()) {
            throw ValidationException::withMessages([
                'account' => 'Ce compte est déjà clôturé.',
            ]);
        }

        if (!$account->users->contains($requester->id)) {
            throw ValidationException::withMessages([
                'account' => 'Vous n\'êtes pas titulaire de ce compte.',
            ]);
        }

        if ((float) $account->balance !== 0.0) {
            throw ValidationException::withMessages([
                'balance' => 'Le solde doit être à zéro pour clôturer le compte.',
            ]);
        }

        
        if ($account->type === Account::TYPE_MINEUR && $requester->id !== $account->guardian_id) {
            throw ValidationException::withMessages([
                'guardian' => 'Seul le tuteur peut clôturer un compte mineur.',
            ]);
        }

        
        $account->users()->updateExistingPivot($requester->id, ['accepted_closure' => true]);
        $account->refresh()->load('users');

        
        $allAccepted = $account->users->every(fn($u) => (bool) $u->pivot->accepted_closure);

        if ($allAccepted) {
            $this->repo->update($account, ['status' => Account::STATUS_CLOSED]);
            return [
                'message' => 'Compte clôturé avec succès.',
                'closed'  => true,
            ];
        }

        $pending = $account->users->filter(fn($u) => !$u->pivot->accepted_closure)->count();

        return [
            'message' => "Demande enregistrée. En attente de {$pending} co-titulaire(s).",
            'closed'  => false,
        ];
    }

    
    public function convertMinorToCurrentAccount(Account $account, User $guardian): Account
    {
        if ($account->type !== Account::TYPE_MINEUR) {
            throw ValidationException::withMessages([
                'account' => 'Seul un compte MINEUR peut être converti en COURANT.',
            ]);
        }

        if ($account->guardian_id !== $guardian->id) {
            throw ValidationException::withMessages([
                'guardian' => 'Seul le tuteur peut initier la conversion du compte.',
            ]);
        }

        
        $minor = $account->users->first();
        if ($minor && $minor->isMinor()) {
            throw ValidationException::withMessages([
                'minor' => 'Le titulaire n\'a pas encore atteint sa majorité (18 ans).',
            ]);
        }

        return $this->repo->update($account, [
            'type'            => Account::TYPE_COURANT,
            'guardian_id'     => null,
            'overdraft_limit' => (float) config('banking.overdraft_limit', 1000),
            'monthly_fee'     => (float) config('banking.monthly_fee', 50),
            'interest_rate'   => 0,
        ]);
    }

    private function generateAccountNumber(): string
    {
        do {
            $number = 'MA' . strtoupper(Str::random(10));
        } while (Account::where('account_number', $number)->exists());

        return $number;
    }
}