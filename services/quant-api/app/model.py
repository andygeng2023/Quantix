from pathlib import Path
import joblib
import numpy as np
import pandas as pd

ARTIFACT=Path(__file__).resolve().parent.parent/"artifacts"/"quantix_model.joblib"
FEATURES=["ret_1d","ret_5d","ret_20d","vol_5d","vol_20d","ma_gap_10","ma_gap_50","volume_z20","rsi_14","range_pct","close_location","volatility_ratio","momentum_10","momentum_30","trend_strength"]

def make_features(df):
    required={"open","high","low","close","volume"}
    missing=required-set(df.columns)
    if missing: raise ValueError(f"Missing columns: {sorted(missing)}")
    x=df.copy(); x.index=pd.to_datetime(x.index,utc=True)
    c=x.close.astype(float); h=x.high.astype(float); l=x.low.astype(float); v=x.volume.astype(float)
    x["ret_1d"]=c.pct_change(); x["ret_5d"]=c.pct_change(5); x["ret_20d"]=c.pct_change(20)
    x["vol_5d"]=x.ret_1d.rolling(5).std(); x["vol_20d"]=x.ret_1d.rolling(20).std()
    x["ma_gap_10"]=c/c.rolling(10).mean()-1; x["ma_gap_50"]=c/c.rolling(50).mean()-1
    vm=v.rolling(20).mean(); vs=v.rolling(20).std(); x["volume_z20"]=(v-vm)/vs.replace(0,np.nan)
    d=c.diff(); gain=d.clip(lower=0).rolling(14).mean(); loss=(-d.clip(upper=0)).rolling(14).mean()
    rs=gain/loss.replace(0,np.nan); x["rsi_14"]=100-100/(1+rs)
    x["range_pct"]=(h-l)/c.replace(0,np.nan); x["close_location"]=(c-l)/(h-l).replace(0,np.nan)
    x["volatility_ratio"]=x.vol_5d/x.vol_20d.replace(0,np.nan)
    x["momentum_10"]=c/c.shift(10)-1; x["momentum_30"]=c/c.shift(30)-1
    x["trend_strength"]=c.rolling(10).mean()/c.rolling(30).mean()-1
    return x.replace([np.inf,-np.inf],np.nan)

def prepare_training_frame(df,horizon_days=5):
    x=make_features(df); future=x.close.shift(-horizon_days)/x.close-1
    x["target"]=(future>0).astype(int); x=x.dropna(subset=FEATURES+["target"])
    return x[FEATURES],x.target,x

def load_model():
    return joblib.load(ARTIFACT) if ARTIFACT.exists() else None
