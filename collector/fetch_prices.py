import os,json,datetime as dt
from pathlib import Path
import numpy as np
import pandas as pd
import yfinance as yf

raw_symbols=os.getenv('QUANTIX_SYMBOLS','').strip()
UNIVERSE_URL='https://raw.githubusercontent.com/datasets/s-and-p-500-companies/main/data/constituents.csv'
FINANCIALS_URL='https://raw.githubusercontent.com/datasets/s-and-p-500-companies-financials/main/data/constituents-financials.csv'
ETF_EXTRAS=['SPY','QQQ','IWM','DIA','XLF','XLK','XLE','XLV','SMH','ARKK']
if raw_symbols:
    symbols=[x.strip().upper() for x in raw_symbols.split(',') if x.strip()]
else:
    try:
        universe_df=pd.read_csv(UNIVERSE_URL)
        symbols=[str(x).strip().upper().replace('.','-') for x in universe_df['Symbol'].dropna().tolist()]
    except Exception as exc:
        print('Universe download failed:',exc)
        symbols=['AAPL','MSFT','NVDA','AMZN','GOOGL','META','TSLA']
    symbols=list(dict.fromkeys(symbols+ETF_EXTRAS))
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
        path=[]
        for d in range(1,days+1):
            val=float(np.exp(intercept+slope*(n-1+d)))
            band=float(1.96*sigma*np.sqrt(1+d/n))
            path.append({'day':d,'value':val,'low':float(np.exp(intercept+slope*(n-1+d)-band)),'high':float(np.exp(intercept+slope*(n-1+d)+band))})
        return future,lo,hi,slope*252,path
    f5,l5,h5,t5,p5=forecast(5); f20,l20,h20,t20,p20=forecast(20)
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
        'forecast_annualized_trend':safe_float(t20),'forecast_path_20d':p20
    }

def backtest_forecast(s, horizon, lookback=60, step=5, max_origins=20):
    s=pd.Series(s,dtype=float).dropna().reset_index(drop=True)
    if len(s)<lookback+horizon+5: return {}
    origins=list(range(lookback, len(s)-horizon+1, step))[-max_origins:]
    errors=[]; sq_errors=[]; signed=[]; direction=[]; covered=[]
    for origin in origins:
        train=s.iloc[:origin]; n=min(lookback,len(train))
        y=np.log(train.tail(n).values); x=np.arange(n,dtype=float)
        slope,intercept=np.polyfit(x,y,1)
        resid=y-(intercept+slope*x); sigma=float(np.std(resid,ddof=1)) if n>2 else 0.0
        pred=float(np.exp(intercept+slope*(n-1+horizon)))
        band=float(1.96*sigma*np.sqrt(1+horizon/n))
        lo=float(np.exp(intercept+slope*(n-1+horizon)-band)); hi=float(np.exp(intercept+slope*(n-1+horizon)+band))
        actual=float(s.iloc[origin+horizon-1]); base=float(s.iloc[origin-1])
        errors.append(abs(pred-actual)); sq_errors.append((pred-actual)**2); signed.append(pred-actual)
        direction.append(int((pred-base)*(actual-base)>=0)); covered.append(int(lo<=actual<=hi))
    if not errors: return {}
    return {'horizon':horizon,'tests':len(errors),'mae':safe_float(np.mean(errors)),
            'rmse':safe_float(np.sqrt(np.mean(sq_errors))),'mean_error':safe_float(np.mean(signed)),
            'directional_accuracy':safe_float(np.mean(direction)),'interval_coverage':safe_float(np.mean(covered))}

def backtest_summary(s):
    return {f'{h}d':backtest_forecast(s,h) for h in (5,20)}

all_stocks=[]; all_prices=[]; series={}
try:
    fin=pd.read_csv(FINANCIALS_URL)
    fin['Symbol']=fin['Symbol'].astype(str).str.strip().str.upper().str.replace('.','-',regex=False)
    fin_by_symbol={str(r['Symbol']):r for _,r in fin.iterrows()}
except Exception as exc:
    print('Financial dataset download failed:',exc)
    fin_by_symbol={}

