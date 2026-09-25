<?php
require __DIR__.'/../config/app.php';
$cfg=require __DIR__.'/../config/database.php';

function import_payload($payload){
    $count=0;
    foreach(($payload['stocks']??[]) as $s){
        q('INSERT INTO stocks(symbol,name,exchange,sector,industry,market_cap,pe,eps,dividend_yield,beta,revenue_growth,eps_growth,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),exchange=VALUES(exchange),sector=VALUES(sector),industry=VALUES(industry),market_cap=VALUES(market_cap),pe=VALUES(pe),eps=VALUES(eps),dividend_yield=VALUES(dividend_yield),beta=VALUES(beta),revenue_growth=VALUES(revenue_growth),eps_growth=VALUES(eps_growth),updated_at=VALUES(updated_at)',[$s['symbol'],$s['name']??null,$s['exchange']??null,$s['sector']??null,$s['industry']??null,$s['market_cap']??null,$s['pe']??null,$s['eps']??null,$s['dividend_yield']??null,$s['beta']??null,$s['revenue_growth']??null,$s['eps_growth']??null,$s['updated_at']??date('Y-m-d H:i:s')]);
        $count++;
    }
    foreach(($payload['prices']??[]) as $p){
        q('INSERT IGNORE INTO prices(symbol,ts,open,high,low,close,volume) VALUES(?,?,?,?,?,?,?)',[$p['symbol'],$p['ts'],$p['open']??null,$p['high']??null,$p['low']??null,$p['close']??null,$p['volume']??null]);
    }
    return $count;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $expected=$cfg['ingest_token']??'';
    $provided=$_SERVER['HTTP_X_QUANTIX_TOKEN']??'';
    if(!$expected||!$provided||!hash_equals($expected,$provided)){http_response_code(401);exit('Unauthorized');}
    $payload=json_decode(file_get_contents('php://input'),true);
    if(!is_array($payload)){http_response_code(400);exit('Invalid JSON');}
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true,'imported'=>import_payload($payload)]);
    exit;
}

$root=realpath(__DIR__.'/../../data/ingest');
if(!$root){exit('No ingest directory.');}
$files=glob($root.'/*.json');$count=0;
foreach($files as $file){
    $payload=json_decode(file_get_contents($file),true);
    if(is_array($payload))$count+=import_payload($payload);
}
echo 'Imported '.$count.' stock records.';
