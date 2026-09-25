# Quantix

Quantix is a PHP/MySQL quantitative market analytics dashboard designed for InfinityFree-compatible hosting. The free InfinityFree plan cannot expose MySQL to an external Python process, so the Python collector writes cache payloads to the repository's \`data/ingest\` folder; PHP imports those payloads into MySQL when the site is visited. This keeps the website compatible with the free hosting model while retaining MySQL caching.

## Stack
- PHP 8.3 + MySQL/MariaDB
- HTML/CSS/JavaScript
- Chart.js for charts
- Python + yfinance collector
- PHP sessions for authentication

## Setup
1. Import \`sql/schema.sql\` with phpMyAdmin.
2. Copy \`web/config/database.example.php\` to \`web/config/database.php\` and enter the InfinityFree database values.
3. Upload the \`web/\` directory contents to the website root.
4. Run the Python collector elsewhere on a schedule; it creates JSON batches under \`data/ingest/\`.
5. Upload the generated JSON files with the website deployment, or run \`php web/api/import.php\` from an allowed PHP execution path.

The collector never stores credentials in the repository. yfinance data access is for personal/research use; it is not a guaranteed sub-second market feed.
