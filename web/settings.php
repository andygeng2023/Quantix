<?php require __DIR__.'/partials/header.php';?>
<section class="section-head"><div><span class="eyebrow">PREFERENCES</span><h1>Settings</h1><p>Interface preferences are stored locally in this browser.</p></div></section>
<div class="grid two">
<article class="card settings-card"><div class="card-title"><b>Appearance</b><span>Interface</span></div><label>Theme<select id="settingTheme"><option value="dark">Dark</option><option value="light">Light</option><option value="system">System</option></select></label><br><label>Density<select id="settingDensity"><option value="comfortable">Comfortable</option><option value="compact">Compact</option></select></label></article>
<article class="card settings-card"><div class="card-title"><b>Charts & refresh</b><span>Browser</span></div><label>Chart range<select id="settingRange"><option value="90">90 sessions</option><option value="180">180 sessions</option><option value="250">250 sessions</option></select></label><br><label>Page refresh<select id="settingRefresh"><option value="0">Off</option><option value="15">Every 15 minutes</option><option value="30">Every 30 minutes</option></select></label></article>
</div>
<div class="grid three">
<article class="card"><span>Full market analytics</span><strong>Scheduled</strong><small>GitHub Actions generates the research cache and sends it to the site.</small></article>
<article class="card"><span>Live quotes</span><strong>3 sec</strong><small>Symbol pages poll while visible and back off after errors.</small></article>
<article class="card"><span>Execution</span><strong>None</strong><small>Quantix is a research and analytics interface only.</small></article>
</div>
<div class="grid two">
<article class="card"><div class="card-title"><b>Model transparency</b><span>Visible</span></div><p class="muted">The current ensemble combines short and medium log-price trends, then reports uncertainty and historical walk-forward diagnostics.</p><div class="model-strip"><div><small>5D</small><strong>Forecast</strong></div><div><small>20D</small><strong>Forecast</strong></div><div><small>OOS</small><strong>Validation</strong></div></div></article>
<article class="card"><div class="card-title"><b>Data behavior</b><span>Resilient</span></div><p class="muted">Pages prefer the static cache for speed. Uncached symbols can attempt a separate one-ticker request. If a live request fails, the page continues using cached data.</p><div class="model-strip"><div><small>Universe</small><strong>500+</strong></div><div><small>History</small><strong>1 year</strong></div><div><small>Cache</small><strong>Static</strong></div></div></article>
</div>
<div class="card settings-footer"><button class="secondary" id="resetSettings" type="button">Reset preferences</button><span id="settingsSaved" class="muted">Changes save automatically.</span></div>
<?php require __DIR__.'/partials/footer.php'; ?>