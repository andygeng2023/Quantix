from datetime import datetime, timezone
from typing import List
import pandas as pd
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field
from .model import load_model, make_features, FEATURES

app = FastAPI(title="Quantix Quant API", version="0.3.0")

class OHLCVBar(BaseModel):
    timestamp: str
    open: float
    high: float
    low: float
    close: float
    volume: float = Field(ge=0)

class PredictionRequest(BaseModel):
    ticker: str
    horizon: str = "5D"
    bars: List[OHLCVBar] = Field(min_length=60)

@app.get("/health")
def health():
    return {"status":"ok","service":"quantix","timestamp":datetime.now(timezone.utc).isoformat(),"model_loaded":load_model() is not None}

@app.get("/v1/model")
def model_info():
    bundle = load_model()
    if bundle is None:
        return {"status":"not_trained","features":FEATURES}
    return {
        "status":"ready",
        "model_type":bundle["model_type"],
        "horizon_days":bundle["horizon_days"],
        "features":bundle["features"],
        "metrics":bundle["metrics"],
        "trained_at":bundle.get("trained_at"),
    }

@app.post("/v1/predict")
def predict(request: PredictionRequest):
    bundle = load_model()
    if bundle is None:
        raise HTTPException(status_code=503, detail="Model is not trained yet")
    df = pd.DataFrame([b.model_dump() for b in request.bars])
    df["timestamp"] = pd.to_datetime(df["timestamp"], utc=True)
    df = df.sort_values("timestamp").set_index("timestamp")
    features = make_features(df).dropna()
    if features.empty:
        raise HTTPException(status_code=422, detail="Not enough valid bars to compute features")
    row = features.iloc[[-1]][bundle["features"]]
    probability = float(bundle["model"].predict_proba(row)[0,1])
    return {
        "ticker":request.ticker.upper(),
        "horizon":f'{bundle["horizon_days"]}D',
        "probability_up":probability,
        "probability_down":1-probability,
        "signal":"bullish" if probability>=0.55 else "bearish" if probability<=0.45 else "neutral",
        "model_version":bundle.get("model_version","unversioned"),
        "generated_at":datetime.now(timezone.utc).isoformat(),
    }

@app.post("/v1/predict-from-csv")
def predict_from_csv():
    raise HTTPException(status_code=403, detail="CSV inference is a local training/development workflow, not a public API")
