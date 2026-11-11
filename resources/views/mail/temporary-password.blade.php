<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your account is ready</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .wrapper { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e4e4e7; }
        .header { background-color: #18181b; padding: 24px 32px; }
        .header h1 { margin: 0; color: #ffffff; font-size: 18px; font-weight: 600; }
        .body { padding: 32px; }
        .greeting { font-size: 15px; color: #3f3f46; margin: 0 0 16px; }
        .instruction { font-size: 15px; color: #3f3f46; margin: 0 0 24px; }
        .credentials { background: #f4f4f5; border-radius: 8px; padding: 20px 24px; margin: 0 0 24px; }
        .credential-row { display: flex; justify-content: space-between; gap: 16px; margin-bottom: 12px; font-size: 14px; }
        .credential-row:last-child { margin-bottom: 0; }
        .credential-label { color: #71717a; white-space: nowrap; }
        .credential-value { color: #18181b; font-weight: 500; word-break: break-all; text-align: right; }
        .password-value { font-family: 'SFMono-Regular', Consolas, monospace; font-size: 15px; font-weight: 700; letter-spacing: 0.05em; }
        .note { font-size: 14px; color: #71717a; background: #fefce8; border: 1px solid #fde68a; border-radius: 6px; padding: 12px 16px; margin: 0 0 24px; }
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
                Your account has been created. Use the credentials below to sign in for the first time.
            </p>
            <div class="credentials">
                <div class="credential-row">
                    <span class="credential-label">Login URL</span>
                    <span class="credential-value">{{ route('login') }}</span>
                </div>
                <div class="credential-row">
                    <span class="credential-label">Email</span>
                    <span class="credential-value">{{ $user->email }}</span>
                </div>
                <div class="credential-row">
                    <span class="credential-label">Temporary password</span>
                    <span class="credential-value password-value">{{ $password }}</span>
                </div>
            </div>
            <p class="note">
                You will be asked to set a new password immediately after your first login.
                This temporary password cannot be used again once you have changed it.
            </p>
            <p class="footer-note">
                If you did not expect this email, please contact your administrator.
            </p>
        </div>
    </div>
</body>
</html>
