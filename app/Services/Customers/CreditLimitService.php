<?php

namespace App\Services\Customers;

use App\Models\Customer;
use App\Services\Money\Money;
use App\Services\Settings\Settings;
use InvalidArgumentException;

/** Evaluate whether an additional credit sale exceeds the customer's limit. */
class CreditLimitService
{
    public function __construct(
        private readonly CustomerBalance $customerBalance,
        private readonly Money $money,
        private readonly Settings $settings,
    ) {}

    /** @return array{balance: string, projected_balance: string, credit_limit: string, outcome: 'within_limit'|'warn'|'block'} */
    public function evaluate(Customer $customer, string $additionalCredit): array
    {
        if (bccomp($this->money->decimal($additionalCredit), '0', 2) < 0) {
            throw new InvalidArgumentException('Additional credit amount cannot be negative.');
        }

        $balance = $this->customerBalance->outstanding($customer);
        $projectedBalance = $this->money->add($balance, $additionalCredit);
        $creditLimit = $customer->credit_limit;

        if ($this->money->compare($projectedBalance, $creditLimit) <= 0) {
            $outcome = 'within_limit';
        } else {
            $outcome = $this->settings->get('customers.credit_limit_mode', 'warn') === 'block' ? 'block' : 'warn';
        }

        return [
            'balance' => $balance,
            'projected_balance' => $projectedBalance,
            'credit_limit' => $creditLimit,
            'outcome' => $outcome,
        ];
    }
}
