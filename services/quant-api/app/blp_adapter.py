from dataclasses import dataclass
from typing import Any
@dataclass
class BLPConfig: host:str; port:int=8194
class BloombergAdapter:
 def __init__(self,config:BLPConfig): self.config=config; self._session:Any=None
 def connect(self):
  import blpapi
  o=blpapi.SessionOptions();o.setServerHost(self.config.host);o.setServerPort(self.config.port);self._session=blpapi.Session(o)
  if not self._session.start(): raise RuntimeError('Unable to start Bloomberg BLPAPI session')
 def close(self):
  if self._session:self._session.stop();self._session=None
