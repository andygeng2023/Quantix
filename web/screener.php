<?php
require __DIR__.'/partials/header.php';
$stocks=quantix_stocks();$analysis=quantix_analysis();$q=trim($_GET['q']??'');$trend=$_GET['trend']??'all';$regime=$_GET['regime']??'all';$sort=$_GET['sort']??'market_cap';
$rows=array_values(array_filter($stocks,function($r)use($q,$trend,$regime,$analysis){$s=$r['symbol']??'';$m=$analysis[$s]??[];$hay=strtolower($s.' '.($r['name']??'').' '.($r['sector']??''));return (!$q||str_contains($hay,strtolower($q)))&&($trend==='all'||($m['trend']??'')===$trend)&&($regime==='all'||($m['volatility_regime']??'')===$regime);}));
$sorters=['market_cap'=>'market_cap','return_20d'=>'return_20d','volatility'=>'annualized_volatility','rsi'=>'rsi14'];
usort($rows,function($a,$b)use($sort,$analysis,$sorters){$k=$sorters[$sort]??'market_cap';$av=$sort==='market_cap'?($a[$k]??0):($analysis[$a['symbol']][$k]??0);$bv=$sort==='market_cap'?($b[$k]??0):($analysis[$b['symbol']][$k]??0);return ($bv??0)<=>($av??0);});
?>
<section class="section-head"><div><span class="eyebrow">EXPLORE</span><h1>Universe scanner</h1><p>Filter the cached research universe without leaving the workspace.</p></div><a href="compare.php">Compare →</a></section>
<form class="filterbar card">
<label>Search<input name="q" value="<?=e($q)?>" placeholder="Ticker, company or sector"></label>
<label>Trend<select name="trend"><option value="all">All trends</option><?php foreach(['bullish','mixed','bearish'] as $x):?><option value="<?=$x?>" <?=$trend===$x?'selected':''?>><?=ucfirst($x)?></option><?php endforeach;?></select></label>
<label>Volatility<select name="regime"><option value="all">All regimes</option><?php foreach(['low','normal','high'] as $x):?><option value="<?=$x?>" <?=$regime===$x?'selected':''?>><?=ucfirst($x)?></option><?php endforeach;?></select></label>
<label>Sort<select name="sort"><?php foreach(['market_cap'=>'Market cap','return_20d'=>'20D return','volatility'=>'Volatility','rsi'=>'RSI'] as $k=>$v):?><option value="<?=$k?>" <?=$sort===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></label>
<button class="primary">Apply</button>
</form>
<div class="toolbar"><span><b><?=count($rows)?></b> matching symbols</span><span class="muted">Showing up to 500 cached symbols. Metrics are research outputs, not trading recommendations.</span></div>
<div class="table-card"><table><thead><tr><th>Symbol</th><th>Company</th><th>Sector</th><th>Trend</th><th>20D</th><th>Volatility</th><th>RSI</th><th>Regime</th></tr></thead><tbody>
<?php foreach(array_slice($rows,0,500) as $r):$m=$analysis[$r['symbol']]??[];?>
<tr><td><a href="stock.php?symbol=<?=e($r['symbol'])?>"><b><?=e($r['symbol'])?></b></a></td><td><?=e($r['name']??'—')?></td><td><?=e($r['sector']??'—')?></td><td><?=e($m['trend']??'—')?></td><td class="<?=quantix_change_class($m['return_20d']??null)?>"><?=quantix_pct($m['return_20d']??null)?></td><td><?=quantix_pct($m['annualized_volatility']??null)?></td><td><?=quantix_num($m['rsi14']??null,1)?></td><td><?=e($m['volatility_regime']??'—')?></td></tr>
<?php endforeach;if(!$rows):?><tr><td colspan="8">No symbols match these filters.</td></tr><?php endif;?>
</tbody></table></div>
<?php require __DIR__.'/partials/footer.php'; ?>