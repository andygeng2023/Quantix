# Quantix

Quantix is a browser-based quantitative market research workspace. It is designed around a static market-data cache plus lightweight PHP pages, so the hosted site does not depend on a long-running Python process.

## Architecture

```
GitHub Actions
  ├─ Python + yfinance → generate web/data/market.json
  └─ FTP → InfinityFree /data/
                    ↓
             PHP + static cache
                    ↓
              Browser + Chart.js
                    ↘ live quote endpoint
```

The scheduled collector refreshes the full research cache. The repository does **not** commit each generated cache, preventing the market-data history from expanding the Git repository on every refresh. The normal site deployment remains separate and only runs when application code changes.

## Features

- 500+ symbol research universe.
- Search and multi-dimensional screener.
- Side-by-side comparison.
- Statistical 5-session and 20-session forecasts.
- Ensemble log-trend model with uncertainty intervals.
- Walk-forward validation diagnostics.
- Live quote polling on symbol pages.
- Dark/light themes, compact density and responsive layout.
- Fixed bottom navigation on desktop and mobile.
- Local browser preferences.
- Optional account/watchlist workspace.
- Uncached ticker lookup when the hosting environment permits outbound requests.

## Data and model notes

Historical data is sourced through yfinance/Yahoo Finance. Full-cache refreshes are scheduled rather than dependent on the visitor's browser. Live quote polling is a separate best-effort request and can fail or be delayed.

Forecasts are statistical research outputs, not guarantees. Validation statistics describe historical tests and should not be interpreted as future-performance promises.

## InfinityFree setup

1. Import `sql/schema.sql` using phpMyAdmin.
2. Copy `web/config/database.example.php` to a private `web/config/database.php` and configure the database.
3. Upload the contents of `web/` to the website root.
4. Configure the GitHub Actions FTP secrets:
   - `INFINITYFREE_FTP_SERVER`
   - `INFINITYFREE_FTP_USERNAME`
   - `INFINITYFREE_FTP_PASSWORD`
   - `INFINITYFREE_FTP_SERVER_DIR`
5. The application deployment updates PHP/CSS/JS. The data-refresh workflow updates only `/data/market.json`.

No database password or collector credential belongs in Git.
