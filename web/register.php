<?php
require __DIR__.'/partials/header.php';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();try{$email=trim($_POST['email']??'');$pass=$_POST['password']??'';if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pass)<8)$error='Use a valid email and a password of at least 8 characters.';else{q('INSERT INTO users(email,password_hash) VALUES(?,?)',[$email,password_hash($pass,PASSWORD_DEFAULT)]);header('Location: login.php');exit;}}catch(Throwable $x){$error='Registration failed; the email may already exist or the database is not configured.';}}
?>
<div class="auth card"><span class="eyebrow">ACCOUNT</span><h1>Create account</h1><?php if($error):?><p class="error"><?=e($error)?></p><?php endif;?><form method="post"><?=csrf_input()?><label>Email<input type="email" name="email" autocomplete="email" required></label><label>Password<input type="password" name="password" minlength="8" autocomplete="new-password" required></label><button class="primary" type="submit">Register</button></form></div>
<?php require __DIR__.'/partials/footer.php'; ?>
