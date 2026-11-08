<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify your email address</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .wrapper { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e4e4e7; }
        .header { background-color: #18181b; padding: 24px 32px; }
        .header h1 { margin: 0; color: #ffffff; font-size: 18px; font-weight: 600; }
        .body { padding: 32px; }
        .greeting { font-size: 15px; color: #3f3f46; margin: 0 0 16px; }
        .instruction { font-size: 15px; color: #3f3f46; margin: 0 0 24px; }
        .code-box { background: #f4f4f5; border-radius: 8px; padding: 20px; text-align: center; margin: 0 0 24px; }
        .code { font-size: 36px; font-weight: 700; letter-spacing: 10px; color: #18181b; font-variant-numeric: tabular-nums; }
        .expiry { font-size: 13px; color: #71717a; margin: 0 0 24px; }
        .footer-note { font-size: 13px; color: #71717a; border-top: 1px solid #e4e4e7; padding-top: 20px; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>{{ config('app.name') }}</h1>
        </div>
        <div class="body">
            <p class="greeting">Hi {{ $user->name }},</p>
            <p class="instruction">
                Use the verification code below to confirm your email address. Enter it on the
                verification screen to complete your account setup.
            </p>
            <div class="code-box">
                <div class="code">{{ $code }}</div>
            </div>
            <p class="expiry">
                This code expires in {{ \App\Services\Auth\EmailVerificationService::CODE_EXPIRY_MINUTES }} minutes.
                Do not share it with anyone.
            </p>
            <p class="footer-note">
                If you did not request this code, you can safely ignore this email.
                Your password will not change.
            </p>
        </div>
    </div>
</body>
</html>
