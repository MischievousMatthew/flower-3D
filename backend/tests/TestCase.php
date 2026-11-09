<?php

namespace Tests;

use App\Subscriptions\SubscriptionPlans;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The plans are read once per request; each test starts from a fresh read
        SubscriptionPlans::flush();
    }
}
