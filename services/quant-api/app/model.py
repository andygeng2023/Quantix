from pathlib import Path
import joblib
import numpy as np
import pandas as pd

ARTIFACT = Path(__file__).resolve().parent.parent / "artifacts" / "quantix_model.joblib"

FEATURES = [
    "ret_1d", "ret_5d", "ret_20d",
    "vol_5d", "vol_20d",
    "ma_gap_10", "ma_gap_50",
    "volume_z20", "rsi_14",
]

def make_features(df: pd.DataFrame) -> pd.DataFrame:
    required = {"open", "high", "low", "close", "volume"}
    missing = required - set(df.columns)
    if missing:
        raise ValueError(f"Missing columns: {sorted(missing)}")

    x = df.copy()
    x.index = pd.to_datetime(x.index)
    close = x["close"].astype(float)
    volume = x["volume"].astype(float)

    x["ret_1d"] = close.pct_change()
    x["ret_5d"] = close.pct_change(5)
    x["ret_20d"] = close.pct_change(20)
    x["vol_5d"] = x["ret_1d"].rolling(5).std()
    x["vol_20d"] = x["ret_1d"].rolling(20).std()
    x["ma_gap_10"] = close / close.rolling(10).mean() - 1
    x["ma_gap_50"] = close / close.rolling(50).mean() - 1
    vol_mean = volume.rolling(20).mean()
    vol_std = volume.rolling(20).std()
    x["volume_z20"] = (volume - vol_mean) / vol_std.replace(0, np.nan)

    delta = close.diff()
    gain = delta.clip(lower=0).rolling(14).mean()
    loss = (-delta.clip(upper=0)).rolling(14).mean()
    rs = gain / loss.replace(0, np.nan)
    x["rsi_14"] = 100 - (100 / (1 + rs))

    return x

def prepare_training_frame(df: pd.DataFrame, horizon_days: int = 5):
    x = make_features(df)
    future_return = x["close"].shift(-horizon_days) / x["close"] - 1
    x["target"] = (future_return > 0).astype(int)
    x = x.replace([np.inf, -np.inf], np.nan).dropna(subset=FEATURES + ["target"])
    return x[FEATURES], x["target"], x

def load_model():
    if not ARTIFACT.exists():
        return None
    return joblib.load(ARTIFACT)
