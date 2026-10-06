<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title') | AquaDissect</title>
  @vite('resources/css/privacy.css')
  <style>
    body{font-size:14px;background:#f2f2f7;color:#343a40}
    main{max-width:620px;margin:10vh auto;padding:24px;border-radius:5px}
    h1{font-size:20px;color:#283d85}
    .error-code{font-size:12px;font-weight:600;color:#596579}
    .error-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:20px}
    .error-actions a{display:inline-flex;align-items:center;min-height:44px;padding:10px 16px;border:1px solid #3e60d5;border-radius:4px;text-decoration:none;transition:background-color 160ms ease}
    .error-actions a:first-child{background:#3e60d5;color:white}
    .error-actions a:first-child:hover{background:#283d85}
    @media(max-width:660px){main{margin:24px 12px;padding:20px}}
    @media(prefers-reduced-motion:reduce){.error-actions a{transition:none}}
  </style>
</head>
<body>
  <main aria-labelledby="errorHeading">
    <p class="error-code">AquaDissect / @yield('code')</p>
    <h1 id="errorHeading">@yield('title')</h1>
    <p>@yield('message')</p>
    <div class="error-actions">
      <a href="{{ auth()->check() ? route('portal.home') : route('login') }}">{{ auth()->check() ? 'Return to dashboard' : 'Sign in' }}</a>
      <a href="{{ url('/help') }}">Get help</a>
    </div>
  </main>
</body>
</html>
