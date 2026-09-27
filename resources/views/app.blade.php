<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta
      name="description"
      content="AquaDissect is a guided aquatic anatomy learning and dissection system with secure role-based access."
    />
    <meta name="theme-color" content="#0e315b" />
    <meta name="application-name" content="AquaDissect" />
    <meta name="mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
    <meta name="apple-mobile-web-app-title" content="AquaDissect" />
    <link rel="manifest" href="{{ rtrim(request()->getBaseUrl(), '/') }}/manifest.webmanifest" />
    <link rel="apple-touch-icon" href="{{ rtrim(request()->getBaseUrl(), '/') }}/pwa-icons/apple-touch-icon.png" />
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='18' fill='%230140c1'/%3E%3Cpath d='M14 33c9-13 24-15 34-5l7-6v20l-7-6c-10 10-25 8-34-3Z' fill='white'/%3E%3Ccircle cx='40' cy='29' r='2' fill='%230d1442'/%3E%3C/svg%3E" />
    <title>AquaDissect | Guided Aquatic Anatomy Learning</title>
    <script>
      window.AQUADISSECT_PAGE = @json($page);
      window.AQUADISSECT_ROUTES = @json(\App\Support\PortalPages::urls());
      window.AQUADISSECT_BASE_URL = @json(rtrim(request()->getBaseUrl(), '/'));
      window.AQUADISSECT_BOOTSTRAP = @json($bootstrap);
    </script>
    @vite('resources/js/app.js')
  </head>
  <body>
    <a class="skip-link" id="primarySkipLink" href="#portal-main">Skip to main content</a>
    @include('partials.portal')
    <div id="app"></div>
  </body>
</html>
