<?php

namespace Tests;

use App\Contracts\PaymentGatewayInterface;
use App\Services\Payment\MockPaymentGateway;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure MockPaymentGateway is used in all tests
        $this->app->bind(PaymentGatewayInterface::class, MockPaymentGateway::class);
    }
}
