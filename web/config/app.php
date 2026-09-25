<?php
if(session_status()!==PHP_SESSION_ACTIVE) session_start();
function db(): PDO { static $pdo=null; if($pdo) return $pdo; $cfg=require __DIR__.'/database.php'; $pdo=new PDO('mysql:host='.$cfg['host'].';dbname='.$cfg['name'].';charset='.$cfg['charset'],$cfg['user'],$cfg['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); return $pdo; }
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function user(){return $_SESSION['user']??null;}
function require_login(){if(!user()){header('Location: login.php');exit;}}
function q($sql,$args=[]){$s=db()->prepare($sql);$s->execute($args);return $s;}
