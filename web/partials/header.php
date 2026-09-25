<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../lib/market.php';
$page=basename($_SERVER['PHP_SELF']);
$explore=in_array($page,['screener.php','stock.php','compare.php'],true);
$research=in_array($page,['analytics.php'],true);
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#0b0e14"><meta name="description" content="Quantix quantitative market research workspace"><meta name="color-scheme" content="dark light">
<title><?=e(ucfirst(str_replace('.php','',$page)))?> · Quantix</title>
<link rel="stylesheet" href="assets/css/neumorphism.css"><link rel="stylesheet" href="assets/css/responsive.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script><script src="assets/js/settings.js" defer></script>
</head><body>
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="topbar">
<a class="brand" href="index.php" aria-label="Quantix home"><b aria-hidden="true">Q</b><span>Quantix</span></a>
<form class="global-search" action="stock.php" role="search"><span aria-hidden="true">⌕</span><input name="symbol" placeholder="Search ticker or company" autocomplete="off" aria-label="Search ticker or company"><button class="primary" type="submit">Open</button></form>
<div class="top-actions"><span class="live-dot" aria-hidden="true"></span><span class="top-status">Research mode</span><?=user()?'<a href="logout.php">Sign out</a>':'<a href="login.php">Sign in</a>'?></div>
</header>
<main class="main" id="main-content"><div class="wrap">
<nav class="bottom-nav" aria-label="Primary navigation">
<a class="<?=$page==='index.php'?'active':''?>" href="index.php" <?=$page==='index.php'?'aria-current="page"':''?>><b aria-hidden="true">⌂</b><span>Home</span></a>
<a class="<?=$explore?'active':''?>" href="screener.php" <?=$explore?'aria-current="page"':''?>><b aria-hidden="true">⌕</b><span>Explore</span></a>
<a class="<?=$research?'active':''?>" href="analytics.php" <?=$research?'aria-current="page"':''?>><b aria-hidden="true">◈</b><span>Research</span></a>
<a class="<?=$page==='watchlist.php'?'active':''?>" href="watchlist.php" <?=$page==='watchlist.php'?'aria-current="page"':''?>><b aria-hidden="true">☆</b><span>Saved</span></a>
<a class="<?=$page==='settings.php'?'active':''?>" href="settings.php" <?=$page==='settings.php'?'aria-current="page"':''?>><b aria-hidden="true">⚙</b><span>Settings</span></a>
</nav>
