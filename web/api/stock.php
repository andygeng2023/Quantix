<?php
require __DIR__.'/../config/app.php';
header('Content-Type: application/json');
$s=strtoupper(trim($_GET['symbol']??'AAPL'));
try{
  $stock=q('SELECT * FROM stocks WHERE symbol=?',[$s])->fetch();
  $prices=q('SELECT ts,open,high,low,close,volume FROM prices WHERE symbol=? ORDER BY ts DESC LIMIT 500',[$s])->fetchAll();
}catch(Throwable $x){$stock=null;$prices=[];}
if(!$stock||!$prices){
  $cache=json_decode(@file_get_contents(__DIR__.'/../data/market.json'),true)?:[];
  foreach(($cache['stocks']??[]) as $row) if(($row['symbol']??'')===$s){$stock=$row;break;}
  $prices=array_values(array_filter($cache['prices']??[],fn($p)=>($p['symbol']??'')===$s));
  usort($prices,fn($a,$b)=>strcmp($a['ts'],$b['ts']));
  $prices=array_slice($prices,-500);
}
echo json_encode(['stock'=>$stock,'prices'=>$prices]);
