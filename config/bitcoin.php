<?php
/**
 * Bitcoin Payment Configuration
 */

return [
    // Coinbase Commerce API
    'coinbase' => [
        'api_key' => getenv('COINBASE_API_KEY') ?: 'b5975a69-3b7e-4274-84ce-9750b830b7a6',
        'webhook_secret' => getenv('COINBASE_WEBHOOK_SECRET') ?: '8d5f733e-ec52-473c-b292-5d3abc793692',
        'api_url' => 'https://api.commerce.coinbase.com',
    ],
    
    // Bitcoin Network
    'network' => getenv('BTC_NETWORK') ?: 'mainnet', // mainnet or testnet
    
    // Minimum Amounts
    'min_deposit_btc' => 0.0001,
    'min_withdrawal_btc' => 0.001,
    'min_transfer_btc' => 0.0001,
    
    // Transaction Fees
    'platform_fee_percentage' => 2.5, // 2.5% platform fee
    'withdrawal_fee_btc' => 0.0001,
    
    // Confirmations Required
    'confirmations_required' => 3,
    
    // Premium Plans (USD)
    'plans' => [
        'plus' => [
            'name' => 'Plus',
            'price_usd' => 9.99,
            'duration_days' => 30,
            'features' => [
                'Unlimited messages',
                'View who favorited you',
                'Advanced search filters',
                'No ads'
            ]
        ],
        'premium' => [
            'name' => 'Premium',
            'price_usd' => 19.99,
            'duration_days' => 30,
            'features' => [
                'All Plus features',
                'Share contact info',
                'Featured listings',
                'Priority support',
                'Incognito mode'
            ]
        ],
        'vip' => [
            'name' => 'VIP',
            'price_usd' => 49.99,
            'duration_days' => 30,
            'features' => [
                'All Premium features',
                'Verified badge',
                'Custom profile badge',
                'Unlimited featured posts',
                'VIP support'
            ]
        ]
    ],
    
    // Price Update Interval (seconds)
    'price_cache_duration' => 300, // 5 minutes
];