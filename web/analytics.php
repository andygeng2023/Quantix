<?php
require __DIR__.'/partials/header.php';
$analysis=quantix_analysis();
function avg_metric($rows,$key){$v=[];foreach($rows as $r)if(isset($r[$key])&&is_numeric($r[$key]))$v[]=(float)$r[$key];return $v?array_sum($v)/count($v):null;}
$all5=[];$all20=[];foreach($analysis as $m){if(!empty($m['backtest']['5d']))$all5[]=$m['backtest']['5d'];if(!empty($m['backtest']['20d']))$all20[]=$m['backtest']['20d']);}
$bt5=['directional_accuracy'=>avg_metric($all5,'directional_accuracy'),'interval_coverage'=>avg_metric($all5,'interval_coverage'),'mae'=>avg_metric($all5,'mae'),'rmse'=>avg_metric($all5,'rmse')];
$bt20=['directional_accuracy'=>avg_metric($all20,'directional_accuracy'),'interval_coverage'=>avg_metric($all20,'interval_coverage'),'mae'=>avg_metric($all20,'mae'),'rmse'=>avg_metric($all20,'rmse')];
$conf=[];foreach($analysis as $m)if(is_numeric($m['model_confidence']??null))$conf[]=$m['model_confidence'];$avgConf=$conf?array_sum($conf)/count($conf):null;
?>
<section class="section-head"><div><span class="eyebrow">RESEARCH LAB</span><h1>Models & validation</h1><p>Inspect model behavior, historical walk-forward tests and current diagnostics.</p></div></section>
<div class="grid four">
<?php foreach([['5D direction',$bt5['directional_accuracy']],['20D direction',$bt20['directional_accuracy']],['5D coverage',$bt5['interval_coverage']],['20D coverage',$bt20['interval_coverage']]] as [$label,$v]):?><article class="card stat-card"><span><?=$label?></span><strong><?=quantix_pct($v)?></strong><small>Average across symbols with tests</small></article><?php endforeach;?>
</div>
<div class="grid three"><article class="card"><span>Model family</span><strong>Ensemble</strong><small>20-session + 60-session log-trend regressions</small></article><article class="card"><span>Average confidence</span><strong><?=quantix_pct($avgConf)?></strong><small>Heuristic uncertainty measure</small></article><article class="card"><span>Coverage target</span><strong>95%</strong><small>Residual-based forecast interval target</small></article></div>
<article class="card"><div class="card-title"><b>Walk-forward validation</b><span>Historical out-of-sample tests</span></div><p class="muted">Each forecast is generated without using observations after its forecast origin. Aggregate figures are simple averages and should be interpreted as diagnostics rather than guarantees.</p><div class="table-card"><table><thead><tr><th>Horizon</th><th>Tests</th><th>MAE</th><th>RMSE</th><th>Direction</th><th>Coverage</th></tr></thead><tbody>
<?php foreach([['5 sessions',$all5,$bt5],['20 sessions',$all20,$bt20]] as [$label,$rows,$m]):?><tr><td><?=$label?></td><td><?=count($rows)?></td><td><?=quantix_num($m['mae'])?></td><td><?=quantix_num($m['rmse'])?></td><td><?=quantix_pct($m['directional_accuracy'])?></td><td><?=quantix_pct($m['interval_coverage'])?></td></tr><?php endforeach;?>
</tbody></table></div></article>
<div class="table-card"><table><thead><tr><th>Symbol</th><th>Trend</th><th>20D return</th><th>Volatility</th><th>Drawdown</th><th>RSI</th><th>Confidence</th><th>20D estimate</th></tr></thead><tbody>
<?php foreach($analysis as $sym=>$m):?><tr><td><a href="stock.php?symbol=<?=e($sym)?>"><b><?=e($sym)?></b></a></td><td><?=e($m['trend']??'—')?></td><td><?=quantix_pct($m['return_20d']??null)?></td><td><?=quantix_pct($m['annualized_volatility']??null)?></td><td><?=quantix_pct($m['max_drawdown']??null)?></td><td><?=quantix_num($m['rsi14']??null,1)?></td><td><?=quantix_pct($m['model_confidence']??null)?></td><td><?=quantix_num($m['forecast_20d']??null)?></td></tr><?php endforeach;if(!$analysis):?><tr><td colspan="8">No analysis cache is available.</td></tr><?php endif;?>
</tbody></table></div>
<div class="notice">Quantix forecasts are statistical research outputs. Historical validation does not establish future performance.</div>
<?php require __DIR__.'/partials/footer.php'; ?>