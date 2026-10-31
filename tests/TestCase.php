<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUpTraits()
    {
        $databaseName = DB::connection()->getDatabaseName();

        if (! str_ends_with($databaseName, '_test')) {
            throw new RuntimeException("Tests may only use a database ending in _test; connected to [{$databaseName}].");
        }

        return parent::setUpTraits();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
