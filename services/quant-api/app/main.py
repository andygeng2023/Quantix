import os,asyncio
from datetime import datetime,timezone
import pandas as pd
from fastapi import FastAPI,HTTPException,WebSocket
from pydantic import BaseModel,Field
from .model import load_model,make_features,FEATURES
from .market_data import MarketStore,TwelveDataStream,provider_status
app=FastAPI(title="Quantix Quant API",version="0.6.0")
WATCHLIST=["NVDA","MSFT","AAPL","AMZN","META","GOOGL","TSLA","AMD","AVGO","NFLX","QQQ","SPY","IWM","GLD","TLT"]
store=MarketStore(); stream=TwelveDataStream(store,WATCHLIST)
class OHLCVBar(BaseModel): timestamp:str; open:float; high:float; low:float; close:float; volume:float=Field(ge=0)
class PredictionRequest(BaseModel): ticker:str; horizon:str="5D"; bars:list[OHLCVBar]=Field(min_length=60)
@app.on_event("startup")
def startup():
    if os.getenv("QUANTIX_AUTOSTART_STREAM","1")=="1": stream.start()
@app.on_event("shutdown")
def shutdown(): stream.stop()
@app.get("/health")
def health(): return {"status":"ok","timestamp":datetime.now(timezone.utc).isoformat(),"model_loaded":load_model() is not None,"providers":provider_status()}
@app.get("/v1/status")
def status(): return {"status":"ok","providers":provider_status(),"stream_running":bool(stream.thread and stream.thread.is_alive()),"latest":{s:store.snapshot(s)[1] for s in WATCHLIST if store.snapshot(s)[1]}}
@app.get("/v1/model")
def model_info():
    b=load_model()
    if not b:return {"status":"not_trained","features":FEATURES}
    return {"status":"ready","model_type":b.get("model_type","HistGradientBoostingClassifier"),"horizon_days":b["horizon_days"],"features":b["features"],"metrics":b["metrics"],"trained_at":b.get("trained_at"),"model_version":b.get("model_version","unknown")}
def score(symbol):
    bars,tick=store.snapshot(symbol); b=load_model()
    if not b or len(bars)<60:return None
    df=pd.DataFrame(bars); df.timestamp=pd.to_datetime(df.timestamp,utc=True); f=make_features(df.set_index("timestamp")).dropna()
    if f.empty:return None
    p=float(b["model"].predict_proba(f.iloc[[-1]][b["features"]])[0,1]); z=f.iloc[-1]
    return {"ticker":symbol,"price":tick["price"] if tick else float(df.close.iloc[-1]),"probability_up":p,"signal":"positive" if p>=.55 else "negative" if p<=.45 else "neutral","rsi":float(z.rsi_14),"momentum_10":float(z.momentum_10),"volatility":float(z.vol_20d)}
@app.get("/v1/scanner")
def scanner():
    r=[score(s) for s in WATCHLIST]; r=[x for x in r if x]; return {"count":len(r),"results":sorted(r,key=lambda x:abs(x["probability_up"]-.5),reverse=True)}
@app.get("/v1/trending")
def trending():
    out=[]
    for s in WATCHLIST:
        bars,t=store.snapshot(s)
        if t and len(bars)>=6:
            c=pd.Series([b["close"] for b in bars]); out.append({"ticker":s,"price":t["price"],"change_5m":float(c.iloc[-1]/c.iloc[-6]-1),"timestamp":t["timestamp"]})
    return {"results":sorted(out,key=lambda x:abs(x["change_5m"]),reverse=True)}
@app.get("/v1/summary")
def summary():
    r=scanner()["results"]
    if not r:return {"headline":"Waiting for live data","points":["Connect a provider and accumulate at least 60 one-minute bars.","Train a model before model-ranked research candidates become available."]}
    pos=sum(x["probability_up"]>.55 for x in r); neg=sum(x["probability_up"]<.45 for x in r)
    return {"headline":f"Quantitative snapshot: {pos} positive, {neg} negative, {len(r)-pos-neg} neutral","points":["Signals are statistical research outputs, not guaranteed outcomes.","Ranking uses model probability, momentum, RSI and volatility.","Provider timestamps determine observed market-data latency."]}
@app.post("/v1/predict")
def predict(req:PredictionRequest):
    b=load_model()
    if not b:raise HTTPException(503,"Model is not trained yet")
    df=pd.DataFrame([x.model_dump() for x in req.bars]); df.timestamp=pd.to_datetime(df.timestamp,utc=True); f=make_features(df.set_index("timestamp")).dropna()
    if f.empty:raise HTTPException(422,"Not enough valid bars")
    p=float(b["model"].predict_proba(f.iloc[[-1]][b["features"]])[0,1])
    return {"ticker":req.ticker.upper(),"horizon":f'{b["horizon_days"]}D',"probability_up":p,"probability_down":1-p,"signal":"positive" if p>=.55 else "negative" if p<=.45 else "neutral","model_version":b.get("model_version","unknown")}
@app.websocket("/ws")
async def ws_endpoint(ws:WebSocket):
    await ws.accept()
    try:
        while True:
            await ws.send_json({"type":"market","timestamp":datetime.now(timezone.utc).isoformat(),"latest":{s:store.snapshot(s)[1] for s in WATCHLIST if store.snapshot(s)[1]}}); await asyncio.sleep(.5)
    except Exception: pass
