<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AdminAccountController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;


Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
});


Route::middleware('auth:api')->group(function () {

    
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
    Route::post('auth/logout',  [AuthController::class, 'logout']);

    
    Route::get('users/me', [UserController::class, 'show']);
    Route::put('users/me', [UserController::class, 'update']);
    Route::patch('users/me/password', [UserController::class, 'changePassword']);

    
    Route::get('accounts', [AccountController::class, 'index']);
    Route::post('accounts', [AccountController::class, 'store']);

    Route::get('accounts/{account}',   [AccountController::class, 'show']);
    Route::delete('accounts/{account}', [AccountController::class, 'requestClosure']);
    Route::patch('accounts/{account}/convert', [AccountController::class, 'convert']);

    
    Route::post('accounts/{account}/co-owners', [AccountController::class, 'addCoOwner']);
    Route::delete('accounts/{account}/co-owners/{user}',  [AccountController::class, 'removeCoOwner']);

    
    Route::post('accounts/{account}/guardian', [AccountController::class, 'assignGuardian']);

    
    Route::get('accounts/{account}/transactions', [TransactionController::class, 'indexForAccount']);
    Route::get('transactions/{transaction}',      [TransactionController::class, 'show']);

    
    Route::post('transfers',  [TransferController::class, 'store']);
    Route::get('transfers/{transaction}', [TransferController::class, 'show']);

    
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('accounts',                       [AdminAccountController::class, 'index']);
        Route::patch('accounts/{account}/block',     [AdminAccountController::class, 'block']);
        Route::patch('accounts/{account}/unblock',   [AdminAccountController::class, 'unblock']);
        Route::patch('accounts/{account}/close',     [AdminAccountController::class, 'close']);
    });
});