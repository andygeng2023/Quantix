import os,json,datetime as dt,time
from pathlib import Path
import requests
import yfinance as yf

raw_symbols=os.getenv('QUANTIX_SYMBOLS','').strip()
symbols=[x.strip().upper() for x in (raw_symbols or 'AAPL,MSFT,NVDA,AMZN,GOOGL,META,TSLA').split(',') if x.strip()]
out=Path(os.getenv('QUANTIX_INGEST_DIR','../data/ingest'));out.mkdir(parents=True,exist_ok=True)
ingest_url=os.getenv('QUANTIX_INGEST_URL','').strip()
ingest_token=os.getenv('QUANTIX_INGEST_TOKEN','').strip()
all_stocks=[]
all_prices=[]

for sym in symbols:
    t=yf.Ticker(sym)
    try: info=t.info or {}
    except Exception: info={}
    stocks=[{'symbol':sym,'name':info.get('longName') or info.get('shortName'),'exchange':info.get('exchange'),'sector':info.get('sector'),'industry':info.get('industry'),'market_cap':info.get('marketCap'),'pe':info.get('trailingPE'),'eps':info.get('trailingEps'),'dividend_yield':info.get('dividendYield'),'beta':info.get('beta'),'revenue_growth':info.get('revenueGrowth'),'eps_growth':info.get('earningsGrowth'),'updated_at':dt.datetime.now(dt.timezone.utc).strftime('%Y-%m-%d %H:%M:%S')}]
    prices=[]
    h=t.history(period='1y',auto_adjust=False)
    for idx,row in h.iterrows():
        prices.append({'symbol':sym,'ts':idx.to_pydatetime().strftime('%Y-%m-%d %H:%M:%S'),'open':None if row.Open!=row.Open else float(row.Open),'high':None if row.High!=row.High else float(row.High),'low':None if row.Low!=row.Low else float(row.Low),'close':None if row.Close!=row.Close else float(row.Close),'volume':None if row.Volume!=row.Volume else int(row.Volume)})
    payload={'generated_at':dt.datetime.now(dt.timezone.utc).isoformat().replace('+00:00','Z'),'stocks':stocks,'prices':prices}
    all_stocks.extend(stocks)
    all_prices.extend(prices)
    if ingest_url and ingest_token:
        last_error=None
        for attempt in range(1,4):
            try:
                r=requests.post(
                    ingest_url,
                    headers={
                        'X-Quantix-Token':ingest_token,
                        'User-Agent':'Quantix-Data-Collector/1.0',
                        'Accept':'application/json',
                    },
                    json=payload,
                    timeout=(15,90),
                )
                print(f'{sym}: HTTP {r.status_code} {r.text[:500]}')
                r.raise_for_status()
                last_error=None
                break
            except requests.RequestException as exc:
                last_error=exc
                print(f'{sym}: ingest attempt {attempt}/3 failed: {type(exc).__name__}: {exc}')
                if attempt < 3:
                    time.sleep(5 * attempt)
        if last_error:
            raise last_error
    else:
        path=out/f'quantix-{sym}-{dt.datetime.now(dt.timezone.utc).strftime("%Y%m%d%H%M%S")}.json'
        path.write_text(json.dumps(payload,separators=(',',':')))
        print(path)

cache=Path('web/data/market.json')
cache.parent.mkdir(parents=True,exist_ok=True)
cache.write_text(json.dumps({'generated_at':dt.datetime.now(dt.timezone.utc).isoformat().replace('+00:00','Z'),'stocks':all_stocks,'prices':all_prices},separators=(',',':')))
print(f'Wrote static market cache: {cache} ({len(all_stocks)} stocks, {len(all_prices)} prices)')
