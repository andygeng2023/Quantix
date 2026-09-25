from __future__ import annotations
import math
from datetime import datetime, timezone
from functools import lru_cache
import numpy as np
import pandas as pd
import yfinance as yf
from fastapi import FastAPI, HTTPException, Query
from fastapi.middleware.cors import CORSMiddleware

app=FastAPI(title="Quantix API",version="2.0.0")
app.add_middleware(CORSMiddleware,allow_origins=["*"],allow_credentials=False,allow_methods=["*"],allow_headers=["*"])
DEFAULT_SYMBOLS=["AAPL","MSFT","NVDA","AMZN","GOOGL","META","TSLA","AVGO","AMD","NFLX","JPM","V","MA","COST","WMT","LLY","ORCL","CRM","ADBE","QCOM","INTC","CSCO","IBM","MU","AMAT","INTU","NOW","UBER","SHOP","PLTR","SPY","QQQ","IWM","DIA","GLD","SLV","TLT"]

def clean(s):
    s=s.strip().upper()
    if not s or len(s)>20 or any(c not in "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789.-^=" for c in s): raise HTTPException(400,"Invalid ticker")
    return s

@lru_cache(maxsize=256)
def raw_history(symbol,period,interval):
    return yf.Ticker(symbol).history(period=period,interval=interval,auto_adjust=False,repair=True)

def history(symbol,period,interval):
    try: df=raw_history(symbol,period,interval)
    except Exception as e: raise HTTPException(502,"Yahoo Finance request failed") from e
    if df is None or df.empty: return []
    if isinstance(df.columns,pd.MultiIndex): df.columns=df.columns.get_level_values(0)
    df=df.reset_index(); tc="Datetime" if "Datetime" in df.columns else "Date"; out=[]
    for _,r in df.iterrows():
        ts=pd.Timestamp(r[tc]); ts=ts.tz_localize("UTC") if ts.tzinfo is None else ts
        out.append({"time":ts.isoformat(),"open":float(r["Open"]),"high":float(r["High"]),"low":float(r["Low"]),"close":float(r["Close"]),"volume":int(r["Volume"]) if pd.notna(r["Volume"]) else 0})
    return out

def indicators(data):
    c=pd.Series([x["close"] for x in data],dtype=float)
    if c.empty: return {"sma20":0,"sma50":0,"rsi14":0,"annualized_volatility":0,"momentum_20":0}
    d=c.diff(); gain=d.clip(lower=0).rolling(14).mean(); loss=(-d.clip(upper=0)).rolling(14).mean(); rs=gain.iloc[-1]/loss.iloc[-1] if loss.iloc[-1] else math.inf
    ret=c.pct_change().dropna()
    return {"sma20":float(c.rolling(20).mean().iloc[-1] if len(c)>=20 else c.mean()),"sma50":float(c.rolling(50).mean().iloc[-1] if len(c)>=50 else c.mean()),"rsi14":float(100-100/(1+rs)),"annualized_volatility":float(ret.tail(20).std()*np.sqrt(252) if len(ret)>1 else 0),"momentum_20":float(c.iloc[-1]/c.iloc[-21]-1 if len(c)>=21 else 0)}

def quote(symbol):
    data=history(symbol,"5d","1m") or history(symbol,"1mo","1d")
    if not data: raise HTTPException(404,"No market data found")
    last=data[-1]; prev=data[-2] if len(data)>1 else last; ch=last["close"]-prev["close"]
    return {"symbol":symbol,"price":last["close"],"change":ch,"change_pct":ch/prev["close"] if prev["close"] else 0,"volume":last["volume"],"timestamp":last["time"],"provider":"yfinance / Yahoo Finance"}

def score(symbol):
    data=history(symbol,"1y","1d")
    if len(data)<30: return {"symbol":symbol,"score":.5,"signal":"neutral","price":data[-1]["close"] if data else 0}
    i=indicators(data); price=data[-1]["close"]; raw=.5+max(-.2,min(.2,i["momentum_20"]*2))+(0.08 if price>=i["sma50"] else -.08)
    if i["rsi14"]>70: raw-=.04
    if i["rsi14"]<30: raw+=.04
    s=max(.05,min(.95,raw))
    return {"symbol":symbol,"price":price,"score":s,"signal":"positive" if s>=.58 else "negative" if s<=.42 else "neutral",**i}

@app.get("/health")
def health(): return {"status":"ok","provider":"yfinance","time":datetime.now(timezone.utc).isoformat()}

@app.get("/v1/status")
def status(): return {"status":"ok","provider":"yfinance","live_symbols":0,"universe_size":len(DEFAULT_SYMBOLS),"note":"Quotes are fetched on demand through yfinance.","time":datetime.now(timezone.utc).isoformat()}

@app.get("/v1/universe")
def universe(): return {"count":len(DEFAULT_SYMBOLS),"symbols":DEFAULT_SYMBOLS}

@app.get("/v1/quote/{symbol}")
def get_quote(symbol): return quote(clean(symbol))

@app.get("/v1/history/{symbol}")
def get_history(symbol,period: str=Query("5y",pattern="^(5d|1mo|3mo|6mo|1y|2y|5y|max)$"),interval: str=Query("1d",pattern="^(1m|2m|5m|15m|30m|60m|90m|1h|1d|1wk|1mo|3mo)$")):
    s=clean(symbol); data=history(s,period,interval); return {"symbol":s,"period":period,"interval":interval,"count":len(data),"rows":data}

@app.get("/v1/stock/{symbol}")
def get_stock(symbol):
    s=clean(symbol); t=yf.Ticker(s); q=quote(s); daily=history(s,"1y","1d"); info={}
    try:
        f=t.fast_info; info={"currency":f.get("currency"),"exchange":f.get("exchange"),"year_high":f.get("yearHigh"),"year_low":f.get("yearLow"),"market_cap":f.get("marketCap")}
    except Exception: pass
    return {"quote":q,"indicators":indicators(daily),"info":info}

@app.get("/v1/scanner")
def scanner():
    result=[]
    for s in DEFAULT_SYMBOLS:
        try: result.append(score(s))
        except Exception: pass
    result.sort(key=lambda x:abs(x.get("score",.5)-.5),reverse=True)
    return {"count":len(result),"results":result}

@app.get("/v1/news/{symbol}")
def news(symbol):
    s=clean(symbol)
    try: items=yf.Ticker(s).news or []
    except Exception: items=[]
    return {"symbol":s,"items":items[:20]}

@app.get("/v1/summary/{symbol}")
def summary(symbol):
    s=clean(symbol); q=get_stock(s); sc=score(s)
    return {"symbol":s,"headline":s+" quantitative research brief","points":[f"Latest available price: {q['quote']['price']:.2f} ({q['quote']['change_pct']*100:.2f}% latest interval).",f"20-session momentum: {q['indicators']['momentum_20']*100:.2f}%; RSI(14): {q['indicators']['rsi14']:.1f}.",f"Research score: {sc.get('score',.5)*100:.1f}/100.","These statistics describe historical data and are not guarantees or trading instructions."]}
