<?php

use App\Services\Money\Money;

it('performs decimal operations without floating point arithmetic', function () {
    $money = new Money;

    expect($money->add('125.10', '7.25'))->toBe('132.35')
        ->and($money->sub('125.10', '7.25'))->toBe('117.85')
        ->and($money->mul('3.50', '2.25'))->toBe('7.88')
        ->and($money->percent('100.00', '18.00'))->toBe('18.00')
        ->and($money->round('1.235'))->toBe('1.24')
        ->and($money->round('-1.235'))->toBe('-1.24')
        ->and($money->compare('10.00', '9.99'))->toBe(1);
});

it('rejects division by zero', function () {
    (new Money)->divide('10', '0');
})->throws(InvalidArgumentException::class);
