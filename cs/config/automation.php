<?php

return [
    'rpc_url' => env('NANO_RPC_URL', 'http://[::1]:7076'),
    'admin_wallet' => env('NANO_ADMIN_WALLET'),
    'encryption_key' => env('NANO_ENCRYPTION_KEY'),
    'withdrawal_fee' => env('NANO_WITHDRAWAL_FEE', 0.01),
];