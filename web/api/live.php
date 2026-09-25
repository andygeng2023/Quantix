<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$symbol=strtoupper(trim($_GET['symbol']??''));
if(!preg_match('/^[A-Z0-9.^_-]{1,15}$/',$symbol)){
  http_response_code(400);
  echo json_encode(['error'=>'Invalid symbol']);
  exit;
}

$url='https://query1.finance.yahoo.com/v8/finance/chart/'.rawurlencode($symbol).'?range=1d&interval=1m&events=div%2Csplits';
$ctx=stream_context_create([
  'http'=>[
    'timeout'=>5,
    'ignore_errors'=>true,
    'header'=>"User-Agent: Quantix/1.0\r\nAccept: application/json\r\n"
  ]
]);
$raw=@file_get_contents($url,false,$ctx);
$j=$raw?json_decode($raw,true):null;
$r=$j['chart']['result'][0]??null;
if(!$r){
  http_response_code(502);
  echo json_encode(['error'=>'Live quote unavailable']);
  exit;
}

$meta=$r['meta']??[];
$q=$r['indicators']['quote'][0]??[];
$last=null;$lastTs=null;
$closes=$q['close']??[];$ts=$r['timestamp']??[];
for($i=count($closes)-1;$i>=0;$i--){
  if($closes[$i]!==null){$last=(float)$closes[$i];$lastTs=isset($ts[$i])?gmdate('c',(int)$ts[$i]):gmdate('c');break;}
}
$prev=null;
for($i=count($closes)-1;$i>=0;$i--){
  if($closes[$i]!==null && $i<(count($closes)-1)){
    $prev=(float)$closes[$i];break;
  }
}
$change=($last!==null&&$prev!==null)?$last-$prev:null;
$changePct=($last!==null&&$prev)?$change/$prev:null;

echo json_encode([
  'symbol'=>$symbol,
  'name'=>$meta['longName']??$meta['shortName']??$symbol,
  'price'=>$last,
  'previous'=>$prev,
  'change'=>$change,
  'change_pct'=>$changePct,
  'timestamp'=>$lastTs,
  'market_time'=>isset($meta['regularMarketTime'])?gmdate('c',(int)$meta['regularMarketTime']):null
],JSON_UNESCAPED_SLASHES);
