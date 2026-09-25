<?php
require __DIR__.'/partials/header.php';
function stats_std(array $a): float{$n=count($a);if($n<2)return 0.0;$m=array_sum($a)/$n;$v=0;foreach($a as $x)$v+=($x-$m)**2;return sqrt($v/($n-1));}
function quantix_series_metrics(array $prices):array{
 $s=[];foreach($prices as $p){$v=is_numeric($p['close']??null)?(float)$p['close']:null;if($v!==null&&$v>0)$s[]=$v;} $n=count($s);if($n<30)return [];
 $ret=[];for($i=1;$i<$n;$i++)if($s[$i-1]>0)$ret[]=$s[$i]/$s[$i-1]-1;$vol=stats_std($ret)*sqrt(252);$ann=(1+(array_sum($ret)/count($ret)))**252-1;
 $peak=$s[0];$mdd=0;foreach($s as $v){$peak=max($peak,$v);$mdd=min($mdd,$v/$peak-1);}
 $sma20=array_sum(array_slice($s,-20))/20;$sma50=$n>=50?array_sum(array_slice($s,-50))/50:null;
 $delta=[];$gain=[];$loss=[];for($i=max(1,$n-14);$i<$n;$i++){ $d=$s[$i]-$s[$i-1];$gain[]=max(0,$d);$loss[]=max(0,-$d); }$ag=array_sum($gain)/max(1,count($gain));$al=array_sum($loss)/max(1,count($loss));$rsi=$al>0?100-(100/(1+$ag/$al)):100;
 $models=[];foreach([20,60] as $window){$start=max(0,$n-$window);$x=[];$y=[];for($i=$start;$i<$n;$i++){$x[]=$i-$start;$y[]=log($s[$i]);}$mx=array_sum($x)/count($x);$my=array_sum($y)/count($y);$num=$den=0;foreach($x as $i=>$xx){$num+=($xx-$mx)*($y[$i]-$my);$den+=($xx-$mx)**2;}$slope=$den?$num/$den:0;$int=$my-$slope*$mx;$res=[];foreach($x as $i=>$xx)$res[]=$y[$i]-($int+$slope*$xx);$sigma=max(stats_std($res),1e-6);$models[]=['slope'=>$slope,'int'=>$int,'sigma'=>$sigma,'n'=>count($x)];}
 $path=[];for($d=1;$d<=20;$d++){ $vals=[];$weights=[];foreach($models as $m){$vals[]=exp($m['int']+$m['slope']*($m['n']-1+$d));$weights[]=1/($m['sigma']*$m['sigma']);}$sw=array_sum($weights);$v=0;foreach($vals as $i=>$vv)$v+=($weights[$i]/$sw)*$vv;$spread=1.96*sqrt(array_sum(array_map(fn($m,$i)=>($weights[$i]/$sw)*($m['sigma']**2+(($vals[$i]-$v)**2))/(1+$d/$m['n'],$models,array_keys($models))));$path[]=['day'=>$d,'value'=>$v,'low'=>exp(log($v)-$spread),'high'=>exp(log($v)+$spread)];}
 $f20=$path[19];$f5=$path[4];$trend=($s[$n-1]>$sma20&&($sma50===null||$sma20>$sma50))?'bullish':(($s[$n-1]<$sma20&&$sma50!==null&&$sma20<$sma50)?'bearish':'mixed');
 $confidence=max(0,min(1,1-(($f20['high']-$f20['low'])/(2*max(abs($f20['value']),1e-9)))));
 return ['last'=>$s[$n-1],'return_20d'=>$n>20&&$s[$n-21]>0?$s[$n-1]/$s[$n-21]-1:null,'return_60d'=>$n>60&&$s[$n-61]>0?$s[$n-1]/$s[$n-61]-1:null,'annualized_return'=>$ann,'annualized_volatility'=>$vol,'max_drawdown'=>$mdd,'sharpe'=>$vol>0?$ann/$vol:null,'sortino'=>null,'rsi14'=>$rsi,'sma20'=>$sma20,'sma50'=>$sma50,'trend'=>$trend,'forecast_5d'=>$f5['value'],'forecast_5d_low'=>$f5['low'],'forecast_5d_high'=>$f5['high'],'forecast_20d'=>$f20['value'],'forecast_20d_low'=>$f20['low'],'forecast_20d_high'=>$f20['high'],'forecast_annualized_trend'=>$models[1]['slope']*252,'forecast_path_20d'=>$path,'model_confidence'=>$confidence,'model_version'=>'ensemble-logtrend-v2-live'];
}
function quantix_remote_quote(string $symbol):array{
 if(!preg_match('/^[A-Z0-9.^_-]{1,15}$/',$symbol))return [[],[]];
 $url='https://query1.finance.yahoo.com/v8/finance/chart/'.rawurlencode($symbol).'?range=1y&interval=1d&events=div%2Csplits';$ctx=stream_context_create(['http'=>['timeout'=>8,'ignore_errors'=>true,'header'=>"User-Agent: Quantix/1.0
Accept: application/json
"]]);$raw=@file_get_contents($url,false,$ctx);$j=$raw?json_decode($raw,true):null;$r=$j['chart']['result'][0]??null;if(!$r)return [[],[]];
 $q=$r['indicators']['quote'][0]??[];$ts=$r['timestamp']??[];$prices=[];foreach($ts as $i=>$t){if(($q['close'][$i]??null)===null)continue;$prices[]=['symbol'=>$symbol,'ts'=>gmdate('Y-m-d H:i:s',$t),'open'=>$q['open'][$i]??null,'high'=>$q['high'][$i]??null,'low'=>$q['low'][$i]??null,'close'=>$q['close'][$i],'volume'=>$q['volume'][$i]??null];}
 $meta=$r['meta']??[];return [['symbol'=>$symbol,'name'=>$meta['longName']??$meta['shortName']??$symbol],$prices];
}
$symbol=strtoupper(trim($_GET['symbol']??'AAPL'));$cache=quantix_cache();$stock=quantix_stock_map()[$symbol]??[];$prices=array_values(array_filter($cache['prices'],fn($p)=>($p['symbol']??'')===$symbol));usort($prices,fn($a,$b)=>strcmp($a['ts'],$b['ts']));$prices=array_slice($prices,-250);$metrics=$cache['analysis'][$symbol]??[];
if(!$prices){try{$prices=q('SELECT ts,open,high,low,close,volume FROM prices WHERE symbol=? ORDER BY ts DESC LIMIT 250',[$symbol])->fetchAll();$prices=array_reverse($prices);}catch(Throwable $x){}}
if(!$prices){[$remoteStock,$remotePrices]=quantix_remote_quote($symbol);if($remotePrices){$stock=array_merge($stock,$remoteStock);$prices=$remotePrices;}}
if(!$metrics&&$prices)$metrics=quantix_series_metrics($prices);
$last=end($prices);$hasData=!empty($prices);
?>
<section class="section-head"><div><span class="eyebrow">SYMBOL RESEARCH</span><h1><?=e($symbol)?></h1><p><?=e($stock['name']??'Live or uncached symbol research')?></p></div><div class="hero-actions"><a class="secondary" href="compare.php?symbols=<?=urlencode($symbol)?>">Compare</a><?php if(user()):?><a class="primary" href="watchlist.php?add=<?=urlencode($symbol)?>">Save symbol</a><?php endif;?></div></section>
<div class="grid four">
<article class="card stat-card"><span>Last price</span><strong id="livePrice"><?=quantix_num($last['close']??null)?></strong><small id="liveMeta">Quote polling enabled</small></article>
<article class="card stat-card"><span>20D return</span><strong class="<?=quantix_change_class($metrics['return_20d']??null)?>"><?=quantix_pct($metrics['return_20d']??null)?></strong><small>Historical model input</small></article>
<article class="card stat-card"><span>Volatility</span><strong><?=quantix_pct($metrics['annualized_volatility']??null)?></strong><small>Annualized estimate</small></article>
<article class="card stat-card"><span>Model confidence</span><strong><?=quantix_pct($metrics['model_confidence']??null)?></strong><small>Heuristic uncertainty score</small></article>
</div>
<article class="card chart-card"><div class="card-title"><b>Price history</b><span id="quoteStatus"><?=e($hasData?'Cached + live quote':'No data')?></span></div><canvas id="priceChart"></canvas></article>
<div class="grid three">
<article class="card"><div class="card-title"><b>Risk & momentum</b><span>Historical</span></div><dl><dt>RSI(14)</dt><dd><?=quantix_num($metrics['rsi14']??null,1)?></dd><dt>Sharpe</dt><dd><?=quantix_num($metrics['sharpe']??null)?></dd><dt>Sortino</dt><dd><?=quantix_num($metrics['sortino']??null)?></dd><dt>Max drawdown</dt><dd><?=quantix_pct($metrics['max_drawdown']??null)?></dd><dt>Volatility regime</dt><dd><?=e($metrics['volatility_regime']??'—')?></dd></dl></article>
<article class="card"><div class="card-title"><b>Forecast model</b><span><?=e($metrics['model_version']??'—')?></span></div><dl><dt>Trend state</dt><dd><?=e($metrics['trend']??'—')?></dd><dt>5D estimate</dt><dd><?=quantix_num($metrics['forecast_5d']??null)?></dd><dt>20D estimate</dt><dd><?=quantix_num($metrics['forecast_20d']??null)?></dd><dt>20D interval</dt><dd><?=is_numeric($metrics['forecast_20d_low']??null)&&is_numeric($metrics['forecast_20d_high']??null)?quantix_num($metrics['forecast_20d_low']).' – '.quantix_num($metrics['forecast_20d_high']):'—'?></dd></dl></article>
<article class="card"><div class="card-title"><b>Fundamentals</b><span>Imported metadata</span></div><dl><dt>Market cap</dt><dd><?=quantix_money($stock['market_cap']??null)?></dd><dt>P/E</dt><dd><?=quantix_num($stock['pe']??null)?></dd><dt>EPS</dt><dd><?=quantix_num($stock['eps']??null)?></dd><dt>Dividend yield</dt><dd><?=quantix_pct($stock['dividend_yield']??null)?></dd></dl></article>
</div>
<article class="card chart-card"><div class="card-title"><b>Forecast path</b><span>20 sessions</span></div><canvas id="forecastChart"></canvas><p class="muted">Forecasts are statistical estimates based on recent price behavior. They are not guarantees or instructions.</p></article>
<?php if(!$hasData):?><div class="notice">No cached series was found for <?=e($symbol)?>. The live search request was also unavailable. Try again later.</div><?php endif;?>
<script>window.QUANTIX_PRICE=<?=json_encode($prices)?>;window.QUANTIX_FORECAST=<?=json_encode($metrics)?>;window.QUANTIX_SYMBOL=<?=json_encode($symbol)?>;window.QUANTIX_LIVE=<?=json_encode($hasData)?>;</script><script src="assets/js/stock.js"></script>
<?php require __DIR__.'/partials/footer.php'; ?>