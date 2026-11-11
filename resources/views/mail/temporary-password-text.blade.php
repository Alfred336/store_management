{{ config('app.name') }}

Hi {{ $user->name }},

Your account has been created. Use the credentials below to sign in for the first time.

Login URL:          {{ route('login') }}
Email:              {{ $user->email }}
Temporary password: {{ $password }}

You will be asked to set a new password immediately after your first login.
This temporary password cannot be used again once you have changed it.

If you did not expect this email, please contact your administrator.
