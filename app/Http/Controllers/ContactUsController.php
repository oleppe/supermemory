<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactUsRequest;
use App\Mail\ContactUsMessageMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ContactUsController extends Controller
{
    public function show(): View
    {
        return view('contact-us');
    }

    public function store(StoreContactUsRequest $request): RedirectResponse
    {
        Mail::to((string) config('services.contact.recipient'))
            ->send(new ContactUsMessageMail($request->validated()));

        return back()->with('status', 'Your message has been sent. We will get back to you shortly.');
    }
}
