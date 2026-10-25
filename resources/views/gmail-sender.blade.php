<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="referrer" content="no-referrer">
  <link rel="icon" href="{{ asset('pwa-icons/icon-192.png') }}">
  <title>Email delivery | AquaDissect</title>
  @vite('resources/css/privacy.css')
</head>
<body><main class="email-delivery">
  <nav><strong>AquaDissect</strong><a href="{{ route('portal.roster') }}">People &amp; sections</a></nav>
  <h1>Email delivery</h1>
  <p>Connect the designated Google account to send student verification emails. This grants sending access only; it does not read inbox messages. Student Google sign-in keeps its existing permissions.</p>
  <p role="status">{{ $connected ? 'Google sending account connected.' : 'Google sending account not connected.' }}</p>
  @if(session('gmail_sender_message'))<p role="status">{{ session('gmail_sender_message') }}</p>@endif
  @if($configured)
    <form method="post" action="{{ route('gmail-sender.connect') }}">@csrf<button class="connect-email" type="submit">{{ $connected ? 'Reconnect Google email' : 'Connect Google email' }}</button></form>
  @else
    <p>The server needs its Google sending configuration before you can connect.</p>
  @endif
  <p>Google will ask the account owner to approve access. A connected account does not open student setup automatically. Confirm actual inbox receipt of a test email before enabling student verification.</p>
</main></body>
</html>
