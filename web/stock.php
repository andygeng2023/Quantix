<?php
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
<div class="grid four"><div class="card"><small>Last price</small><strong><?=e(end($prices)['close']??'—')?></strong></div><div class="card"><small>Market cap</small><strong><?=e($stock['market_cap']??'—')?></strong></div><div class="card"><small>P/E</small><strong><?=e($stock['pe']??'—')?></strong></div><div class="card"><small>Beta</small><strong><?=e($stock['beta']??'—')?></strong></div></div>
<article class="card chart-card"><div class="card-title"><b>Price history</b><span>Cached observations</span></div><canvas id="priceChart"></canvas></article>
<div class="grid three"><article class="card"><h3>Quant metrics</h3><dl><dt>20D return</dt><dd><?=isset($metrics['return_20d'])?number_format($metrics['return_20d']*100,1).'%':'—'?></dd><dt>Annualized volatility</dt><dd><?=isset($metrics['annualized_volatility'])?number_format($metrics['annualized_volatility']*100,1).'%':'—'?></dd><dt>Max drawdown</dt><dd><?=isset($metrics['max_drawdown'])?number_format($metrics['max_drawdown']*100,1).'%':'—'?></dd><dt>RSI(14)</dt><dd><?=isset($metrics['rsi14'])?number_format($metrics['rsi14'],1):'—'?></dd><dt>Sharpe</dt><dd><?=isset($metrics['sharpe'])?number_format($metrics['sharpe'],2):'—'?></dd></dl></article><article class="card"><h3>Forecast model</h3><dl><dt>Trend</dt><dd><?=e($metrics['trend']??'—')?></dd><dt>5D estimate</dt><dd><?=isset($metrics['forecast_5d'])?number_format($metrics['forecast_5d'],2):'—'?></dd><dt>20D estimate</dt><dd><?=isset($metrics['forecast_20d'])?number_format($metrics['forecast_20d'],2):'—'?></dd><dt>20D interval</dt><dd><?=isset($metrics['forecast_20d_low'], $metrics['forecast_20d_high'])?number_format($metrics['forecast_20d_low'],2).' – '.number_format($metrics['forecast_20d_high'],2):'—'?></dd></dl><p class="muted">Statistical estimate from recent log-price trend; not a guarantee.</p></article><article class="card"><h3>Fundamentals</h3><dl><dt>Revenue growth</dt><dd><?=e($stock['revenue_growth']??'—')?></dd><dt>EPS growth</dt><dd><?=e($stock['eps_growth']??'—')?></dd><dt>EPS</dt><dd><?=e($stock['eps']??'—')?></dd><dt>Dividend yield</dt><dd><?=e($stock['dividend_yield']??'—')?></dd></dl></article><article class="card"><h3>Research notes</h3><p>Use the historical series and reported fundamentals to investigate a company. Quantix does not provide personalized financial advice.</p></article></div>
<article class="card chart-card"><div class="card-title"><b>Prediction path</b><span>20-session statistical estimate</span></div><canvas id="forecastChart"></canvas><p class="muted">The forecast and interval are model outputs from recent price behavior, not guarantees or trading instructions.</p></article>
<script>window.QUANTIX_PRICE=<?=json_encode($prices)?>;window.QUANTIX_FORECAST=<?=json_encode($metrics)?>;window.QUANTIX_SYMBOL=<?=json_encode($symbol)?>;window.QUANTIX_LIVE=<?=json_encode($liveMode)?>;</script><script src="assets/js/stock.js"></script><?php require __DIR__.'/partials/footer.php'; ?>