<?php require_once __DIR__.'/../config/app.php'; $page=basename($_SERVER['PHP_SELF']); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Quantix</title><link rel="stylesheet" href="assets/css/neumorphism.css"><link rel="stylesheet" href="assets/css/responsive.css"><script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script src="assets/js/settings.js"></script></head><body>
<aside class="sidebar"><a class="brand" href="index.php"><b>Q</b><span>Quantix</span></a><nav>
<a class="<?= $page==='index.php'?'active':'' ?>" href="index.php"><span>Home</span><small>Overview</small></a>
<a class="<?= in_array($page,['screener.php','stock.php','compare.php'])?'active':'' ?>" href="screener.php"><span>Explore</span><small>Stocks & compare</small></a>
<a class="<?= $page==='analytics.php'?'active':'' ?>" href="analytics.php"><span>Research</span><small>Models & validation</small></a>
<a class="<?= $page==='watchlist.php'?'active':'' ?>" href="watchlist.php"><span>Watchlist</span><small>Saved symbols</small></a>
<a class="<?= $page==='settings.php'?'active':'' ?>" href="settings.php"><span>Settings</span><small>Preferences</small></a>
</nav><div class="side-foot"><?=user()?e(user()['email']):'<a href="login.php">Sign in</a>'?></div></aside>
<main class="main"><header class="topbar"><form action="stock.php"><input name="symbol" placeholder="Search ticker or company" autocomplete="off" aria-label="Search ticker"><button class="primary">Search</button></form><div><?=user()?'<a href="logout.php">Sign out</a>':'<a href="login.php">Sign in</a>'?></div></header><div class="wrap">