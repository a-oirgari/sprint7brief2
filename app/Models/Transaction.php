<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id', 'related_account_id', 'type',
        'amount', 'status', 'description', 'initiated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    const TYPE_CREDIT   = 'CREDIT';
    const TYPE_DEBIT    = 'DEBIT';
    const TYPE_FEE      = 'FEE';
    const TYPE_FEE_FAILED = 'FEE_FAILED';
    const TYPE_INTEREST = 'INTEREST';
    const TYPE_TRANSFER = 'TRANSFER';

    const STATUS_PENDING   = 'PENDING';
    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_FAILED    = 'FAILED';

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function relatedAccount()
    {
        return $this->belongsTo(Account::class, 'related_account_id');
    }

    public function initiatedBy()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}