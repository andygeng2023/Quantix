<?php
require __DIR__.'/partials/header.php';
$mincap=(float)($_GET['mincap']??0);$maxpe=(float)($_GET['maxpe']??0);$rows=[];
try{$sql='SELECT * FROM stocks WHERE 1';$args=[];if($mincap>0){$sql.=' AND market_cap>=?';$args[]=$mincap;}if($maxpe>0){$sql.=' AND pe<=? AND pe>0';$args[]=$maxpe;}$sql.=' ORDER BY market_cap DESC LIMIT 200';$rows=q($sql,$args)->fetchAll();}catch(Throwable $x){}
if(!$rows){
  $cache=json_decode(@file_get_contents(__DIR__.'/data/market.json'),true)?:[];
  $rows=$cache['stocks']??[];
  $rows=array_filter($rows,function($r)use($mincap,$maxpe){return (!$mincap||($r['market_cap']??0)>=$mincap)&&(!$maxpe||(($r['pe']??0)>0&&($r['pe']??0)<=$maxpe));});
  usort($rows,fn($a,$b)=>($b['market_cap']??0)<=>($a['market_cap']??0));$rows=array_slice($rows,0,200);
}
?>
<section class="section-head"><div><span class="eyebrow">QUANTITATIVE SCREEN</span><h1>Universe screener</h1></div></section><form class="filterbar card"><label>Min market cap<input type="number" name="mincap" value="<?=e($_GET['mincap']??'')?>"></label><label>Max P/E<input type="number" name="maxpe" value="<?=e($_GET['maxpe']??'')?>"></label><button class="primary">Apply filters</button></form><div class="table-card"><table><thead><tr><th>Symbol</th><th>Market cap</th><th>P/E</th><th>Revenue growth</th><th>EPS growth</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><a href="stock.php?symbol=<?=e($r['symbol'])?>"><b><?=e($r['symbol'])?></b></a></td><td><?=e($r['market_cap']??'—')?></td><td><?=e($r['pe']??'—')?></td><td><?=e($r['revenue_growth']??'—')?></td><td><?=e($r['eps_growth']??'—')?></td></tr><?php endforeach;if(!$rows):?><tr><td colspan="5">No symbols match the current filters.</td></tr><?php endif;?></tbody></table></div><?php require __DIR__.'/partials/footer.php'; ?>