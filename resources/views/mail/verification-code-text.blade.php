{{ config('app.name') }} — Verify your email address

Hi {{ $user->name }},

Use the verification code below to confirm your email address:

    {{ $code }}

This code expires in {{ \App\Services\Auth\EmailVerificationService::CODE_EXPIRY_MINUTES }} minutes.
Do not share it with anyone.

If you did not request this code, you can safely ignore this email.
