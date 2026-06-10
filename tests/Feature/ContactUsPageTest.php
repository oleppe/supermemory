<?php

namespace Tests\Feature;

use App\Mail\ContactUsMessageMail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactUsPageTest extends TestCase
{
    public function test_contact_page_renders(): void
    {
        $this->get('/contact-us')
            ->assertOk()
            ->assertSee('Contact Us')
            ->assertSee('Send message');
    }

    public function test_contact_form_sends_email_and_redirects_with_status(): void
    {
        Mail::fake();

        $response = $this->post('/contact-us', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'company' => 'Analytical Engines',
            'subject' => 'Need integration support',
            'message' => 'We want to onboard with OCR-heavy documents.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Your message has been sent. We will get back to you shortly.');

        Mail::assertSent(ContactUsMessageMail::class, function (ContactUsMessageMail $mail): bool {
            return $mail->submission['email'] === 'ada@example.com'
                && $mail->submission['subject'] === 'Need integration support';
        });
    }

    public function test_contact_form_validates_required_fields(): void
    {
        $this->from('/contact-us')
            ->post('/contact-us', [
                'name' => '',
                'email' => 'bad-email',
                'subject' => '',
                'message' => '',
            ])
            ->assertRedirect('/contact-us')
            ->assertSessionHasErrors(['name', 'email', 'subject', 'message']);
    }
}
