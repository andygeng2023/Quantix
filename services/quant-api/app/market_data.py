import json,os,threading,time
from collections import defaultdict,deque
from datetime import datetime,timezone
import websocket
class MarketStore:
    def __init__(self,max_bars=1000):
        self.bars=defaultdict(lambda:deque(maxlen=max_bars)); self.latest={}; self.lock=threading.RLock()
    def add_tick(self,symbol,price,volume=0,timestamp_ms=None):
        ts=datetime.fromtimestamp((timestamp_ms or time.time()*1000)/1000,tz=timezone.utc); p=float(price)
        with self.lock:
            self.latest[symbol]={"symbol":symbol,"price":p,"volume":float(volume),"timestamp":ts.isoformat()}
            bucket=ts.replace(second=0,microsecond=0); items=self.bars[symbol]
            if items and items[-1]["timestamp"]==bucket.isoformat():
                b=items[-1]; b["high"]=max(b["high"],p); b["low"]=min(b["low"],p); b["close"]=p; b["volume"]+=float(volume)
            else: items.append({"timestamp":bucket.isoformat(),"open":p,"high":p,"low":p,"close":p,"volume":float(volume)})
    def snapshot(self,symbol):
        with self.lock:return list(self.bars[symbol]),self.latest.get(symbol)
class TwelveDataStream:
    def __init__(self,store,symbols): self.store,self.symbols=store,symbols; self.ws=None; self.thread=None
    def start(self):
        key=os.getenv("TWELVEDATA_API_KEY")
        if not key or (self.thread and self.thread.is_alive()): return False
        def run():
            def opened(ws): ws.send(json.dumps({"action":"subscribe","params":{"symbols":",".join(self.symbols)}}))
            def message(ws,raw):
                try:
                    m=json.loads(raw); s=m.get("symbol"); p=m.get("price")
                    if s and p is not None:self.store.add_tick(s,p,m.get("day_volume",0),m.get("timestamp"))
                except Exception: pass
            self.ws=websocket.WebSocketApp("wss://ws.twelvedata.com/v1/quotes/price?apikey="+key,on_open=opened,on_message=message)
            self.ws.run_forever(ping_interval=20,ping_timeout=10)
        self.thread=threading.Thread(target=run,daemon=True); self.thread.start(); return True
    def stop(self):
        if self.ws:self.ws.close()
def provider_status(): return {"twelvedata_configured":bool(os.getenv("TWELVEDATA_API_KEY")),"finnhub_configured":bool(os.getenv("FINNHUB_API_KEY"))}
