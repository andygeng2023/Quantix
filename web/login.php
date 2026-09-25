<?php
require __DIR__.'/partials/header.php';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();try{$email=trim($_POST['email']??'');$u=q('SELECT * FROM users WHERE email=?',[$email])->fetch();if($u&&password_verify($_POST['password']??'',$u['password_hash'])){session_regenerate_id(true);$_SESSION['user']=['id'=>$u['id'],'email'=>$u['email']];header('Location: index.php');exit;}$error='Invalid email or password.';}catch(Throwable $x){$error='Database is not configured.';}}
?>
<div class="auth card"><span class="eyebrow">ACCOUNT</span><h1>Sign in</h1><?php if($error):?><p class="error"><?=e($error)?></p><?php endif;?><form method="post"><?=csrf_input()?><label>Email<input type="email" name="email" autocomplete="email" required></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><button class="primary" type="submit">Sign in</button></form><p>No account? <a href="register.php">Register</a></p></div>
<?php require __DIR__.'/partials/footer.php'; ?>
