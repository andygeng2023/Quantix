import os,json,datetime as dt
from pathlib import Path
import requests
import yfinance as yf

symbols=[x.strip().upper() for x in os.getenv('QUANTIX_SYMBOLS','AAPL,MSFT,NVDA,AMZN,GOOGL,META,TSLA').split(',') if x.strip()]
out=Path(os.getenv('QUANTIX_INGEST_DIR','../data/ingest'));out.mkdir(parents=True,exist_ok=True)
ingest_url=os.getenv('QUANTIX_INGEST_URL','').strip()
ingest_token=os.getenv('QUANTIX_INGEST_TOKEN','').strip()

for sym in symbols:
    t=yf.Ticker(sym)
    try: info=t.info or {}
    except Exception: info={}
    stocks=[{'symbol':sym,'name':info.get('longName') or info.get('shortName'),'exchange':info.get('exchange'),'sector':info.get('sector'),'industry':info.get('industry'),'market_cap':info.get('marketCap'),'pe':info.get('trailingPE'),'eps':info.get('trailingEps'),'dividend_yield':info.get('dividendYield'),'beta':info.get('beta'),'revenue_growth':info.get('revenueGrowth'),'eps_growth':info.get('earningsGrowth'),'updated_at':dt.datetime.utcnow().strftime('%Y-%m-%d %H:%M:%S')}]
    prices=[]
    h=t.history(period='1y',auto_adjust=False)
    for idx,row in h.iterrows():
        prices.append({'symbol':sym,'ts':idx.to_pydatetime().strftime('%Y-%m-%d %H:%M:%S'),'open':None if row.Open!=row.Open else float(row.Open),'high':None if row.High!=row.High else float(row.High),'low':None if row.Low!=row.Low else float(row.Low),'close':None if row.Close!=row.Close else float(row.Close),'volume':None if row.Volume!=row.Volume else int(row.Volume)})
    payload={'generated_at':dt.datetime.utcnow().isoformat()+'Z','stocks':stocks,'prices':prices}
    if ingest_url and ingest_token:
        r=requests.post(ingest_url,headers={'X-Quantix-Token':ingest_token},json=payload,timeout=60)
        r.raise_for_status()
        print(sym,r.text)
    else:
        path=out/f'quantix-{sym}-{dt.datetime.utcnow().strftime("%Y%m%d%H%M%S")}.json'
        path.write_text(json.dumps(payload,separators=(',',':')))
        print(path)
