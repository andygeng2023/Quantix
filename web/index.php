<?php
require __DIR__.'/partials/header.php';
$rows=[];
try{$rows=q("SELECT s.*,p.close,p.ts FROM stocks s LEFT JOIN prices p ON p.symbol=s.symbol AND p.ts=(SELECT MAX(ts) FROM prices x WHERE x.symbol=s.symbol) ORDER BY s.market_cap DESC LIMIT 12")->fetchAll();}catch(Throwable $x){}
if(!$rows){
  $cache=json_decode(@file_get_contents(__DIR__.'/data/market.json'),true);
  $latest=[];
  foreach(($cache['prices']??[]) as $p){$k=$p['symbol'];if(!isset($latest[$k])||$p['ts']>$latest[$k]['ts'])$latest[$k]=$p;}
  foreach(($cache['stocks']??[]) as $s){$p=$latest[$s['symbol']]??[];$rows[]=array_merge($s,['close'=>$p['close']??null,'ts'=>$p['ts']??null]);}
  usort($rows,function($a,$b){return ($b['market_cap']??0)<=>($a['market_cap']??0);});
  $rows=array_slice($rows,0,12);
}
?>
<section class="hero"><div><span class="eyebrow">QUANTITATIVE MARKET TERMINAL</span><h1>Research markets without the clutter.</h1><p>Fast local analytics, cached history, fundamentals and technical studies.</p></div><a class="primary" href="screener.php">Open screener</a></section>
<div class="grid three"><article class="card"><small>Universe</small><strong><?=count($rows)?count($rows):'—'?></strong><span>Cached symbols</span></article><article class="card"><small>Data status</small><strong><?=count($rows)?'Ready':'Waiting'?></strong><span><?=count($rows)?'Latest market cache':'No market cache'?></span></article><article class="card"><small>Mode</small><strong>Research</strong><span>Historical analytics</span></article></div>
<section class="section-head"><div><span class="eyebrow">MARKET BOARD</span><h2>Tracked universe</h2></div></section>
<div class="table-card"><table><thead><tr><th>Symbol</th><th>Price</th><th>Market cap</th><th>P/E</th><th>Updated</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><a href="stock.php?symbol=<?=e($r['symbol'])?>"><b><?=e($r['symbol'])?></b></a></td><td><?=e($r['close']??'—')?></td><td><?=e($r['market_cap']??'—')?></td><td><?=e($r['pe']??'—')?></td><td><?=e($r['ts']??'—')?></td></tr><?php endforeach;if(!$rows):?><tr><td colspan="5">Market cache is empty. Run the Quantix Data Collector workflow.</td></tr><?php endif;?></tbody></table></div>
<?php require __DIR__.'/partials/footer.php'; ?>