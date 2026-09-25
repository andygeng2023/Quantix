from pathlib import Path
from datetime import datetime,timezone
import argparse,joblib,pandas as pd
from sklearn.ensemble import HistGradientBoostingClassifier
from sklearn.metrics import accuracy_score,balanced_accuracy_score,roc_auc_score
from app.model import FEATURES,prepare_training_frame
p=argparse.ArgumentParser(); p.add_argument("--csv",required=True); p.add_argument("--horizon",type=int,default=5); a=p.parse_args()
df=pd.read_csv(a.csv,parse_dates=["date"]).sort_values("date").set_index("date")
X,y,_=prepare_training_frame(df,a.horizon)
if len(X)<500: raise ValueError(f"Need at least 500 usable rows; found {len(X)}")
split=int(len(X)*.8); model=HistGradientBoostingClassifier(learning_rate=.04,max_iter=350,max_leaf_nodes=21,l2_regularization=1.5,random_state=42)
model.fit(X.iloc[:split],y.iloc[:split]); proba=model.predict_proba(X.iloc[split:])[:,1]; pred=(proba>=.5).astype(int)
metrics={"test_rows":len(y.iloc[split:]),"accuracy":accuracy_score(y.iloc[split:],pred),"balanced_accuracy":balanced_accuracy_score(y.iloc[split:],pred),"roc_auc":roc_auc_score(y.iloc[split:],proba) if y.iloc[split:].nunique()==2 else None,"horizon_days":a.horizon,"features":FEATURES}
out=Path(__file__).resolve().parent/"artifacts"; out.mkdir(exist_ok=True)
joblib.dump({"model":model,"features":FEATURES,"horizon_days":a.horizon,"metrics":metrics,"model_type":"HistGradientBoostingClassifier","model_version":"quantix-hgb-0.6","trained_at":datetime.now(timezone.utc).isoformat()},out/"quantix_model.joblib")
print(metrics)
