<?php

return [

    
    'overdraft_limit' => env('OVERDRAFT_LIMIT', 1000),

    
    'daily_transfer_limit' => env('DAILY_TRANSFER_LIMIT', 10000),

    
    'savings_max_withdrawals' => env('SAVINGS_MAX_WITHDRAWALS', 3),

    
    'minor_max_withdrawals' => env('MINOR_MAX_WITHDRAWALS', 2),

    
    'monthly_fee' => env('MONTHLY_FEE_AMOUNT', 50),
];