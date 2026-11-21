# login512-laravel 2.1.1

Laravel package for **Login512** (face login + passkeys, ML-DSA-65 signed tokens).
Laravel 10, 11 or 12, PHP 8.1+, ext-curl.

## Installation
```bash
composer require login512/login512-laravel
php artisan vendor:publish --tag=login512-config
```

### Without Packagist (ZIP)
1. Download `https://login512.com/downloads/login512-laravel.zip` and extract it to `packages/login512-laravel/`.
2. In your app's `composer.json`:
   ```json
   "repositories": [{"type": "path", "url": "packages/login512-laravel"}]
   ```
3. Install and publish the config:
   ```bash
   composer require login512/login512-laravel:*
   php artisan vendor:publish --tag=login512-config
   ```

The core PHP SDK is bundled in `core/Login512.php` (class `\Login512\Login512`); no other package is needed.

## `.env`
```env
LOGIN512_PUBLISHABLE_KEY=l512_pk_live_your_key
LOGIN512_SECRET_KEY=l512_sk_live_your_secret
LOGIN512_ENDPOINT=https://api.login512.com
```

## Login button (Blade)
```blade
<head> @login512Scripts </head>
<body>
  @login512Button
  <script>
    document.addEventListener('l512:success', async (e) => {
      const r = await fetch('/login512/verify', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
        body: JSON.stringify({token: e.detail.token}),
      });
      if (r.ok) location.href = (await r.json()).redirect;
    });
  </script>
</body>
```

## Controller
```php
use Login512\Laravel\Facades\Login512;
use Illuminate\Support\Facades\Auth;

public function verify(Request $request)
{
    $res = Login512::verifyToken((string) $request->input('token'));

    // Use session_allowed, not valid: a valid token can still require a step-up.
    if (empty($res['session_allowed'])) {
        return response()->json(['error' => $res['error'] ?? 'STEP_UP_REQUIRED'], 401);
    }

    // claims.sub is the external_user_id YOU set when enrolling the user (see createEnrollmentToken).
    $user = User::where('login512_id', $res['claims']['sub'])->first();
    if (!$user) {
        return response()->json(['error' => 'NOT_ENROLLED'], 401);
    }
    Auth::login($user);
    return response()->json(['redirect' => route('dashboard')]);
}
```

Enroll an existing, signed-in user: `Login512::createEnrollmentToken((string) $user->id, $user->name)` and pass the token
to the frontend enrollment flow; store the same id in `users.login512_id`.

## Protecting sensitive routes (step-up)
```php
Route::middleware(['auth:sanctum', 'login512.verified'])->post('/vault/export', ...);
```
`login512.verified` requires a fresh Login512 token in the `X-Login512-Token` header (or `login512_token` input).
Tokens are single-use, so use it for sensitive actions, not for every page. Verified claims:
`$request->attributes->get('login512_claims')`. If you stored a nonce in `session('login512_nonce')`, it is checked too.
