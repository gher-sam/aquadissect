<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="Content-Security-Policy" content="connect-src 'none'; form-action 'none'">
  <meta name="description" content="Try AquaDissect’s First Dive and Return Dive with sample questions and compare your own scores.">
  <title>Reef Quest student demo | AquaDissect</title>
  <link rel="icon" href="{{ asset('pwa-icons/icon-192.png') }}">
  @vite('resources/js/reefAssessmentDemo.js')
</head>
<body>
  <header class="demo-mast"><div>
    <span class="demo-brand"><img src="{{ asset('pwa-icons/icon-192.png') }}" alt="" width="36" height="36">AquaDissect</span>
    <span class="demo-tag">Student demo</span>
  </div></header>
  <main class="demo-wrap">
    <section class="demo-intro" aria-labelledby="demo-title">
      <div><h1 id="demo-title">Reef Quest</h1>
        <p>Take two short dives into fish anatomy and see how your score changes.</p></div>
      <div class="demo-sea" aria-hidden="true"><span data-fish></span><i></i><i></i><i></i></div>
    </section>
    <ol class="demo-steps" aria-label="Your dive sequence">
      <li data-step="pre"><span aria-hidden="true">1</span>First Dive</li>
      <li data-step="lesson"><span aria-hidden="true">2</span>Sample lesson</li>
      <li data-step="post"><span aria-hidden="true">3</span>Return Dive</li>
    </ol>
    <section class="demo-card" id="demo-stage" aria-labelledby="stage-title">
      <h2 id="stage-title">First Dive</h2><p>Four sample questions about fish anatomy.</p>
      <noscript>Turn on JavaScript in your browser to try the two dives.</noscript>
    </section>
    <p class="demo-privacy">Your answers and scores stay in this tab. Refresh the page for a fresh start.</p>
  </main>
</body>
</html>
