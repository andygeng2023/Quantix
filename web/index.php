<?php
require __DIR__.'/partials/header.php';
$stocks=quantix_stocks(); $analysis=quantix_analysis(); $prices=quantix_prices(); $status=quantix_status(); $generated=quantix_generated_at();
$latest=quantix_price_map(); $rows=[];
foreach($stocks as $s){$sym=$s['symbol']??'';$rows[]=array_merge($s,$latest[$sym]??[],['metrics'=>$analysis[$sym]??[]]);}
usort($rows,fn($a,$b)=>(($b['market_cap']??0)<=>($a['market_cap']??0)));
$bull=$bear=$mixed=0; foreach($analysis as $m){$t=$m['trend']??'mixed'; if($t==='bullish')$bull++; elseif($t==='bearish')$bear++; else $mixed++;}
$research=[];
foreach($analysis as $sym=>$m){
  $confidence=is_numeric($m['model_confidence']??null)?(float)$m['model_confidence']:0;
  $momentum=is_numeric($m['return_20d']??null)?max(0,min(1,($m['return_20d']+0.10)/0.20)):0.5;
  $trend=($m['trend']??'mixed')==='mixed'?0.5:1;
  $research[$sym]=round(100*(.35*$trend+.25*$momentum+.25*$confidence+.15*(($m['volatility_regime']??'normal')==='normal'?1:.6)),1);
}
arsort($research); $research=array_slice($research,0,6,true);
?>
<section class="hero">
  <div class="hero-copy">
    <span class="eyebrow">QUANTITATIVE RESEARCH TERMINAL</span>
    <h1>Research the market without the clutter.</h1>
    <p>Quantix combines a cached research universe, live quote polling, screening, model forecasts and walk-forward validation in one compact workspace.</p>
    <div class="hero-actions"><a class="primary" href="screener.php">Explore universe</a><a class="secondary" href="analytics.php">Open model lab</a></div>
  </div>
  <article class="card status-card">
    <div><span class="eyebrow">DATA PIPELINE</span><strong class="status-<?=$status['tone']?>"><?=e($status['label'])?></strong></div>
    <div class="status-meta"><span><?=count($stocks)?> symbols</span><span><?=count($analysis)?> models</span></div>
    <span><?=e($generated?date('M j, Y · H:i',strtotime($generated)):'No cache timestamp')?></span>
  </article>
</section>

<div class="grid four">
  <article class="card stat-card"><span>Universe</span><strong><?=count($stocks)?></strong><small>Tracked research symbols</small></article>
  <article class="card stat-card"><span>Analyses</span><strong><?=count($analysis)?></strong><small>Computed model records</small></article>
  <article class="card stat-card"><span>Bullish state</span><strong><?=number_format(count($analysis)?$bull/count($analysis)*100:0,0)?>%</strong><small><?=$bull?> of <?=$bull+$bear+$mixed?></small></article>
  <article class="card stat-card"><span>Cache age</span><strong><?=is_numeric($status['age'])?($status['age']<60?number_format($status['age'],0).'m':number_format($status['age']/60,1).'h'):'—'?></strong><small>Full analytics refresh</small></article>
</div>

<section class="section-head"><div><span class="eyebrow">WORKSPACE</span><h2>Choose a research task</h2><p>Each area has a distinct purpose.</p></div></section>
<div class="quick-grid">
  <a class="card action-card" href="screener.php"><b>Explore</b><span>Search the universe, filter model states and open symbols.</span><em>→</em></a>
  <a class="card action-card" href="compare.php"><b>Compare</b><span>Place several symbols side by side across market and model metrics.</span><em>→</em></a>
  <a class="card action-card" href="analytics.php"><b>Research</b><span>Inspect forecast quality, interval coverage and model diagnostics.</span><em>→</em></a>
  <a class="card action-card" href="watchlist.php"><b>Saved</b><span>Keep a focused list of symbols you are studying.</span><em>→</em></a>
</div>

<section class="section-head"><div><span class="eyebrow">MODEL MONITOR</span><h2>Research candidates</h2><p>Quantitative screening context only; not trading instructions.</p></div><a href="analytics.php">View validation →</a></section>
<div class="grid three">
<?php foreach($research as $sym=>$score): $m=$analysis[$sym]??[]; ?>
<a class="card stock-card" href="stock.php?symbol=<?=e($sym)?>">
  <div class="row-between"><b><?=e($sym)?></b><span class="badge"><?=e($m['trend']??'mixed')?></span></div>
  <strong><?=number_format($score,1)?><small>/100 research fit</small></strong>
  <span>20D model: <?=quantix_num($m['forecast_20d']??null)?></span>
  <span>Confidence: <?=is_numeric($m['model_confidence']??null)?number_format($m['model_confidence']*100,0).'%':'—'?></span>
</a>
<?php endforeach; if(!$research): ?><article class="card empty"><b>No model records available.</b><span>Quantix is waiting for a valid market cache.</span></article><?php endif; ?>
</div>

<section class="section-head"><div><span class="eyebrow">MARKET BOARD</span><h2>Largest tracked symbols</h2></div><a href="screener.php">Open screener →</a></section>
<div class="table-card"><table><thead><tr><th>Symbol</th><th>Price</th><th>Trend</th><th>20D return</th><th>Volatility</th><th>RSI</th></tr></thead><tbody>
<?php foreach(array_slice($rows,0,14) as $r): $m=$r['metrics']; ?>
<tr><td><a href="stock.php?symbol=<?=e($r['symbol'])?>"><b><?=e($r['symbol'])?></b></a></td><td><?=quantix_num($r['close']??null)?></td><td><?=e($m['trend']??'—')?></td><td class="<?=quantix_change_class($m['return_20d']??null)?>"><?=quantix_pct($m['return_20d']??null)?></td><td><?=quantix_pct($m['annualized_volatility']??null)?></td><td><?=quantix_num($m['rsi14']??null,1)?></td></tr>
<?php endforeach; if(!$rows): ?><tr><td colspan="6">No market cache is available yet.</td></tr><?php endif; ?>
</tbody></table></div>
<div class="notice">Full analytics are refreshed by the scheduled data pipeline. Individual stock pages also poll a live quote separately when available.</div>
<?php require __DIR__.'/partials/footer.php'; ?>