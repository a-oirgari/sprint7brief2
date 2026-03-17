<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_number', 'type', 'status', 'balance',
        'overdraft_limit', 'interest_rate', 'monthly_fee',
        'blocked_reason', 'guardian_id',
    ];

    protected $casts = [
        'balance'        => 'decimal:2',
        'overdraft_limit'=> 'decimal:2',
        'interest_rate'  => 'decimal:4',
        'monthly_fee'    => 'decimal:2',
    ];

    const TYPE_COURANT = 'COURANT';
    const TYPE_EPARGNE = 'EPARGNE';
    const TYPE_MINEUR  = 'MINEUR';

    const STATUS_ACTIVE  = 'ACTIVE';
    const STATUS_BLOCKED = 'BLOCKED';
    const STATUS_CLOSED  = 'CLOSED';

    public function users()
    {
        return $this->belongsToMany(User::class, 'account_user')
                    ->withPivot('role', 'accepted_closure')
                    ->withTimestamps();
    }

    public function guardian()
    {
        return $this->belongsTo(User::class, 'guardian_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}