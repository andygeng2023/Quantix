import os,json,datetime as dt
from pathlib import Path
import numpy as np
import pandas as pd
import yfinance as yf

raw_symbols=os.getenv('QUANTIX_SYMBOLS','').strip()
DEFAULT_UNIVERSE='AAPL,MSFT,NVDA,AMZN,GOOGL,META,TSLA,AVGO,ORCL,CRM,ADBE,AMD,INTC,QCOM,TXN,MU,AMAT,ASML,CSCO,IBM,NOW,PANW,PLTR,SNOW,NET,CRWD,UBER,ABNB,SHOP,SPOT,NFLX,DIS,CMCSA,TMUS,VZ,T,KO,PEP,COST,WMT,TGT,HD,LOW,MCD,SBUX,NKE,EL,PG,JNJ,MRK,PFE,ABBV,LLY,UNH,CVS,TMO,DHR,ISRG,BA,CAT,DE,GE,RTX,LMT,GD,FORD,GM,RIVN,SPY,QQQ,IWM,DIA,XLF,XLK,XLE,XLV,SMH,ARKK,JPM,BAC,WFC,GS,MS,C,BLK,AXP,MA,V,COF,ADP,PYPL,INTU,AMGN,GILD,REGN,VRTX,BMY,CVX,XOM,COP,SLB,NEE,DUK,SO,PLD,AMT,EQIX'
symbols=[x.strip().upper() for x in (raw_symbols or DEFAULT_UNIVERSE).split(',') if x.strip()]
out=Path(os.getenv('QUANTIX_INGEST_DIR','../data/ingest'));out.mkdir(parents=True,exist_ok=True)

def safe_float(v):
    try:
        return None if pd.isna(v) else float(v)
    except Exception:
        return None

def metrics(prices, benchmark):
    if len(prices)<30:
        return {}
    s=pd.Series(prices['close'].astype(float).values)
    ret=s.pct_change().dropna()
    if ret.empty: return {}
    ann=(1+ret.mean())**252-1
    vol=ret.std(ddof=1)*np.sqrt(252)
    downside=ret[ret<0].std(ddof=1)*np.sqrt(252) if len(ret[ret<0])>1 else np.nan
    sharpe=ann/vol if vol and np.isfinite(vol) and vol>0 else np.nan
    sortino=ann/downside if np.isfinite(downside) and downside>0 else np.nan
    peak=s.cummax()
    dd=s/peak-1
    r20=s.pct_change(20).iloc[-1] if len(s)>20 else np.nan
    r60=s.pct_change(60).iloc[-1] if len(s)>60 else np.nan
    sma20=s.rolling(20).mean().iloc[-1]
    sma50=s.rolling(50).mean().iloc[-1] if len(s)>=50 else np.nan
    ema12=s.ewm(span=12,adjust=False).mean()
    ema26=s.ewm(span=26,adjust=False).mean()
    macd=ema12-ema26
    signal=macd.ewm(span=9,adjust=False).mean()
    delta=s.diff()
    gain=delta.clip(lower=0).rolling(14).mean()
    loss=(-delta.clip(upper=0)).rolling(14).mean()
    rs=gain/loss.replace(0,np.nan)
    rsi=100-(100/(1+rs))
    beta=np.nan
    if benchmark is not None and len(benchmark)==len(ret):
        cov=np.cov(ret.values,benchmark.values,ddof=1)[0,1]
        bv=np.var(benchmark.values,ddof=1)
        beta=cov/bv if bv>0 else np.nan
    def forecast(days):
        n=min(60,len(s))
        y=np.log(s.tail(n).values)
        x=np.arange(n,dtype=float)
        slope,intercept=np.polyfit(x,y,1)
        fitted=intercept+slope*x
        resid=y-fitted
        sigma=float(np.std(resid,ddof=1)) if n>2 else 0.0
        future=float(np.exp(intercept+slope*(n-1+days)))
        lo=float(np.exp(intercept+slope*(n-1+days)-1.96*sigma*np.sqrt(1+days/n)))
        hi=float(np.exp(intercept+slope*(n-1+days)+1.96*sigma*np.sqrt(1+days/n)))
        return future,lo,hi,slope*252
    f5,l5,h5,t5=forecast(5); f20,l20,h20,t20=forecast(20)
    last=float(s.iloc[-1])
    trend='bullish' if last>sma20 and sma20>(sma50 if np.isfinite(sma50) else sma20) and t20>0 else ('bearish' if last<sma20 and np.isfinite(sma50) and sma20<sma50 and t20<0 else 'mixed')
    return {
        'last':last,'return_20d':safe_float(r20),'return_60d':safe_float(r60),
        'annualized_return':safe_float(ann),'annualized_volatility':safe_float(vol),
        'max_drawdown':safe_float(dd.min()),'sharpe':safe_float(sharpe),'sortino':safe_float(sortino),
        'beta':safe_float(beta),'sma20':safe_float(sma20),'sma50':safe_float(sma50),
        'rsi14':safe_float(rsi.iloc[-1]),'macd':safe_float(macd.iloc[-1]),
        'macd_signal':safe_float(signal.iloc[-1]),'trend':trend,
        'forecast_5d':safe_float(f5),'forecast_5d_low':safe_float(l5),'forecast_5d_high':safe_float(h5),
        'forecast_20d':safe_float(f20),'forecast_20d_low':safe_float(l20),'forecast_20d_high':safe_float(h20),
        'forecast_annualized_trend':safe_float(t20)
    }

