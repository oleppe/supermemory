# Quick Task 260422-cs0: Implement Stripe Payment Intents in Laravel for Flutter mobile subscriptions

Replace the hosted Stripe Checkout subscription bootstrap with a mobile-first PaymentSheet bootstrap flow for Flutter Android/iOS.

## Scope

- Replace the current checkout-session contract with a PaymentSheet bootstrap contract.
- Create Stripe subscriptions with `payment_behavior=default_incomplete` and return the first invoice payment intent client secret.
- Return customer and ephemeral key data required by Flutter Stripe PaymentSheet.
- Preserve billing portal and webhook-based subscription sync.
- Update focused tests and Flutter API docs.

## Planned Steps

1. Update the Stripe billing service to create a mobile subscription bootstrap payload instead of a hosted Checkout session.
2. Update the request, controller, and API route surface to remove hosted Checkout redirect dependencies.
3. Adjust focused billing tests to the new response contract.
4. Update `.env.example` and Flutter integration docs to reflect PaymentSheet usage.
5. Run focused billing validation.