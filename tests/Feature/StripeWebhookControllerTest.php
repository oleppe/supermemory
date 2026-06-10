<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MocksStripeBillingService;
use Tests\TestCase;

class StripeWebhookControllerTest extends TestCase
{
    use MocksStripeBillingService;
    use RefreshDatabase;

    public function test_api_webhook_forwards_payload_and_signature_to_billing_service(): void
    {
        $stripe = $this->mockStripeBillingService();

        $stripe->expects($this->once())
            ->method('handleWebhook')
            ->with(
                '{"id":"evt_test_123","type":"checkout.session.completed"}',
                't=1,v1=testsig',
            );

        $this->call(
            'POST',
            '/api/stripe/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't=1,v1=testsig',
            ],
            '{"id":"evt_test_123","type":"checkout.session.completed"}',
        )
            ->assertOk()
            ->assertJsonPath('received', true);
    }

    public function test_web_webhook_alias_forwards_payload_and_signature_to_billing_service(): void
    {
        $stripe = $this->mockStripeBillingService();

        $stripe->expects($this->once())
            ->method('handleWebhook')
            ->with(
                '{"id":"evt_test_456","type":"payment_intent.succeeded"}',
                't=1,v1=testsig',
            );

        $this->call(
            'POST',
            '/stripe/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't=1,v1=testsig',
            ],
            '{"id":"evt_test_456","type":"payment_intent.succeeded"}',
        )
            ->assertOk()
            ->assertJsonPath('received', true);
    }
}
