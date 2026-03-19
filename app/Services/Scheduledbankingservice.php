<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScheduledBankingService
{
    
    public function chargeMonthlyFees(): void
    {
        $accounts = Account::where('type', Account::TYPE_COURANT)
            ->where('status', Account::STATUS_ACTIVE)
            ->get();

        foreach ($accounts as $account) {
            $fee = (float) $account->monthly_fee;

            if ($fee <= 0) continue;

            try {
                if ((float) $account->balance >= $fee) {
                    
                    DB::statement(
                        'UPDATE accounts SET balance = balance - ? WHERE id = ?',
                        [$fee, $account->id]
                    );

                    Transaction::create([
                        'account_id'  => $account->id,
                        'type'        => Transaction::TYPE_FEE,
                        'amount'      => $fee,
                        'status'      => Transaction::STATUS_COMPLETED,
                        'description' => 'Frais de tenue de compte — ' . now()->format('F Y'),
                    ]);

                    Log::info("Frais prélevés sur compte #{$account->id} : {$fee} MAD");
                } else {
                    
                    $account->update([
                        'status'         => Account::STATUS_BLOCKED,
                        'blocked_reason' => 'Frais de tenue de compte impayés — ' . now()->format('F Y'),
                    ]);

                    Transaction::create([
                        'account_id'  => $account->id,
                        'type'        => Transaction::TYPE_FEE_FAILED,
                        'amount'      => $fee,
                        'status'      => Transaction::STATUS_FAILED,
                        'description' => 'Échec prélèvement frais — solde insuffisant',
                    ]);

                    Log::warning("Compte #{$account->id} bloqué : frais impayés.");
                }
            } catch (\Throwable $e) {
                Log::error("Erreur frais compte #{$account->id} : " . $e->getMessage());
            }
        }
    }

    
    public function creditMonthlyInterests(): void
    {
        $accounts = Account::whereIn('type', [Account::TYPE_EPARGNE, Account::TYPE_MINEUR])
            ->where('status', Account::STATUS_ACTIVE)
            ->where('interest_rate', '>', 0)
            ->get();

        foreach ($accounts as $account) {
            $interest = round(
                (float) $account->balance * ((float) $account->interest_rate / 12),
                2
            );

            if ($interest <= 0) continue;

            try {
                DB::statement(
                    'UPDATE accounts SET balance = balance + ? WHERE id = ?',
                    [$interest, $account->id]
                );

                Transaction::create([
                    'account_id'  => $account->id,
                    'type'        => Transaction::TYPE_INTEREST,
                    'amount'      => $interest,
                    'status'      => Transaction::STATUS_COMPLETED,
                    'description' => 'Intérêts mensuels (' . ($account->interest_rate * 100) . '% annuel) — ' . now()->format('F Y'),
                ]);

                Log::info("Intérêts crédités sur compte #{$account->id} : {$interest} MAD");
            } catch (\Throwable $e) {
                Log::error("Erreur intérêts compte #{$account->id} : " . $e->getMessage());
            }
        }
    }
}