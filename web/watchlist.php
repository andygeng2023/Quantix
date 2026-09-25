<?php
require __DIR__.'/partials/header.php';require_login();
$add=strtoupper(trim($_GET['add']??''));$remove=strtoupper(trim($_GET['remove']??''));
if($add&&preg_match('/^[A-Z0-9.^_-]{1,20}$/',$add))q('INSERT IGNORE INTO watchlists(user_id,symbol) VALUES(?,?)',[user()['id'],$add]);
if($remove&&preg_match('/^[A-Z0-9.^_-]{1,20}$/',$remove))q('DELETE FROM watchlists WHERE user_id=? AND symbol=?',[user()['id'],$remove]);
$rows=q('SELECT w.symbol,s.name,s.sector FROM watchlists w LEFT JOIN stocks s ON s.symbol=w.symbol WHERE w.user_id=? ORDER BY w.created_at DESC',[user()['id']])->fetchAll();
$map=quantix_analysis();$prices=quantix_price_map();
?>
<section class="section-head"><div><span class="eyebrow">SAVED RESEARCH</span><h1>Your watchlist</h1><p>A focused workspace for symbols you want to revisit.</p></div></section>
<div class="grid three">
<?php foreach($rows as $r):$m=$map[$r['symbol']]??[];$p=$prices[$r['symbol']]??[];?>
<article class="card stock-card"><div class="row-between"><a href="stock.php?symbol=<?=e($r['symbol'])?>"><b><?=e($r['symbol'])?></b></a><a class="search-chip" href="watchlist.php?remove=<?=urlencode($r['symbol'])?>">Remove</a></div><span><?=e($r['name']??'Uncached symbol')?></span><strong><?=quantix_num($p['close']??null)?></strong><span><?=e($m['trend']??'No model')?></span><a href="stock.php?symbol=<?=e($r['symbol'])?>">Open research →</a></article>
<?php endforeach;if(!$rows):?><article class="card empty"><b>Your watchlist is empty.</b><span>Open a symbol and save it for later research.</span></article><?php endif;?>
</div>
<?php require __DIR__.'/partials/footer.php'; ?>