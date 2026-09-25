# Quantix

Quantix is a browser-based quantitative market research workspace. It keeps the existing PHP + MySQL + static JSON cache architecture so it remains practical on shared hosting such as InfinityFree, while the browser provides responsive charts, screening, comparison, validation and watchlist workflows.

## Features

- **Market universe:** 500+ cached symbols with company, sector and market-cap metadata.
- **Screener:** search by ticker/company/sector and filter by trend or volatility regime.
- **Symbol research:** historical price chart, live quote polling, risk/momentum metrics and fundamentals.
- **Forecast lab:** ensemble short/medium log-trend forecasts with uncertainty intervals.
- **Validation:** walk-forward diagnostic summaries including directional accuracy, MAE, RMSE and interval coverage.
- **Comparison:** side-by-side research metrics for up to six symbols.
- **Watchlist:** optional account-based saved symbols with CSRF-protected add/remove actions.
- **Responsive UI:** desktop, tablet and mobile layouts, touch-friendly controls, keyboard focus states and reduced-motion support.
- **Themes and density:** dark/light/system theme, compact mode, chart range and optional page refresh stored locally.
- **Resilient data path:** cached analytics are preferred; individual symbol pages can fall back to a direct Yahoo Finance chart request.
- **Live quotes:** symbol pages and the home market board use small, visibility-aware polling requests with exponential backoff.

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

The scheduled collector refreshes the research cache. Generated market data is not intentionally committed on every refresh, so the Git repository does not grow with each data run. Site deployment remains separate from the data refresh.

The application framework remains **PHP + MySQL + vanilla JavaScript/CSS + Chart.js**. The redesign does not introduce a frontend framework or change the hosting model.

## Data/model notes

- Historical market data is sourced through **yfinance/Yahoo Finance**.
- Live quotes are best-effort Yahoo Finance requests and can be delayed, rate-limited or unavailable.
- The 5-session and 20-session forecasts are statistical research outputs, not guarantees or personalized financial advice.
- Historical validation describes past model behavior; it does not establish future performance.
- The live endpoint uses Yahoo's reported previous close for daily change calculations.
- Uncached symbol calculations include Sortino ratio and a simple volatility regime when enough history is available.

## InfinityFree deployment

1. Import `sql/schema.sql` with phpMyAdmin.
2. Copy `web/config/database.example.php` to a private `web/config/database.php` and configure the database.
3. Upload the contents of `web/` to the website root.
4. Configure the GitHub Actions FTP secrets:
   - `INFINITYFREE_FTP_SERVER`
   - `INFINITYFREE_FTP_USERNAME`
   - `INFINITYFREE_FTP_PASSWORD`
   - `INFINITYFREE_FTP_SERVER_DIR`
5. The application deployment updates PHP/CSS/JS. The data-refresh workflow updates only `/data/market.json`.

Do not commit database passwords, ingest tokens or other credentials.

## Reliability and security

- Session cookies use HttpOnly and SameSite=Lax; HTTPS deployments also set Secure.
- Login, registration and watchlist mutations use CSRF tokens.
- Successful login regenerates the session ID.
- Watchlist changes are POST-only rather than state-changing GET links.
- Live and uncached Yahoo requests use short timeouts and cURL when available, with a stream fallback.
- Symbol input is allow-listed before outbound requests.
- Live quote requests back off after failures and stop while the page is hidden.

## Browser support

Quantix targets current Chrome, Edge, Firefox and Safari on desktop and mobile. The layout supports narrow phone widths through large desktop displays. JavaScript is required for interactive charts and live polling; cached pages remain readable when live requests fail.

## Development checklist

Before deploying:

- Run PHP syntax checks on changed PHP files with `php -l`.
- Load home, screener, symbol, comparison, settings and watchlist flows.
- Test phone, tablet and desktop widths.
- Test dark/light/system themes and reduced-motion preferences.
- Confirm live quote failures leave cached content usable.
- Verify the InfinityFree deployment has a private `web/config/database.php` that is not tracked by Git.

## License

This project is open source. See the repository for the applicable license.
