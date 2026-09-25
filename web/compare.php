<?php
require __DIR__.'/partials/header.php';
$cache=quantix_cache();$analysis=$cache['analysis'];$map=quantix_stock_map();
$symbols=array_values(array_unique(array_filter(array_map(fn($x)=>strtoupper(trim($x)),explode(',',$_GET['symbols']??'AAPL,MSFT,NVDA,GOOGL')))));
$symbols=array_slice($symbols,0,6);
?>
<section class="section-head"><div><span class="eyebrow">EXPLORE</span><h1>Compare research</h1><p>Side-by-side metrics for up to six symbols.</p></div></section>
<form class="filterbar card" style="grid-template-columns:1fr auto"><label>Symbols<input name="symbols" value="<?=e(implode(',',$symbols))?>" placeholder="AAPL,MSFT,NVDA"></label><button class="primary">Compare</button></form>
<div class="table-card"><table><thead><tr><th>Metric</th><?php foreach($symbols as $s):?><th><?=e($s)?></th><?php endforeach;?></tr></thead><tbody>
<?php $metrics=[['last','Last price','num'],['return_20d','20D return','pct'],['return_60d','60D return','pct'],['annualized_volatility','Annualized volatility','pct'],['max_drawdown','Max drawdown','pct'],['rsi14','RSI(14)','num1'],['sharpe','Sharpe','num'],['sortino','Sortino','num'],['forecast_20d','20D model estimate','num'],['forecast_20d_low','20D interval low','num'],['forecast_20d_high','20D interval high','num'],['model_confidence','Model confidence','pct'],['market_cap','Market cap','money'],['pe','P/E','num']]; foreach($metrics as [$key,$label,$fmt]):?>
<tr><td><?=$label?></td><?php foreach($symbols as $s):$m=$analysis[$s]??[];$v=$key==='market_cap'?($map[$s]['market_cap']??null):($key==='pe'?($map[$s]['pe']??null):($m[$key]??null));?><td><?php if($fmt==='pct')echo quantix_pct($v);elseif($fmt==='money')echo quantix_money($v);elseif($fmt==='num1')echo quantix_num($v,1);else echo quantix_num($v);?></td><?php endforeach;?></tr>
<?php endforeach;?>
</tbody></table></div>
<div class="notice">Comparison is descriptive. It does not select a preferred security or provide a personalized recommendation.</div>
<?php require __DIR__.'/partials/footer.php'; ?>