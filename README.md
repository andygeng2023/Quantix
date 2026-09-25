# Quantix

Quantix is a quantitative market-intelligence platform for market data, analytics, research, backtesting and model signals.

## Deployment
InfinityFree deployment publishes `web/` over FTP. Configure repository secrets `INFINITYFREE_FTP_SERVER`, `INFINITYFREE_FTP_USERNAME`, `INFINITYFREE_FTP_PASSWORD`, and `INFINITYFREE_FTP_SERVER_DIR` (normally `/htdocs/`). Keep Bloomberg credentials off GitHub. Run BLPAPI/model services on infrastructure that supports the required Bloomberg entitlements and runtime.

Predictions are research analytics, not guaranteed returns or investment advice.
