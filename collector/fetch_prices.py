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
ROOT=Path(__file__).resolve().parents[1]
out=Path(os.getenv('QUANTIX_INGEST_DIR',str(ROOT/'data'/'ingest')));out.mkdir(parents=True,exist_ok=True)
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
    mid20=s.rolling(20).mean(); std20=s.rolling(20).std(ddof=1); upper20=mid20+2*std20; lower20=mid20-2*std20
    if 'high' in prices.columns and 'low' in prices.columns:
        high=prices['high'].astype(float); low=prices['low'].astype(float); prev_close=prices['close'].astype(float).shift()
        tr=pd.concat([high-low,(high-prev_close).abs(),(low-prev_close).abs()],axis=1).max(axis=1)
    else:
        # Close-only fallback: approximate true range with absolute close changes.
        tr=s.diff().abs()
    atr14=tr.rolling(14).mean().iloc[-1] if len(tr)>=14 else np.nan
    k14=100*(s-s.rolling(14).min())/(s.rolling(14).max()-s.rolling(14).min()).replace(0,np.nan)
    vol20=ret.tail(20).std(ddof=1)*np.sqrt(252) if len(ret)>=20 else np.nan
    vol60=ret.tail(60).std(ddof=1)*np.sqrt(252) if len(ret)>=60 else np.nan
    vol_regime='high' if np.isfinite(vol20) and np.isfinite(vol60) and vol20>vol60*1.25 else ('low' if np.isfinite(vol20) and np.isfinite(vol60) and vol20<vol60*0.8 else 'normal')
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
    # Ensemble the short and medium log-price trends. The lower-weight model is
    # selected by inverse residual variance, which reduces sensitivity to one window.
    def ensemble(days):
        candidates=[]
        for window in (20,60):
            n=min(window,len(s)); y=np.log(s.tail(n).values); x=np.arange(n,dtype=float)
            slope,intercept=np.polyfit(x,y,1); resid=y-(intercept+slope*x)
            sigma=max(float(np.std(resid,ddof=1)) if n>2 else 0.0,1e-6)
            pred=float(np.exp(intercept+slope*(n-1+days))); candidates.append((pred,slope,sigma,n))
        weights=np.array([1/(z[2]**2) for z in candidates]); weights=weights/weights.sum()
        pred=float(sum(w*z[0] for w,z in zip(weights,candidates))); slope=float(sum(w*z[1] for w,z in zip(weights,candidates)))
        sigma=float(np.sqrt(sum(w*(z[2]**2+(z[0]-pred)**2)/(1+days/z[3]) for w,z in zip(weights,candidates))))
        band=1.96*sigma*np.sqrt(1+days/60); return pred,float(np.exp(np.log(pred)-band)),float(np.exp(np.log(pred)+band)),slope*252
    f5,l5,h5,t5=ensemble(5); f20,l20,h20,t20=ensemble(20)
    path=[]
    for d in range(1,21):
        v,lo,hi,_=ensemble(d); path.append({'day':d,'value':v,'low':lo,'high':hi})
    last=float(s.iloc[-1])
    trend='bullish' if last>sma20 and sma20>(sma50 if np.isfinite(sma50) else sma20) and t20>0 else ('bearish' if last<sma20 and np.isfinite(sma50) and sma20<sma50 and t20<0 else 'mixed')
    return {
        'last':last,'return_20d':safe_float(r20),'return_60d':safe_float(r60),
        'annualized_return':safe_float(ann),'annualized_volatility':safe_float(vol),
        'max_drawdown':safe_float(dd.min()),'sharpe':safe_float(sharpe),'sortino':safe_float(sortino),
        'beta':safe_float(beta),'sma20':safe_float(sma20),'sma50':safe_float(sma50),
        'rsi14':safe_float(rsi.iloc[-1]),'macd':safe_float(macd.iloc[-1]),
        'macd_signal':safe_float(signal.iloc[-1]),'trend':trend,'bollinger_upper':safe_float(upper20.iloc[-1]),'bollinger_lower':safe_float(lower20.iloc[-1]),'atr14':safe_float(atr14),'stochastic14':safe_float(k14.iloc[-1]),'volatility_regime':vol_regime,
        'forecast_5d':safe_float(f5),'forecast_5d_low':safe_float(l5),'forecast_5d_high':safe_float(h5),
        'forecast_20d':safe_float(f20),'forecast_20d_low':safe_float(l20),'forecast_20d_high':safe_float(h20),
        'forecast_annualized_trend':safe_float(t20),'forecast_path_20d':p20,'model_confidence':safe_float(max(0,min(1,1-((h20-l20)/max(abs(f20),1e-9))))),'model_version':'ensemble-logtrend-v2'
    }

