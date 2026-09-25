from datetime import datetime, timezone
from pathlib import Path
import pandas as pd
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from .model import load_model, make_features, FEATURES

app = FastAPI(title="Quantix Quant API", version="0.2.0")

class PredictionRequest(BaseModel):
    ticker: str
    horizon: str = "5D"

@app.get("/health")
def health():
    return {"status": "ok", "service": "quantix", "timestamp": datetime.now(timezone.utc).isoformat(), "model_loaded": load_model() is not None}

@app.get("/v1/model")
def model_info():
    bundle = load_model()
    if bundle is None:
        return {"status": "not_trained"}
    return {"status": "ready", "horizon_days": bundle["horizon_days"], "metrics": bundle["metrics"]}

@app.post("/v1/predict")
def predict(request: PredictionRequest):
    bundle = load_model()
    if bundle is None:
        raise HTTPException(status_code=503, detail="Model is not trained yet")
    # Production inference should receive the latest authorized OHLCV/features
    # from the server-side data store, never from the browser.
    raise HTTPException(status_code=501, detail="Connect the latest feature row from the authorized market-data store")

@app.post("/v1/predict-from-csv")
def predict_from_csv():
    raise HTTPException(status_code=403, detail="CSV inference is a local training/development workflow, not a public API")
