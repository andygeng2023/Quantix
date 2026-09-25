<?php
function quantix_cache(): array {
    static $cache=null;
    if($cache!==null)return $cache;
    $path=__DIR__.'/../data/market.json';
    $raw=@file_get_contents($path);
    $cache=is_string($raw)?(json_decode($raw,true)?:[]):[];
    foreach(['stocks','prices','analysis'] as $k)$cache[$k]=is_array($cache[$k]??null)?$cache[$k]:[];
    return $cache;
}
function quantix_stocks():array {
    $stocks=quantix_cache()['stocks'];
    if($stocks)return $stocks;
    try{return q('SELECT * FROM stocks ORDER BY market_cap DESC LIMIT 1000')->fetchAll();}catch(Throwable $e){return [];}
}
function quantix_analysis():array {
    $a=quantix_cache()['analysis'];
    if($a)return $a;
    try{$rows=q('SELECT * FROM stock_analysis')->fetchAll();foreach($rows as $r){$m=json_decode($r['metrics']??'',true);$a[$r['symbol']]=$m?:$r;}}catch(Throwable $e){}
    return $a;
}
function quantix_prices():array {
    $p=quantix_cache()['prices'];
    if($p)return $p;
    return [];
}
function quantix_generated_at():?string {
    $v=quantix_cache()['generated_at']??null;
    return is_string($v)&&$v!==''?$v:null;
}
function quantix_age_minutes():?float {
    $g=quantix_generated_at();if(!$g)return null;$t=strtotime($g);return $t?max(0,(time()-$t)/60):null;
}
function quantix_status():array {
    $age=quantix_age_minutes();$count=count(quantix_stocks());
    if(!$count)return ['label'=>'Data unavailable','tone'=>'bad','age'=>$age,'count'=>0];
    if($age===null)return ['label'=>'Loaded · timestamp unknown','tone'=>'warn','age'=>null,'count'=>$count];
    if($age<=20)return ['label'=>'Live research cache','tone'=>'good','age'=>$age,'count'=>$count];
    if($age<=90)return ['label'=>'Cache aging','tone'=>'warn','age'=>$age,'count'=>$count];
    return ['label'=>'Cache stale','tone'=>'bad','age'=>$age,'count'=>$count];
}
function quantix_stock_map():array {
    static $map=null;if($map!==null)return $map;$map=[];
    foreach(quantix_stocks() as $r)if(!empty($r['symbol']))$map[$r['symbol']]=$r;return $map;
}
function quantix_price_map():array {
    static $map=null;if($map!==null)return $map;$map=[];
    foreach(quantix_prices() as $r){$s=$r['symbol']??'';if($s!==''&&(!isset($map[$s])||($r['ts']??'')>$map[$s]['ts']))$map[$s]=$r;}return $map;
}
function quantix_money($v):string{if(!is_numeric($v))return '—';$n=(float)$v;if(abs($n)>=1e12)return '$'.number_format($n/1e12,1).'T';if(abs($n)>=1e9)return '$'.number_format($n/1e9,1).'B';if(abs($n)>=1e6)return '$'.number_format($n/1e6,1).'M';return '$'.number_format($n,2);}
function quantix_pct($v,int $digits=1):string{return is_numeric($v)?number_format((float)$v*100,$digits).'%':'—';}
function quantix_num($v,int $digits=2):string{return is_numeric($v)?number_format((float)$v,$digits):'—';}
function quantix_change_class($v):string{return is_numeric($v)?((float)$v>0?'positive':((float)$v<0?'negative':'neutral')):'neutral';}
function quantix_model_state($m):string{return ($m['trend']??'mixed').' · '.($m['volatility_regime']??'normal');}
