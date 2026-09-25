<?php

function quantix_series_metrics(array $prices): array {
  $s=[]; foreach($prices as $p){$v=isset($p['close'])?(float)$p['close']:null;if($v!==null&&$v>0)$s[]=$v;}
  $n=count($s); if($n<30)return [];
  $ret=[]; for($i=1;$i<$n;$i++) if($s[$i-1]>0)$ret[]=$s[$i]/$s[$i-1]-1;
  if(!$ret)return [];
  $mean=array_sum($ret)/count($ret); $vol=stats_std($ret)*sqrt(252); $ann=pow(1+$mean,252)-1;
  $down=array_values(array_filter($ret,fn($x)=>$x<0)); $downVol=count($down)>1?stats_std($down)*sqrt(252):null;
  $peak=$s[0];$mdd=0; foreach($s as $v){$peak=max($peak,$v);$mdd=min($mdd,$v/$peak-1);}
  $r20=$s[$n-21]>0?$s[$n-1]/$s[$n-21]-1:null; $r60=$n>60&&$s[$n-61]>0?$s[$n-1]/$s[$n-61]-1:null;
  $sma20=array_sum(array_slice($s,-20))/20; $sma50=$n>=50?array_sum(array_slice($s,-50))/50:null;
  $g=[];$l=[];for($i=max(1,$n-14);$i<$n;$i++){ $d=$s[$i]-$s[$i-1];$g[]=max(0,$d);$l[]=max(0,-$d); }
  $ag=array_sum($g)/max(1,count($g));$al=array_sum($l)/max(1,count($l));$rsi=$al>0?100-(100/(1+$ag/$al)):100;
  $x=[];$y=[];$start=max(0,$n-60);for($i=$start;$i<$n;$i++){ $x[]=$i-$start;$y[]=log($s[$i]); }
  $mx=array_sum($x)/count($x);$my=array_sum($y)/count($y);$num=0;$den=0;foreach($x as $i=>$xx){$num+=($xx-$mx)*($y[$i]-$my);$den+=($xx-$mx)**2;}$slope=$den? $num/$den:0;$int=$my-$slope*$mx;$res=[];foreach($x as $i=>$xx)$res[]=$y[$i]-($int+$slope*$xx);$sigma=stats_std($res);
  $path=[];for($d=1;$d<=20;$d++){ $v=exp($int+$slope*(count($x)-1+$d));$band=1.96*$sigma*sqrt(1+$d/max(1,count($x)));$path[]=['day'=>$d,'value'=>$v,'low'=>exp(log($v)-$band),'high'=>exp(log($v)+$band)]; }
  $f5=$path[4]['value'];$f20=$path[19]['value'];$trend=($s[$n-1]>$sma20&&($sma50===null||$sma20>$sma50)&&$slope>0)?'bullish':(($s[$n-1]<$sma20&&$sma50!==null&&$sma20<$sma50&&$slope<0)?'bearish':'mixed');
  return ['last'=>$s[$n-1],'return_20d'=>$r20,'return_60d'=>$r60,'annualized_return'=>$ann,'annualized_volatility'=>$vol,'max_drawdown'=>$mdd,'sharpe'=>$vol>0?$ann/$vol:null,'sortino'=>($downVol&&$downVol>0)?$ann/$downVol:null,'rsi14'=>$rsi,'sma20'=>$sma20,'sma50'=>$sma50,'trend'=>$trend,'forecast_5d'=>$f5,'forecast_5d_low'=>$path[4]['low'],'forecast_5d_high'=>$path[4]['high'],'forecast_20d'=>$f20,'forecast_20d_low'=>$path[19]['low'],'forecast_20d_high'=>$path[19]['high'],'forecast_annualized_trend'=>$slope*252,'forecast_path_20d'=>$path];
}
function stats_std(array $a): float { $n=count($a);if($n<2)return 0.0;$m=array_sum($a)/$n;$v=0;foreach($a as $x)$v+=($x-$m)**2;return sqrt($v/($n-1));}
function quantix_remote_quote(string $symbol): array {
  if(!preg_match('/^[A-Z0-9.^_-]{1,15}$/',$symbol)) return [[],[]];
  $url='https://query1.finance.yahoo.com/v8/finance/chart/'.rawurlencode($symbol).'?range=1y&interval=1d&events=div%2Csplits';
  $ctx=stream_context_create(['http'=>['timeout'=>8,'header'=>"User-Agent: Quantix/1.0\r\n"]]);
  $raw=@file_get_contents($url,false,$ctx); if(!$raw) return [[],[]];
  $j=json_decode($raw,true);$r=$j['chart']['result'][0]??null;if(!$r)return [[],[]];
  $q=$r['indicators']['quote'][0]??[];$ts=$r['timestamp']??[];$prices=[];
  foreach($ts as $i=>$t){if(!isset($q['close'][$i])||$q['close'][$i]===null)continue;$prices[]=['symbol'=>$symbol,'ts'=>gmdate('Y-m-d H:i:s',$t),'open'=>$q['open'][$i]??null,'high'=>$q['high'][$i]??null,'low'=>$q['low'][$i]??null,'close'=>$q['close'][$i],'volume'=>$q['volume'][$i]??null];}
  $m=$r['meta']??[];return [['symbol'=>$symbol,'name'=>$m['longName']??$m['shortName']??$symbol,'market_cap'=>null,'pe'=>null,'eps'=>null,'dividend_yield'=>null,'beta'=>null],$prices];
}
require __DIR__.'/partials/header.php';
$symbol=strtoupper(trim($_GET['symbol']??'AAPL'));$stock=[];$prices=[];$metrics=[];
try{$stock=q('SELECT * FROM stocks WHERE symbol=?',[$symbol])->fetch()?:[];$prices=q('SELECT ts,close,volume FROM prices WHERE symbol=? ORDER BY ts DESC LIMIT 250',[$symbol])->fetchAll();$prices=array_reverse($prices);}catch(Throwable $x){}
if(!$stock||!$prices){
  $cache=json_decode(@file_get_contents(__DIR__.'/data/market.json'),true)?:[];
  foreach(($cache['stocks']??[]) as $s) if(($s['symbol']??'')===$symbol){$stock=$s;break;}
  $prices=array_values(array_filter($cache['prices']??[],fn($p)=>($p['symbol']??'')===$symbol));
  usort($prices,fn($a,$b)=>strcmp($a['ts'],$b['ts']));
  $prices=array_slice($prices,-250);
}
$cache=json_decode(@file_get_contents(__DIR__.'/data/market.json'),true)?:[];
$metrics=$cache['analysis'][$symbol]??[];
if(!$prices){[$remoteStock,$remotePrices]=quantix_remote_quote($symbol);if($remotePrices){$prices=$remotePrices;$stock=array_merge($stock,$remoteStock);}}
if(!$metrics && $prices){$metrics=quantix_series_metrics($prices);}
$liveMode=!empty($prices);
?>
<section class="section-head"><div><span class="eyebrow">STOCK RESEARCH</span><h1><?=e($symbol)?></h1><p><?=e($stock['name']??'No fundamentals imported yet')?></p></div><a class="secondary" href="watchlist.php?add=<?=urlencode($symbol)?>">Add to watchlist</a></section>
<div class="grid four"><div class="card"><small>Last price</small><strong id="livePrice"><?=e(end($prices)['close']??'—')?></strong></div><div class="card"><small>Market cap</small><strong><?=e($stock['market_cap']??'—')?></strong></div><div class="card"><small>P/E</small><strong><?=e($stock['pe']??'—')?></strong></div><div class="card"><small>Beta</small><strong><?=e($stock['beta']??'—')?></strong></div></div>
<article class="card chart-card"><div class="card-title"><b>Price history</b><span>Cached observations</span></div><canvas id="priceChart"></canvas></article>
<div class="grid three"><article class="card"><h3>Quant metrics</h3><dl><dt>20D return</dt><dd><?=isset($metrics['return_20d'])?number_format($metrics['return_20d']*100,1).'%':'—'?></dd><dt>Annualized volatility</dt><dd><?=isset($metrics['annualized_volatility'])?number_format($metrics['annualized_volatility']*100,1).'%':'—'?></dd><dt>Max drawdown</dt><dd><?=isset($metrics['max_drawdown'])?number_format($metrics['max_drawdown']*100,1).'%':'—'?></dd><dt>RSI(14)</dt><dd><?=isset($metrics['rsi14'])?number_format($metrics['rsi14'],1):'—'?></dd><dt>Sharpe</dt><dd><?=isset($metrics['sharpe'])?number_format($metrics['sharpe'],2):'—'?></dd></dl></article><article class="card"><h3>Forecast model</h3><dl><dt>Trend</dt><dd><?=e($metrics['trend']??'—')?></dd><dt>5D estimate</dt><dd><?=isset($metrics['forecast_5d'])?number_format($metrics['forecast_5d'],2):'—'?></dd><dt>20D estimate</dt><dd><?=isset($metrics['forecast_20d'])?number_format($metrics['forecast_20d'],2):'—'?></dd><dt>20D interval</dt><dd><?=isset($metrics['forecast_20d_low'], $metrics['forecast_20d_high'])?number_format($metrics['forecast_20d_low'],2).' – '.number_format($metrics['forecast_20d_high'],2):'—'?></dd></dl><p class="muted">Statistical estimate from recent log-price trend; not a guarantee.</p></article><article class="card"><h3>Fundamentals</h3><dl><dt>Revenue growth</dt><dd><?=e($stock['revenue_growth']??'—')?></dd><dt>EPS growth</dt><dd><?=e($stock['eps_growth']??'—')?></dd><dt>EPS</dt><dd><?=e($stock['eps']??'—')?></dd><dt>Dividend yield</dt><dd><?=e($stock['dividend_yield']??'—')?></dd></dl></article><article class="card"><h3>Research notes</h3><p>Use the historical series and reported fundamentals to investigate a company. Quantix does not provide personalized financial advice.</p></article></div>
<article class="card chart-card"><div class="card-title"><b>Prediction path</b><span>20-session statistical estimate</span></div><canvas id="forecastChart"></canvas><p class="muted">The forecast and interval are model outputs from recent price behavior, not guarantees or trading instructions.</p></article>
<script>window.QUANTIX_PRICE=<?=json_encode($prices)?>;window.QUANTIX_FORECAST=<?=json_encode($metrics)?>;window.QUANTIX_SYMBOL=<?=json_encode($symbol)?>;window.QUANTIX_LIVE=<?=json_encode($liveMode)?>;</script><script src="assets/js/stock.js"></script><?php require __DIR__.'/partials/footer.php'; ?>