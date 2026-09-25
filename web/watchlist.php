<?php
require __DIR__.'/partials/header.php';require_login();
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();$action=$_POST['action']??'';$symbol=strtoupper(trim($_POST['symbol']??''));
    if(!preg_match('/^[A-Z0-9.^_-]{1,20}$/',$symbol))$action='';
    if($action==='add')q('INSERT IGNORE INTO watchlists(user_id,symbol) VALUES(?,?)',[user()['id'],$symbol]);
    if($action==='remove')q('DELETE FROM watchlists WHERE user_id=? AND symbol=?',[user()['id'],$symbol]);
    header('Location: watchlist.php');exit;
}
$rows=q('SELECT w.symbol,s.name,s.sector FROM watchlists w LEFT JOIN stocks s ON s.symbol=w.symbol WHERE w.user_id=? ORDER BY w.created_at DESC',[user()['id']])->fetchAll();$map=quantix_analysis();$prices=quantix_price_map();
?>
<section class="section-head"><div><span class="eyebrow">SAVED RESEARCH</span><h1>Your watchlist</h1><p>A focused workspace for symbols you want to revisit.</p></div></section>
<div class="grid three">
<?php foreach($rows as $r):$m=$map[$r['symbol']]??[];$p=$prices[$r['symbol']]??[];?>
<article class="card stock-card"><div class="row-between"><a href="stock.php?symbol=<?=e($r['symbol'])?>"><b><?=e($r['symbol'])?></b></a><form method="post"><input type="hidden" name="action" value="remove"><input type="hidden" name="symbol" value="<?=e($r['symbol'])?>"><?=csrf_input()?><button class="search-chip" type="submit">Remove</button></form></div><span><?=e($r['name']??'Uncached symbol')?></span><strong><?=quantix_num($p['close']??null)?></strong><span><?=e($m['trend']??'No model')?></span><a href="stock.php?symbol=<?=e($r['symbol'])?>">Open research →</a></article>
<?php endforeach;if(!$rows):?><article class="card empty"><b>Your watchlist is empty.</b><span>Open a symbol and save it for later research.</span></article><?php endif;?>
</div>
<?php require __DIR__.'/partials/footer.php'; ?>
