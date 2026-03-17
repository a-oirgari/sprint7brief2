<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'password',
        'date_of_birth', 'is_admin', 'is_minor',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_admin'      => 'boolean',
        'is_minor'      => 'boolean',
    ];

    
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    
    public function getJWTCustomClaims(): array
    {
        return [
            'is_admin' => $this->is_admin,
            'email'    => $this->email,
        ];
    }

    public function isMinor(): bool
    {
        return $this->date_of_birth->age < 18;
    }

    
    public function accounts()
    {
        return $this->belongsToMany(Account::class, 'account_user')
                    ->withPivot('role', 'accepted_closure')
                    ->withTimestamps();
    }

    
    public function guardianAccounts()
    {
        return $this->hasMany(Account::class, 'guardian_id');
    }
}