@if(\App\Support\GoogleAccess::ready())
<a class="portal-secondary portal-full google-access" href="{{ route('google.redirect', ['intent' => $intent]) }}">
  <svg width="20" height="20" viewBox="0 0 48 48" aria-hidden="true"><path fill="#4285F4" d="M43.6 24.5c0-1.4-.1-2.7-.4-4H24v7.6h11a9.4 9.4 0 0 1-4.1 6.2v5h6.6c3.9-3.6 6.1-8.8 6.1-14.8Z"/><path fill="#34A853" d="M24 44c5.5 0 10.1-1.8 13.5-4.7l-6.6-5c-1.8 1.2-4.1 1.9-6.9 1.9-5.3 0-9.8-3.6-11.4-8.4H5.8V33A20.4 20.4 0 0 0 24 44Z"/><path fill="#FBBC05" d="M12.6 27.8a12 12 0 0 1 0-7.6V15H5.8a20 20 0 0 0 0 18l6.8-5.2Z"/><path fill="#EA4335" d="M24 11.8c3 0 5.7 1 7.8 3.1l5.8-5.8A19.5 19.5 0 0 0 24 4 20.4 20.4 0 0 0 5.8 15l6.8 5.2C14.2 15.4 18.7 11.8 24 11.8Z"/></svg>
  <span>{{ $label ?? 'Continue with Google' }}</span>
</a>
<p class="portal-muted google-access-note">{{ $intent === 'register' ? 'Choose the Google email you will use for your instructor request.' : 'Choose the Google email on your AquaDissect account record.' }} An internet connection is required.</p>
@endif
@if(session('google_message'))
<p class="portal-message" role="alert">{{ session('google_message') }}</p>
@endif