all_stocks=[]; all_prices=[]; series={}
for sym in symbols:
    t=yf.Ticker(sym)
    try: info=t.info or {}
    except Exception: info={}
    stocks=[{'symbol':sym,'name':info.get('longName') or info.get('shortName'),'exchange':info.get('exchange'),'sector':info.get('sector'),'industry':info.get('industry'),'market_cap':info.get('marketCap'),'pe':info.get('trailingPE'),'eps':info.get('trailingEps'),'dividend_yield':info.get('dividendYield'),'beta':info.get('beta'),'revenue_growth':info.get('revenueGrowth'),'eps_growth':info.get('earningsGrowth'),'updated_at':dt.datetime.now(dt.timezone.utc).strftime('%Y-%m-%d %H:%M:%S')}]
    h=t.history(period='1y',auto_adjust=False)
    prices=[]
    for idx,row in h.iterrows():
        prices.append({'symbol':sym,'ts':idx.to_pydatetime().strftime('%Y-%m-%d %H:%M:%S'),'open':safe_float(row.Open),'high':safe_float(row.High),'low':safe_float(row.Low),'close':safe_float(row.Close),'volume':None if pd.isna(row.Volume) else int(row.Volume)})
    all_stocks.extend(stocks); all_prices.extend(prices)
    series[sym]=pd.Series([p['close'] for p in prices],dtype=float)
    payload={'generated_at':dt.datetime.now(dt.timezone.utc).isoformat().replace('+00:00','Z'),'stocks':stocks,'prices':prices}
    (out/f'quantix-{sym}-{dt.datetime.now(dt.timezone.utc).strftime("%Y%m%d%H%M%S")}.json').write_text(json.dumps(payload,separators=(',',':')))
    print(sym, len(prices), 'observations')

aligned=pd.DataFrame(series).dropna()
benchmark=aligned.pct_change().dropna().mean(axis=1) if not aligned.empty else None
analyses={}
for sym in symbols:
    df=pd.DataFrame({'close':series[sym]}).dropna()
    r=df['close'].pct_change().dropna()
    bm=None
    if benchmark is not None:
        bm=pd.concat([r,benchmark],axis=1).dropna()
        r=bm.iloc[:,0]; bm=bm.iloc[:,1]
    analyses[sym]=metrics(df,bm)
    print('analysis',sym,analyses[sym].get('trend'),analyses[sym].get('forecast_20d'))

cache=Path('web/data/market.json');cache.parent.mkdir(parents=True,exist_ok=True)
cache.write_text(json.dumps({'generated_at':dt.datetime.now(dt.timezone.utc).isoformat().replace('+00:00','Z'),'stocks':all_stocks,'prices':all_prices,'analysis':analyses},separators=(',',':')))
print(f'Wrote static market cache: {cache} ({len(all_stocks)} stocks, {len(all_prices)} prices, {len(analyses)} analyses)')
