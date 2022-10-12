<?php

declare(strict_types=1);

namespace ProductTrap\BigWAustralia\Tests;

use ProductTrap\BigWAustralia\BigWAustraliaServiceProvider;
use ProductTrap\ProductTrapServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ProductTrapServiceProvider::class, BigWAustraliaServiceProvider::class];
    }
}
