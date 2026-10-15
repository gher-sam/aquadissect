# Rabbitfish card preview playback correction

October 15, 2026

The prior browser-recorded preview contained only 12 decoded frames in 9.784 seconds. The preserved AI-generated source contains 240 frames at 24 fps. Rebuilt the separate card preview directly from that original with FFmpeg, keeping 24 fps and a 19-frame closing crossfade into the opening segment. The original is preserved outside the release.

The replacement is silent H.264/yuv420p at 1280 x 720, with MP4 fast-start metadata. Full decoding verified 221 frames over 9.208 seconds and constant frame timing (vfrdet 0/220). A sampled contact sheet was visually inspected. SHA-256: `80dd9431e4b8e436fd0322242350c78d07c9f1f87e7eb1e6bdd60bbfc6a97010`.

This changes only the separately labeled AI-generated presentation preview. Existing package authorization, faculty-review status, publication requirements, controls, reduced-motion behavior and poster fallback are retained. These are engineering checks, not scientific or faculty validation.
