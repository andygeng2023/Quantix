import json,os,threading,time
from dataclasses import dataclass
import websocket
@dataclass
class Tick:
    symbol:str; price:float; volume:float; timestamp_ms:int; provider:str
class BaseStream:
    name="base"
    def __init__(self,on_tick,symbols): self.on_tick,self.symbols=on_tick,symbols; self.ws=None; self.thread=None
    def stop(self):
        if self.ws:self.ws.close()
class TwelveDataStream(BaseStream):
    name="twelvedata"
    def start(self):
        key=os.getenv("TWELVEDATA_API_KEY")
        if not key:return False
        def run():
            def opened(ws):ws.send(json.dumps({"action":"subscribe","params":{"symbols":",".join(self.symbols)}}))
            def message(ws,raw):
                try:
                    m=json.loads(raw);s=m.get("symbol");p=m.get("price")
                    if s and p is not None:self.on_tick(Tick(s,float(p),float(m.get("day_volume") or 0),int(float(m.get("timestamp") or time.time())*1000),self.name))
                except Exception:pass
            self.ws=websocket.WebSocketApp(f"wss://ws.twelvedata.com/v1/quotes/price?apikey={key}",on_open=opened,on_message=message);self.ws.run_forever(ping_interval=20,ping_timeout=10)
        self.thread=threading.Thread(target=run,daemon=True);self.thread.start();return True
class FinnhubStream(BaseStream):
    name="finnhub"
    def start(self):
        key=os.getenv("FINNHUB_API_KEY")
        if not key:return False
        def run():
            def opened(ws):
                for s in self.symbols:ws.send(json.dumps({"type":"subscribe","symbol":s}))
            def message(ws,raw):
                try:
                    m=json.loads(raw)
                    for t in m.get("data",[]):
                        if t.get("s") and t.get("p") is not None:self.on_tick(Tick(t["s"],float(t["p"]),float(t.get("v") or 0),int(t.get("t") or time.time()*1000),self.name))
                except Exception:pass
            self.ws=websocket.WebSocketApp(f"wss://ws.finnhub.io?token={key}",on_open=opened,on_message=message);self.ws.run_forever(ping_interval=20,ping_timeout=10)
        self.thread=threading.Thread(target=run,daemon=True);self.thread.start();return True
def configured():return {"twelvedata":bool(os.getenv("TWELVEDATA_API_KEY")),"finnhub":bool(os.getenv("FINNHUB_API_KEY"))}
