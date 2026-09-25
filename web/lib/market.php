<?php
function quantix_cache(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $path = __DIR__ . '/../data/market.json';
    $raw = @file_get_contents($path);
    $cache = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
    $cache['stocks'] = is_array($cache['stocks'] ?? null) ? $cache['stocks'] : [];
    $cache['prices'] = is_array($cache['prices'] ?? null) ? $cache['prices'] : [];
    $cache['analysis'] = is_array($cache['analysis'] ?? null) ? $cache['analysis'] : [];
    return $cache;
}
function quantix_stocks(): array { return quantix_cache()['stocks']; }
function quantix_analysis(): array { return quantix_cache()['analysis']; }
function quantix_prices(): array { return quantix_cache()['prices']; }
function quantix_generated_at(): ?string {
    $v = quantix_cache()['generated_at'] ?? null;
    return is_string($v) && $v !== '' ? $v : null;
}
function quantix_age_minutes(): ?float {
    $g = quantix_generated_at();
    if (!$g) return null;
    $t = strtotime($g);
    return $t ? max(0, (time() - $t) / 60) : null;
}
function quantix_status(): array {
    $age = quantix_age_minutes();
    if ($age === null) return ['label'=>'No cache','tone'=>'bad','age'=>null];
    if ($age <= 30) return ['label'=>'Fresh','tone'=>'good','age'=>$age];
    if ($age <= 120) return ['label'=>'Aging','tone'=>'warn','age'=>$age];
    return ['label'=>'Stale','tone'=>'bad','age'=>$age];
}
function quantix_stock_map(): array {
    static $map = null;
    if ($map !== null) return $map;
    $map = [];
    foreach (quantix_stocks() as $row) {
        if (!empty($row['symbol'])) $map[$row['symbol']] = $row;
    }
    return $map;
}
function quantix_price_map(): array {
    static $map = null;
    if ($map !== null) return $map;
    $map = [];
    foreach (quantix_prices() as $row) {
        $s = $row['symbol'] ?? '';
        if ($s === '') continue;
        if (!isset($map[$s]) || ($row['ts'] ?? '') > ($map[$s]['ts'] ?? '')) $map[$s] = $row;
    }
    return $map;
}
function quantix_money($v): string {
    if (!is_numeric($v)) return '—';
    $n = (float)$v;
    if (abs($n) >= 1e12) return '$'.number_format($n/1e12,1).'T';
    if (abs($n) >= 1e9) return '$'.number_format($n/1e9,1).'B';
    if (abs($n) >= 1e6) return '$'.number_format($n/1e6,1).'M';
    return '$'.number_format($n,2);
}
function quantix_pct($v, int $digits=1): string { return is_numeric($v) ? number_format((float)$v*100,$digits).'%' : '—'; }
function quantix_num($v, int $digits=2): string { return is_numeric($v) ? number_format((float)$v,$digits) : '—'; }
function quantix_change_class($v): string { return is_numeric($v) ? ((float)$v > 0 ? 'positive' : ((float)$v < 0 ? 'negative' : 'neutral')) : 'neutral'; }