def backtest_forecast(s, horizon, lookback=60, step=5, max_origins=20):
    s=pd.Series(s,dtype=float).dropna().reset_index(drop=True)
    if len(s)<lookback+horizon+5:return {}
    origins=list(range(lookback,len(s)-horizon+1,step))[-max_origins:]
    errors=[];sq_errors=[];signed=[];direction=[];covered=[]
    for origin in origins:
        train=s.iloc[:origin];candidates=[]
        for window in (20,60):
            n=min(window,len(train));y=np.log(train.tail(n).values);x=np.arange(n,dtype=float)
            slope,intercept=np.polyfit(x,y,1);resid=y-(intercept+slope*x);sigma=max(float(np.std(resid,ddof=1)) if n>2 else 0.0,1e-6)
            pred=float(np.exp(intercept+slope*(n-1+horizon)));candidates.append((pred,slope,sigma,n))
        weights=np.array([1/(z[2]**2) for z in candidates]);weights/=weights.sum()
        pred=float(sum(w*z[0] for w,z in zip(weights,candidates)))
        sigma=float(np.sqrt(sum(w*(z[2]**2+(z[0]-pred)**2)/(1+horizon/z[3]) for w,z in zip(weights,candidates))))
        band=1.96*sigma*np.sqrt(1+horizon/60);lo=float(np.exp(np.log(pred)-band));hi=float(np.exp(np.log(pred)+band))
        actual=float(s.iloc[origin+horizon-1]);base=float(s.iloc[origin-1])
        errors.append(abs(pred-actual));sq_errors.append((pred-actual)**2);signed.append(pred-actual);direction.append(int((pred-base)*(actual-base)>=0));covered.append(int(lo<=actual<=hi))
    if not errors:return {}
    return {'horizon':horizon,'tests':len(errors),'mae':safe_float(np.mean(errors)),'rmse':safe_float(np.sqrt(np.mean(sq_errors))),'mean_error':safe_float(np.mean(signed)),'directional_accuracy':safe_float(np.mean(direction)),'interval_coverage':safe_float(np.mean(covered))}

def backtest_summary(s):
    return {f'{h}d':backtest_forecast(s,h) for h in (5,20)}

all_stocks=[]; all_prices=[]; series={}; freshness={}
try:
    fin=pd.read_csv(FINANCIALS_URL)
    fin['Symbol']=fin['Symbol'].astype(str).str.strip().str.upper().str.replace('.','-',regex=False)
    fin_by_symbol={str(r['Symbol']):r for _,r in fin.iterrows()}
except Exception as exc:
    print('Financial dataset download failed:',exc)
    fin_by_symbol={}

print(f'Fetching {len(symbols)} symbols in efficient batches')
price_frames={}
BATCH_SIZE=12
for start in range(0,len(symbols),BATCH_SIZE):
    batch=symbols[start:start+BATCH_SIZE]
    print(f'Batch {start+1}-{start+len(batch)} / {len(symbols)}')
    try:
        frame=yf.download(batch,period='1y',interval='1d',auto_adjust=False,group_by='ticker',threads=False,progress=False,timeout=30)
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
        for col in ['Open','High','Low','Close','Volume']:
            if col not in h.columns: h[col]=h['Close'] if 'Close' in h.columns else np.nan
        for _,row in h.iterrows():
            close=row.get('Close')
            if pd.isna(close): continue
            prices.append({'symbol':sym,'ts':pd.Timestamp(row[date_col]).to_pydatetime().strftime('%Y-%m-%d %H:%M:%S'),
                           'open':safe_float(row.get('Open')),'high':safe_float(row.get('High')),
                           'low':safe_float(row.get('Low')),'close':safe_float(close),
                           'volume':None if pd.isna(row.get('Volume')) else int(row.get('Volume'))})
    if prices:
        all_stocks.extend(stocks); all_prices.extend(prices)
        freshness[sym]=prices[-1]['ts']
        series[sym]=pd.Series([p['close'] for p in prices],dtype=float)
        print(sym,len(prices),'observations')
aligned=pd.DataFrame(series).dropna()
benchmark=aligned.pct_change().dropna().mean(axis=1) if not aligned.empty else None
analyses={}
for sym, ser in series.items():
    h=price_frames.get(sym,pd.DataFrame())
    if h.empty or 'Close' not in h.columns:
        df=pd.DataFrame({'close':ser}).dropna()
    else:
        df=pd.DataFrame({
            'close':pd.to_numeric(h['Close'],errors='coerce'),
            'high':pd.to_numeric(h.get('High'),errors='coerce'),
            'low':pd.to_numeric(h.get('Low'),errors='coerce')
        }).dropna(subset=['close']).reset_index(drop=True)
    r=df['close'].pct_change().dropna()
    bm=None
    if benchmark is not None:
        bm=pd.concat([r,benchmark],axis=1).dropna()
        r=bm.iloc[:,0]; bm=bm.iloc[:,1]
    analyses[sym]=metrics(df,bm)
    analyses[sym]['backtest']=backtest_summary(series[sym])
    analyses[sym]['data_quality']={'observations':int(len(series[sym])),'freshness':str(max((p['ts'] for p in all_prices if p['symbol']==sym),default=None))}
    print('analysis',sym,analyses[sym].get('trend'),analyses[sym].get('forecast_20d'),analyses[sym].get('backtest',{}))

cache=ROOT/'web'/'data'/'market.json';cache.parent.mkdir(parents=True,exist_ok=True)
valid_symbols=sum(1 for sym in symbols if len(series.get(sym,[])) >= 30)
if len(all_stocks) < 400 or len(analyses) < 400 or valid_symbols < 400:
    raise RuntimeError(f'Collector produced an unsafe cache: {len(all_stocks)} stocks / {len(analyses)} analyses / {valid_symbols} valid price series')
cache.write_text(json.dumps({'generated_at':dt.datetime.now(dt.timezone.utc).isoformat().replace('+00:00','Z'),'stocks':all_stocks,'prices':all_prices,'analysis':analyses},separators=(',',':')))
print(f'Wrote static market cache: {cache} ({len(all_stocks)} stocks, {len(all_prices)} prices, {len(analyses)} analyses)')
