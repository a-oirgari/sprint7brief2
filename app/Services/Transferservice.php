<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferService
{
    public function __construct(private TransactionRepository $repo) {}

    
    public function transfer(array $data, User $initiator): Transaction
    {
        return DB::transaction(function () use ($data, $initiator) {
            $fromAccount = Account::with('users')->findOrFail($data['from_account_id']);
            $toAccount   = Account::with('users')->findOrFail($data['to_account_id']);
            $amount      = (float) $data['amount'];

            
            if ($fromAccount->id === $toAccount->id) {
                throw ValidationException::withMessages([
                    'to_account_id' => 'Le virement vers le même compte est interdit.',
                ]);
            }

            
            if (!$fromAccount->isActive()) {
                throw ValidationException::withMessages([
                    'from_account_id' => "Le compte source est {$fromAccount->status}. Les opérations sont bloquées.",
                ]);
            }

            
            if (!$toAccount->isActive()) {
                throw ValidationException::withMessages([
                    'to_account_id' => "Le compte destinataire est {$toAccount->status}.",
                ]);
            }

            
            $this->checkInitiatorAuthorization($fromAccount, $initiator);

            
            $this->checkDailyLimit($fromAccount, $amount);

            
            $this->checkMonthlyWithdrawals($fromAccount);

            
            $this->checkBalance($fromAccount, $amount);

            
            $transaction = $this->repo->create([
                'account_id'         => $fromAccount->id,
                'related_account_id' => $toAccount->id,
                'type'               => Transaction::TYPE_TRANSFER,
                'amount'             => $amount,
                'status'             => Transaction::STATUS_PENDING,
                'description'        => $data['description'] ?? null,
                'initiated_by'       => $initiator->id,
            ]);

            
            DB::statement(
                'UPDATE accounts SET balance = balance - ? WHERE id = ?',
                [$amount, $fromAccount->id]
            );

            DB::statement(
                'UPDATE accounts SET balance = balance + ? WHERE id = ?',
                [$amount, $toAccount->id]
            );

            
            $transaction->update(['status' => Transaction::STATUS_COMPLETED]);

            return $transaction->load('account', 'relatedAccount', 'initiatedBy');
        });
    }

    
    private function checkInitiatorAuthorization(Account $account, User $initiator): void
    {
        if ($account->type === Account::TYPE_MINEUR) {
            
            if ($account->guardian_id !== $initiator->id) {
                throw ValidationException::withMessages([
                    'authorization' => 'Seul le tuteur peut initier un virement depuis un compte mineur.',
                ]);
            }
        } else {
            
            if (!$account->users->contains($initiator->id)) {
                throw ValidationException::withMessages([
                    'authorization' => 'Vous n\'êtes pas autorisé à débiter ce compte.',
                ]);
            }
        }
    }

    
    private function checkDailyLimit(Account $account, float $amount): void
    {
        $limit = (float) config('banking.daily_transfer_limit', 10000);

        $todayTotal = Transaction::where('account_id', $account->id)
            ->where('type', Transaction::TYPE_TRANSFER)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereDate('created_at', today())
            ->sum('amount');

        if (($todayTotal + $amount) > $limit) {
            throw ValidationException::withMessages([
                'amount' => "Limite journalière de {$limit} MAD dépassée. Déjà effectué aujourd'hui : {$todayTotal} MAD.",
            ]);
        }
    }

    
    private function checkMonthlyWithdrawals(Account $account): void
    {
        $limits = [
            Account::TYPE_EPARGNE => (int) config('banking.savings_max_withdrawals', 3),
            Account::TYPE_MINEUR  => (int) config('banking.minor_max_withdrawals', 2),
        ];

        if (!array_key_exists($account->type, $limits)) {
            return; 
        }

        $max = $limits[$account->type];

        $monthCount = Transaction::where('account_id', $account->id)
            ->where('type', Transaction::TYPE_TRANSFER)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        if ($monthCount >= $max) {
            throw ValidationException::withMessages([
                'account' => "Limite de {$max} retraits par mois atteinte pour ce compte {$account->type}.",
            ]);
        }
    }

    
    private function checkBalance(Account $account, float $amount): void
    {
        $available = (float) $account->balance + (float) $account->overdraft_limit;

        if ($amount > $available) {
            throw ValidationException::withMessages([
                'amount' => "Solde insuffisant. Disponible : {$available} MAD (découvert inclus).",
            ]);
        }
    }
}