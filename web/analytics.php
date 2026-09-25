<?php
require __DIR__.'/partials/header.php';
$cache=json_decode(@file_get_contents(__DIR__.'/data/market.json'),true)?:[];
$stocks=$cache['stocks']??[];$analysis=$cache['analysis']??[];
$bySymbol=[];foreach($stocks as $s)$bySymbol[$s['symbol']]=$s;
?>
<section class="section-head"><div><span class="eyebrow">QUANT ANALYTICS</span><h1>Analytics & Forecasts</h1><p>Rule-based technical and statistical analysis of the cached historical series. Forecasts are model estimates, not guaranteed outcomes.</p></div></section>
<div class="table-card"><table><thead><tr><th>Symbol</th><th>Trend</th><th>20D return</th><th>Volatility</th><th>Drawdown</th><th>RSI</th><th>20D forecast</th><th>95% interval</th></tr></thead><tbody>
<?php foreach($analysis as $sym=>$m): $f=$m['forecast_20d']??null;$lo=$m['forecast_20d_low']??null;$hi=$m['forecast_20d_high']??null; ?>
<tr><td><a href="stock.php?symbol=<?=e($sym)?>"><b><?=e($sym)?></b></a></td><td><?=e($m['trend']??'—')?></td><td><?=isset($m['return_20d'])?number_format($m['return_20d']*100,1).'%':'—'?></td><td><?=isset($m['annualized_volatility'])?number_format($m['annualized_volatility']*100,1).'%':'—'?></td><td><?=isset($m['max_drawdown'])?number_format($m['max_drawdown']*100,1).'%':'—'?></td><td><?=isset($m['rsi14'])?number_format($m['rsi14'],1):'—'?></td><td><?=is_numeric($f)?number_format($f,2):'—'?></td><td><?=is_numeric($lo)&&is_numeric($hi)?number_format($lo,2).' – '.number_format($hi,2):'—'?></td></tr>
<?php endforeach;if(!$analysis):?><tr><td colspan="8">Run the Quantix Data Collector to generate analysis.</td></tr><?php endif;?></tbody></table></div>
<div class="grid three"><?php foreach(['5d'=>'forecast_5d','20d'=>'forecast_20d'] as $label=>$key): ?><article class="card"><small><?=$label?> statistical forecast</small><strong><?=count($analysis)?'Generated':'—'?></strong><span>Log-linear trend model with a 95% residual-based interval.</span></article><?php endforeach; ?><article class="card"><small>Universe</small><strong><?=count($analysis)?></strong><span>Symbols with computed metrics</span></article></div>
<?php require __DIR__.'/partials/footer.php'; ?>