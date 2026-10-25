<!doctype html>
<html lang="en"><head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="referrer" content="no-referrer">
  <link rel="icon" href="{{ asset('pwa-icons/icon-192.png') }}">
  <title>Choose a new password | AquaDissect</title>
  @vite('resources/css/privacy.css')
</head><body><main class="password-change">
  <nav><strong>AquaDissect</strong></nav>
  <h1>Choose a new password</h1>
  <p>Before opening your course, replace your temporary password with one that only you know.</p>
  <p>Your student ID is your username. Your new password must be different from your student ID.</p>
  @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
  <form method="post" action="{{ route('password.update') }}">@csrf
    <label>New password<input type="password" name="password" autocomplete="new-password" minlength="8" required autofocus></label>
    <label>Confirm new password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required></label>
    <p>Use at least eight characters, including a letter and a number. Keep it private.</p>
    <button class="connect-email" type="submit">Save new password</button>
  </form>
  <form method="post" action="{{ route('password.sign-out') }}">@csrf<button class="password-sign-out" type="submit">Sign out</button></form>
</main></body></html>
