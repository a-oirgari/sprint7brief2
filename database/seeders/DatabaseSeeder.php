<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        
        $admin = User::create([
            'first_name'    => 'Admin',
            'last_name'     => 'AlMadar',
            'email'         => 'admin@almadar.ma',
            'password'      => Hash::make('password'),
            'date_of_birth' => '1980-01-01',
            'is_admin'      => true,
        ]);

        
        $guardian = User::create([
            'first_name'    => 'Karim',
            'last_name'     => 'Bensaid',
            'email'         => 'karim@example.ma',
            'password'      => Hash::make('password'),
            'date_of_birth' => '1985-06-15',
        ]);

        
        $minor = User::create([
            'first_name'    => 'Youssef',
            'last_name'     => 'Bensaid',
            'email'         => 'youssef@example.ma',
            'password'      => Hash::make('password'),
            'date_of_birth' => now()->subYears(14)->format('Y-m-d'),
            'is_minor'      => true,
        ]);

        
        $client = User::create([
            'first_name'    => 'Fatima',
            'last_name'     => 'Zahra',
            'email'         => 'fatima@example.ma',
            'password'      => Hash::make('password'),
            'date_of_birth' => '1990-03-20',
        ]);

       
        $courant = Account::create([
            'account_number' => 'MA001COURANT01',
            'type'           => 'COURANT',
            'status'         => 'ACTIVE',
            'balance'        => 5000,
            'overdraft_limit'=> 1000,
            'monthly_fee'    => 50,
        ]);
        $courant->users()->attach($guardian->id, ['role' => 'owner']);

        $epargne = Account::create([
            'account_number' => 'MA002EPARGNE01',
            'type'           => 'EPARGNE',
            'status'         => 'ACTIVE',
            'balance'        => 20000,
            'interest_rate'  => 0.035, // 3.5%
        ]);
        $epargne->users()->attach($client->id, ['role' => 'owner']);

        $mineur = Account::create([
            'account_number' => 'MA003MINEUR001',
            'type'           => 'MINEUR',
            'status'         => 'ACTIVE',
            'balance'        => 1500,
            'interest_rate'  => 0.02,
            'guardian_id'    => $guardian->id,
        ]);
        $mineur->users()->attach($minor->id, ['role' => 'owner']);

        $joint = Account::create([
            'account_number' => 'MA004JOINT001',
            'type'           => 'COURANT',
            'status'         => 'ACTIVE',
            'balance'        => 8000,
            'overdraft_limit'=> 1000,
            'monthly_fee'    => 50,
        ]);
        $joint->users()->attach($guardian->id, ['role' => 'owner']);
        $joint->users()->attach($client->id, ['role' => 'co_owner']);

        $blocked = Account::create([
            'account_number' => 'MA005BLOCKED1',
            'type'           => 'COURANT',
            'status'         => 'BLOCKED',
            'balance'        => 200,
            'blocked_reason' => 'Activité suspecte détectée',
            'monthly_fee'    => 50,
        ]);
        $blocked->users()->attach($admin->id, ['role' => 'owner']);

        
        $transactions = [
            ['account_id' => $courant->id, 'type' => 'CREDIT',   'amount' => 5000,  'status' => 'COMPLETED', 'description' => 'Dépôt initial'],
            ['account_id' => $courant->id, 'type' => 'TRANSFER',  'amount' => 200,   'status' => 'COMPLETED', 'description' => 'Virement loyer'],
            ['account_id' => $epargne->id, 'type' => 'CREDIT',   'amount' => 20000, 'status' => 'COMPLETED', 'description' => 'Dépôt épargne'],
            ['account_id' => $epargne->id, 'type' => 'INTEREST', 'amount' => 58.33, 'status' => 'COMPLETED', 'description' => 'Intérêts janvier'],
            ['account_id' => $mineur->id,  'type' => 'CREDIT',   'amount' => 1500,  'status' => 'COMPLETED', 'description' => 'Argent de poche'],
            ['account_id' => $mineur->id,  'type' => 'INTEREST', 'amount' => 2.50,  'status' => 'COMPLETED', 'description' => 'Intérêts janvier'],
            ['account_id' => $joint->id,   'type' => 'CREDIT',   'amount' => 8000,  'status' => 'COMPLETED', 'description' => 'Dépôt conjoint'],
            ['account_id' => $joint->id,   'type' => 'TRANSFER',  'amount' => 500,   'status' => 'COMPLETED', 'description' => 'Courses'],
            ['account_id' => $courant->id, 'type' => 'FEE',      'amount' => 50,    'status' => 'COMPLETED', 'description' => 'Frais tenue janvier'],
            ['account_id' => $blocked->id, 'type' => 'FEE_FAILED','amount' => 50,   'status' => 'FAILED',    'description' => 'Frais impayés'],
        ];

        foreach ($transactions as $tx) {
            Transaction::create($tx);
        }
    }
}