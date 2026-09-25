"""Train Quantix from Bloomberg-exported OHLCV data.

Input CSV columns: date, open, high, low, close, volume.
The split is chronological to prevent look-ahead leakage.
"""
from pathlib import Path
import argparse
import joblib
import pandas as pd
from sklearn.ensemble import HistGradientBoostingClassifier
from sklearn.metrics import accuracy_score, balanced_accuracy_score, roc_auc_score
from app.model import FEATURES, prepare_training_frame

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--csv", required=True)
    parser.add_argument("--horizon", type=int, default=5)
    args = parser.parse_args()

    df = pd.read_csv(args.csv, parse_dates=["date"]).sort_values("date").set_index("date")
    X, y, frame = prepare_training_frame(df, args.horizon)

    if len(X) < 500:
        raise ValueError(f"Need at least 500 usable rows; found {len(X)}")

    split = int(len(X) * 0.8)
    X_train, X_test = X.iloc[:split], X.iloc[split:]
    y_train, y_test = y.iloc[:split], y.iloc[split:]

    model = HistGradientBoostingClassifier(
        learning_rate=0.05,
        max_iter=250,
        max_leaf_nodes=15,
        l2_regularization=1.0,
        random_state=42,
    )
    model.fit(X_train, y_train)

    proba = model.predict_proba(X_test)[:, 1]
    pred = (proba >= 0.5).astype(int)

    metrics = {
        "test_rows": len(X_test),
        "accuracy": accuracy_score(y_test, pred),
        "balanced_accuracy": balanced_accuracy_score(y_test, pred),
        "roc_auc": roc_auc_score(y_test, proba) if y_test.nunique() == 2 else None,
        "horizon_days": args.horizon,
        "features": FEATURES,
    }
    print(metrics)

    artifact = Path(__file__).resolve().parent / "artifacts"
    artifact.mkdir(exist_ok=True)
    joblib.dump({"model": model, "features": FEATURES, "horizon_days": args.horizon, "metrics": metrics}, artifact / "quantix_model.joblib")
    print(f"Saved {artifact / 'quantix_model.joblib'}")

if __name__ == "__main__":
    main()
