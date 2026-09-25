<?php
require __DIR__.'/partials/header.php';
$cache=json_decode(@file_get_contents(__DIR__.'/data/market.json'),true)?:[];
$stocks=$cache['stocks']??[];$analysis=$cache['analysis']??[];
$latest=[];foreach(($cache['prices']??[]) as $p){$k=$p['symbol'];if(!isset($latest[$k])||$p['ts']>$latest[$k]['ts'])$latest[$k]=$p;}
$rows=[];foreach($stocks as $s){$rows[]=array_merge($s,['close'=>$latest[$s['symbol']]['close']??null,'ts'=>$latest[$s['symbol']]['ts']??null]);}
usort($rows,fn($a,$b)=>($b['market_cap']??0)<=>($a['market_cap']??0));
$universeCount=count($stocks);
$research=[];
foreach($analysis as $sym=>$m){
  $trend=$m['trend']??'mixed';$rsi=$m['rsi14']??null;$vol=$m['annualized_volatility']??null;$path=$m['forecast_path_20d']??[];
  $agreement=($trend==='bullish'||$trend==='bearish')?1:0;
  $momentum=isset($m['return_20d'])&&$m['return_20d']>0?1:0;
  $rsiClean=is_numeric($rsi)&&$rsi>35&&$rsi<70?1:0;
  $uncertainty=1;
  if($path&&isset($path[19]['low'],$path[19]['high'],$path[19]['value'])&&$path[19]['value']!=0){
    $uncertainty=max(0,min(1,1-(($path[19]['high']-$path[19]['low'])/($path[19]['value']*2))));
  }
  $research[$sym]=round(($agreement*40+$momentum*25+$rsiClean*20+$uncertainty*15),1);
}
arsort($research);$research=array_slice($research,0,5,true);
?>
<section class="hero"><div><span class="eyebrow">QUANTITATIVE MARKET TERMINAL</span><h1>Research markets without the clutter.</h1><p>Cached history, fundamentals, technical studies, statistical forecasts and model-driven research signals.</p></div><a class="primary" href="screener.php">Open screener</a></section>
<div class="grid three"><article class="card"><small>Universe</small><strong><?=e($universeCount)?></strong><span>Tracked symbols</span></article><article class="card"><small>Data status</small><strong><?=$universeCount?'Ready':'Waiting'?></strong><span><?=$universeCount?'Latest market cache':'No market cache'?></span></article><article class="card"><small>Analysis</small><strong><?=count($analysis)?></strong><span>Models computed</span></article></div>
<section class="section-head"><div><span class="eyebrow">RESEARCH RECOMMENDATIONS</span><h2>Model-screened research candidates</h2><p>These are educational research signals based on scanner/model agreement, not buy or sell recommendations.</p></div></section>
<div class="grid three"><?php foreach($research as $sym=>$score): $m=$analysis[$sym]??[]; ?><a class="card stock-card" href="stock.php?symbol=<?=e($sym)?>"><small><?=e($sym)?></small><strong><?=e($m['trend']??'mixed')?></strong><span>Research signal score <?=e($score)?>/100</span><span>20D model estimate: <?=isset($m['forecast_20d'])?e(number_format($m['forecast_20d'],2)):'—'?></span></a><?php endforeach;if(!$research):?><article class="card"><strong>No signals yet</strong><span>Run the data collector to calculate model signals.</span></article><?php endif;?></div>
<section class="section-head"><div><span class="eyebrow">MARKET BOARD</span><h2>Tracked universe</h2></div></section>
<div class="table-card"><table><thead><tr><th>Symbol</th><th>Price</th><th>Market cap</th><th>P/E</th><th>Updated</th></tr></thead><tbody><?php foreach(array_slice($rows,0,20) as $r):?><tr><td><a href="stock.php?symbol=<?=e($r['symbol'])?>"><b><?=e($r['symbol'])?></b></a></td><td><?=e($r['close']??'—')?></td><td><?=e($r['market_cap']??'—')?></td><td><?=e($r['pe']??'—')?></td><td><?=e($r['ts']??'—')?></td></tr><?php endforeach;if(!$rows):?><tr><td colspan="5">Market cache is empty. Run the Quantix Data Collector workflow.</td></tr><?php endif;?></tbody></table></div>
<?php require __DIR__.'/partials/footer.php'; ?>