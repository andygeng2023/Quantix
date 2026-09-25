<?php
if(session_status()!==PHP_SESSION_ACTIVE){
    $secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
    session_set_cookie_params(['httponly'=>true,'secure'=>$secure,'samesite'=>'Lax','path'=>'/']);
    session_start();
}
function db():PDO{
    static $pdo=null;
    if($pdo)return $pdo;
    $path=__DIR__.'/database.php';
    if(!is_file($path))throw new RuntimeException('Database configuration is missing.');
    $cfg=require $path;
    $pdo=new PDO('mysql:host='.$cfg['host'].';dbname='.$cfg['name'].';charset='.($cfg['charset']??'utf8mb4'),$cfg['user'],$cfg['pass'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    return $pdo;
}
function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function user(){return $_SESSION['user']??null;}
function require_login():void{if(!user()){header('Location: login.php');exit;}}
function q($sql,$args=[]){$s=db()->prepare($sql);$s->execute($args);return $s;}
function csrf_token():string{if(empty($_SESSION['csrf_token']))$_SESSION['csrf_token']=bin2hex(random_bytes(32));return $_SESSION['csrf_token'];}
function csrf_input():string{return '<input type="hidden" name="csrf_token" value="'.e(csrf_token()).'">';}
function verify_csrf():void{$token=(string)($_POST['csrf_token']??'');if(!hash_equals((string)($_SESSION['csrf_token']??''),$token)){http_response_code(419);exit('Invalid form token. Please reload and try again.');}}
