<?php

class CoinsSystem {
    private $balance;
    private $transactions;

    public function __construct() {
        $this->balance = 0;
        $this->transactions = [];
    }

    public function getBalance() {
        return $this->balance;
    }

    public function addTransaction($amount, $type) {
        $transaction = [
            'amount' => $amount,
            'type' => $type,
            'date' => date('Y-m-d H:i:s')
        ];
        $this->transactions[] = $transaction;
    }

    public function deposit($amount) {
        if ($amount > 0) {
            $this->balance += $amount;
            $this->addTransaction($amount, 'deposit');
            return true;
        }
        return false;
    }

    public function withdraw($amount) {
        if ($amount > 0 && $this->balance >= $amount) {
            $this->balance -= $amount;
            $this->addTransaction($amount, 'withdraw');
            return true;
        }
        return false;
    }

    public function transfer($amount, CoinsSystem $recipient) {
        if ($this->withdraw($amount)) {
            $recipient->deposit($amount);
            $this->addTransaction($amount, 'transfer to recipient');
            $recipient->addTransaction($amount, 'transfer from sender');
            return true;
        }
        return false;
    }

    public function integrateBitcoin($amountInBitcoin) {
        $conversionRate = 50000; // Example conversion rate from Bitcoin to coins
        $amountInCoins = $amountInBitcoin * $conversionRate;
        $this->deposit($amountInCoins);
        return $amountInCoins;
    }

    public function getTransactionHistory() {
        return $this->transactions;
    }
}

?>