print(f'Fetching {len(symbols)} symbols in efficient batches')
price_frames={}
BATCH_SIZE=50
for start in range(0,len(symbols),BATCH_SIZE):
    batch=symbols[start:start+BATCH_SIZE]
    print(f'Batch {start+1}-{start+len(batch)} / {len(symbols)}')
    try:
        frame=yf.download(' '.join(batch),period='1y',interval='1d',auto_adjust=False,group_by='ticker',threads=True,progress=False,timeout=30)
        for sym in batch:
            try:
                price_frames[sym]=frame[sym].dropna(how='all').reset_index()
            except Exception:
                price_frames[sym]=pd.DataFrame()
    except Exception as exc:
        print('Batch failed:',exc)
        # Retry the failed batch once with a smaller request to avoid a total-cache failure.
        if len(batch)>20:
            try:
                retry=yf.download(' '.join(batch),period='1y',interval='1d',auto_adjust=False,group_by='ticker',threads=False,progress=False,timeout=45)
                for sym in batch:
                    try: price_frames[sym]=retry[sym].dropna(how='all').reset_index()
                    except Exception: price_frames[sym]=pd.DataFrame()
            except Exception as retry_exc:
                print('Retry failed:',retry_exc)
        for sym in batch:
            price_frames.setdefault(sym,pd.DataFrame())
for sym in symbols:
    info=fin_by_symbol.get(sym,{})
    stocks=[{'symbol':sym,'name':None if pd.isna(info.get('Name')) else info.get('Name'),
             'exchange':None,'sector':None if pd.isna(info.get('Sector')) else info.get('Sector'),'industry':None,
             'market_cap':safe_float(info.get('Market Cap')),'pe':safe_float(info.get('Price/Earnings')),
             'eps':safe_float(info.get('Earnings/Share')),'dividend_yield':safe_float(info.get('Dividend Yield')),
             'beta':None,'revenue_growth':None,'eps_growth':None,
             'price_to_sales':safe_float(info.get('Price/Sales')),'price_to_book':safe_float(info.get('Price/Book')),
             '52_week_low':safe_float(info.get('52 Week Low')),'52_week_high':safe_float(info.get('52 Week High')),
             'updated_at':dt.datetime.now(dt.timezone.utc).strftime('%Y-%m-%d %H:%M:%S')}]
    h=price_frames.get(sym,pd.DataFrame())
    prices=[]
    if not h.empty:
        date_col=h.columns[0]
        for _,row in h.iterrows():
            close=row.get('Close')
            if pd.isna(close): continue
            prices.append({'symbol':sym,'ts':pd.Timestamp(row[date_col]).to_pydatetime().strftime('%Y-%m-%d %H:%M:%S'),
                           'open':safe_float(row.get('Open')),'high':safe_float(row.get('High')),
                           'low':safe_float(row.get('Low')),'close':safe_float(close),
                           'volume':None if pd.isna(row.get('Volume')) else int(row.get('Volume'))})
    if prices:
        all_stocks.extend(stocks); all_prices.extend(prices)
        series[sym]=pd.Series([p['close'] for p in prices],dtype=float)
        print(sym,len(prices),'observations')
aligned=pd.DataFrame(series).dropna()
benchmark=aligned.pct_change().dropna().mean(axis=1) if not aligned.empty else None
analyses={}
for sym, ser in series.items():
    df=pd.DataFrame({'close':ser}).dropna()
    r=df['close'].pct_change().dropna()
    bm=None
    if benchmark is not None:
        bm=pd.concat([r,benchmark],axis=1).dropna()
        r=bm.iloc[:,0]; bm=bm.iloc[:,1]
    analyses[sym]=metrics(df,bm)
    analyses[sym]['backtest']=backtest_summary(series[sym])
    analyses[sym]['data_quality']={'observations':int(len(series[sym])),'freshness':str(all_prices[-1]['ts']) if all_prices and all_prices[-1]['symbol']==sym else None}
    print('analysis',sym,analyses[sym].get('trend'),analyses[sym].get('forecast_20d'),analyses[sym].get('backtest',{}))

cache=Path('web/data/market.json');cache.parent.mkdir(parents=True,exist_ok=True)
cache.write_text(json.dumps({'generated_at':dt.datetime.now(dt.timezone.utc).isoformat().replace('+00:00','Z'),'stocks':all_stocks,'prices':all_prices,'analysis':analyses},separators=(',',':')))
print(f'Wrote static market cache: {cache} ({len(all_stocks)} stocks, {len(all_prices)} prices, {len(analyses)} analyses)')
