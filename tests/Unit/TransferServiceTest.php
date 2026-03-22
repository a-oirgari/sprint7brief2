<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\TransactionRepository;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TransferServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransferService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TransferService(new TransactionRepository());

        $this->user = User::factory()->create([
            'date_of_birth' => '1990-01-01',
            'is_admin'      => false,
            'is_minor'      => false,
        ]);
    }

    

    private function makeAccount(array $attrs = []): Account
    {
        $account = Account::factory()->create(array_merge([
            'type'            => 'COURANT',
            'status'          => 'ACTIVE',
            'balance'         => 1000.00,
            'overdraft_limit' => 0,
        ], $attrs));

        $account->users()->attach($this->user->id, ['role' => 'owner']);

        return $account;
    }


    /**
     * @test
     */
    public function it_rejects_transfer_with_insufficient_balance(): void
    {
        $from = $this->makeAccount(['balance' => 100, 'overdraft_limit' => 0]);
        $to   = $this->makeAccount(['balance' => 0]);

        $this->expectException(ValidationException::class);

        $this->service->transfer([
            'from_account_id' => $from->id,
            'to_account_id'   => $to->id,
            'amount'          => 500,
        ], $this->user);
    }

    /**
     * @test
     */
    public function it_allows_transfer_within_overdraft_limit(): void
    {
        $from = $this->makeAccount(['balance' => 50, 'overdraft_limit' => 500]);
        $to   = $this->makeAccount(['balance' => 0]);

        $tx = $this->service->transfer([
            'from_account_id' => $from->id,
            'to_account_id'   => $to->id,
            'amount'          => 200, 
        ], $this->user);

        $this->assertEquals('COMPLETED', $tx->status);
        $this->assertEquals(200, $tx->amount);
    }

    /**
     * @test
     */
    public function it_rejects_transfer_when_savings_monthly_limit_reached(): void
    {
        $epargne = Account::factory()->create([
            'type'    => 'EPARGNE',
            'status'  => 'ACTIVE',
            'balance' => 10000,
        ]);
        $to = $this->makeAccount();

        $epargne->users()->attach($this->user->id, ['role' => 'owner']);

        
        for ($i = 0; $i < 3; $i++) {
            Transaction::create([
                'account_id' => $epargne->id,
                'type'       => 'TRANSFER',
                'amount'     => 100,
                'status'     => 'COMPLETED',
            ]);
        }

        $this->expectException(ValidationException::class);

        $this->service->transfer([
            'from_account_id' => $epargne->id,
            'to_account_id'   => $to->id,
            'amount'          => 100,
        ], $this->user);
    }

    /**
     * @test
     */
    public function it_rejects_transfer_from_minor_account_by_non_guardian(): void
    {
        $guardian = User::factory()->create(['date_of_birth' => '1985-01-01']);
        $minor    = User::factory()->create([
            'date_of_birth' => now()->subYears(14)->format('Y-m-d'),
            'is_minor'      => true,
        ]);

        $minorAccount = Account::factory()->create([
            'type'        => 'MINEUR',
            'status'      => 'ACTIVE',
            'balance'     => 500,
            'guardian_id' => $guardian->id,
        ]);
        $minorAccount->users()->attach($minor->id, ['role' => 'owner']);

        $to = $this->makeAccount();

        $randomUser = User::factory()->create(['date_of_birth' => '1992-01-01']);

        $this->expectException(ValidationException::class);

        $this->service->transfer([
            'from_account_id' => $minorAccount->id,
            'to_account_id'   => $to->id,
            'amount'          => 100,
        ], $randomUser); 
    }

    /**
     * @test
     */
    public function it_allows_transfer_from_minor_account_by_guardian(): void
    {
        $guardian = User::factory()->create(['date_of_birth' => '1985-01-01']);
        $minor    = User::factory()->create([
            'date_of_birth' => now()->subYears(14)->format('Y-m-d'),
            'is_minor'      => true,
        ]);

        $minorAccount = Account::factory()->create([
            'type'        => 'MINEUR',
            'status'      => 'ACTIVE',
            'balance'     => 500,
            'guardian_id' => $guardian->id,
        ]);
        $minorAccount->users()->attach($minor->id, ['role' => 'owner']);

        $to = Account::factory()->create(['type' => 'COURANT', 'status' => 'ACTIVE', 'balance' => 0]);
        $to->users()->attach($guardian->id, ['role' => 'owner']);

        $tx = $this->service->transfer([
            'from_account_id' => $minorAccount->id,
            'to_account_id'   => $to->id,
            'amount'          => 100,
        ], $guardian); 

        $this->assertEquals('COMPLETED', $tx->status);
    }

    /**
     * @test
     */
    public function it_rejects_transfer_from_blocked_account(): void
    {
        $blocked = Account::factory()->create([
            'type'    => 'COURANT',
            'status'  => 'BLOCKED',
            'balance' => 5000,
        ]);
        $blocked->users()->attach($this->user->id, ['role' => 'owner']);

        $to = $this->makeAccount();

        $this->expectException(ValidationException::class);

        $this->service->transfer([
            'from_account_id' => $blocked->id,
            'to_account_id'   => $to->id,
            'amount'          => 100,
        ], $this->user);
    }

    /**
     * @test
     */
    public function it_rejects_transfer_to_same_account(): void
    {
        $account = $this->makeAccount(['balance' => 1000]);

        $this->expectException(ValidationException::class);

        $this->service->transfer([
            'from_account_id' => $account->id,
            'to_account_id'   => $account->id,
            'amount'          => 100,
        ], $this->user);
    }

    /**
     * @test
     */
    public function it_rejects_transfer_exceeding_daily_limit(): void
    {
        $from = $this->makeAccount(['balance' => 50000]);
        $to   = $this->makeAccount(['balance' => 0]);

        
        Transaction::create([
            'account_id' => $from->id,
            'type'       => 'TRANSFER',
            'amount'     => 9500,
            'status'     => 'COMPLETED',
        ]);

        $this->expectException(ValidationException::class);

        
        $this->service->transfer([
            'from_account_id' => $from->id,
            'to_account_id'   => $to->id,
            'amount'          => 600,
        ], $this->user);
    }
}