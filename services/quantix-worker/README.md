# Quantix Cloud API

Cloudflare Worker + Durable Object backend for Quantix. Your computer does not need to stay on.

## Configure

Set the provider key as a Cloudflare secret, never in the frontend:

    npx wrangler secret put TWELVEDATA_API_KEY

Optional live universe:

    npx wrangler secret put LIVE_SYMBOLS

Deploy:

    npm install
    npm run deploy

Use the generated workers.dev URL as the Quantix API endpoint.

## Limits

Cloudflare Workers Free currently has 100,000 Worker requests/day. Free Durable Objects use SQLite and have daily limits. Twelve Data Basic is free for personal/internal use and includes real-time US equities/ETFs, but its free WebSocket access is only trial access for a small symbol set. Therefore this backend does not claim unlimited free live streaming.

No market-data key is ever placed in web/.
