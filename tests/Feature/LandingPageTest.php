<?php

namespace Tests\Feature;

use App\Mail\LandingDemoRequestMail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    public function test_root_page_renders_landing_shell(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('MemoDoc | AI Memory For Documents', false)
            ->assertSee('landing-app', false);
    }

    public function test_demo_request_sends_email(): void
    {
        Mail::fake();

        $response = $this->postJson('/demo-request', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'company' => 'Analytical Engines',
            'monthly_volume' => '2,000 PDFs/month',
            'use_case' => 'We need grounded answers across contracts and scanned manuals.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Demo request received.');

        Mail::assertSent(LandingDemoRequestMail::class, function (LandingDemoRequestMail $mail): bool {
            return $mail->submission['email'] === 'ada@example.com';
        });
    }

    public function test_demo_request_validates_required_fields(): void
    {
        $this->postJson('/demo-request', [
            'name' => '',
            'email' => 'not-an-email',
            'use_case' => '',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'use_case']);
    }
}
