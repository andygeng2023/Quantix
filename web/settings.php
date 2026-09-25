<?php require __DIR__.'/partials/header.php';?>
<section class="section-head"><div><span class="eyebrow">CONFIGURATION</span><h1>Settings</h1><p>Interface preferences are saved locally in this browser.</p></div></section>
<div class="grid two">
<article class="card"><h3>Appearance</h3><label>Theme<select id="settingTheme"><option value="system">System</option><option value="light">Light</option><option value="dark">Dark</option></select></label><label>Density<select id="settingDensity"><option value="comfortable">Comfortable</option><option value="compact">Compact</option></select></label></article>
<article class="card"><h3>Charts & data</h3><label>Default chart range<select id="settingRange"><option value="90">90 sessions</option><option value="180">180 sessions</option><option value="250">250 sessions</option></select></label><label>Auto-refresh<select id="settingRefresh"><option value="0">Off</option><option value="15">Every 15 minutes</option><option value="30">Every 30 minutes</option></select></label></article>
</div>
<div class="card"><h3>Search & data behavior</h3><p>Quantix keeps the main universe in its generated cache. If a searched ticker is not cached, the Stock page attempts a live one-ticker market-data request.</p><button class="secondary" id="resetSettings" type="button">Reset preferences</button></div>
<?php require __DIR__.'/partials/footer.php'; ?>