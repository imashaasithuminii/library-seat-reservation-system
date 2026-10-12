<?php require 'config.php';   // MODULE 1: Login / Register / Librarian sign-in
function ico($k){static $p=[
 'id'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6 16c.6-1.6 4.4-1.6 5 0M14 10h4M14 14h3"/>',
 'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
 'mail'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
 'lock'=>'<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/>',
 'eye'=>'<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/><path class="slash" d="M4 4l16 16"/>',
 'seat'=>'<path d="M7 11V6a3 3 0 013-3h4a3 3 0 013 3v5M5 11h14v5H5zM7 16v4M17 16v4"/>',
 'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
 'bell'=>'<path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0"/>'];
 return '<svg viewBox="0 0 24 24" aria-hidden="true">'.$p[$k].'</svg>';}

$err=$ok='';
$mode=$_POST['mode']??$_GET['mode']??'student';
if(!in_array($mode,['student','register','admin'],true))$mode='student';
$old=fn($k)=>e(trim((string)($_POST[$k]??'')));   // keeps what the person typed after an error

if($_SERVER['REQUEST_METHOD']=='POST'){
 $email=trim((string)($_POST['email']??''));$pw=(string)($_POST['password']??'');
 if($mode=='admin'){
  $s=adb()->prepare("SELECT * FROM admins WHERE email=?");$s->execute([$email]);$u=$s->fetch();
  if($u&&password_verify($pw,$u['password'])){session_regenerate_id(true);$_SESSION=['role'=>'admin','id'=>$u['id'],'name'=>$u['name']];header('Location: admin.php');exit;}
  $err='Incorrect admin email or password.';
 }elseif($mode=='register'){
  $no=trim((string)($_POST['no']??''));$name=trim((string)($_POST['name']??''));
  if($no===''||$name==='')$err='Please enter your student ID and full name.';
  elseif(!filter_var($email,FILTER_VALIDATE_EMAIL))$err='Please enter a valid email address.';
  elseif(strlen($pw)<6)$err='Password must be at least 6 characters.';
  elseif($pw!==(string)($_POST['password2']??''))$err='The two passwords do not match.';
  else{try{sdb()->prepare("INSERT INTO students(student_no,name,email,password) VALUES(?,?,?,?)")->execute([$no,$name,$email,password_hash($pw,PASSWORD_DEFAULT)]);$ok='Account created. Sign in to reserve a space.';$mode='student';}catch(Exception $x){$err='That student ID or email is already registered.';}}
 }else{
  $s=sdb()->prepare("SELECT * FROM students WHERE email=?");$s->execute([$email]);$u=$s->fetch();
  if($u&&password_verify($pw,$u['password'])){session_regenerate_id(true);$_SESSION=['role'=>'student','id'=>$u['id'],'name'=>$u['name']];header('Location: '.(($_GET['next']??'')=='reserve'?'reserve.php':'dashboard.php'));exit;}
  $err='Incorrect email or password.';
 }
}
$idx=array_search($mode,['student','register','admin']);
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in · Reading Room</title><link rel="stylesheet" href="assets/css/base.css"><link rel="stylesheet" href="assets/css/login.css"></head>
<body class="auth" data-m="<?=e($mode)?>">
<aside class="auth-art"><!-- 📷 IMAGE SLOT: put assets/img/login.jpg (portrait, ~1000x1400). Shown behind this panel. -->
<a class="brand" href="reserve.php"><img src="assets/img/logo.png" alt="" onerror="this.remove()">Reading Room</a>
<div class="art-mid"><h1>Your seat is waiting.</h1><p>Reserve silent desks, discussion rooms and presentation studios up to 7 days ahead.</p>
<ul class="perks"><li><span><?=ico('seat')?></span>Pick your exact seat on a seat map</li><li><span><?=ico('clock')?></span>Book up to 7 days ahead, 9:00 AM – 5:00 PM</li><li><span><?=ico('bell')?></span>Booking updates in your dashboard</li></ul></div>
<div class="tip" id="tip"></div></aside>

<main class="auth-form"><div class="card" id="card">
<div class="tabs" role="tablist" aria-label="Account type" style="--i:<?=(int)$idx?>"><i></i>
<button type="button" role="tab" data-m="student" class="<?=$mode=='student'?'on':''?>">Student</button><button type="button" role="tab" data-m="register" class="<?=$mode=='register'?'on':''?>">Create account</button><button type="button" role="tab" data-m="admin" class="<?=$mode=='admin'?'on':''?>">Librarian</button></div>
<div class="head"><h2 id="h2"></h2><p id="sub"></p></div>
<?php if($err):?><p class="msg bad srv" role="alert"><?=e($err)?></p><?php endif;if($ok):?><p class="msg good srv" role="status"><?=e($ok)?></p><?php endif;?>

<form method="post" id="f" novalidate><input type="hidden" name="mode" id="mode" value="<?=e($mode)?>">
<div class="fld only-register"><label for="no">Student ID</label><div class="inp"><?=ico('id')?><input id="no" name="no" autocomplete="off" placeholder="e.g. 2024/CS/015" value="<?=$old('no')?>"></div><small class="fm"></small></div>
<div class="fld only-register"><label for="name">Full name</label><div class="inp"><?=ico('user')?><input id="name" name="name" autocomplete="name" placeholder="Your full name" value="<?=$old('name')?>"></div><small class="fm"></small></div>
<div class="fld"><label for="email">Email</label><div class="inp"><?=ico('mail')?><input id="email" type="email" name="email" autocomplete="username" placeholder="you@example.edu" value="<?=$old('email')?>"></div><small class="fm"></small></div>
<div class="fld"><label for="password">Password</label><div class="inp pw"><?=ico('lock')?><input id="password" type="password" name="password" autocomplete="current-password" placeholder="At least 6 characters"><button type="button" class="eye" aria-label="Show password"><?=ico('eye')?></button></div><small class="caps" hidden>⇪ Caps Lock is on</small><small class="fm"></small></div>
<div class="strength only-register"><div class="meter" id="meter" data-s="0"><i></i><i></i><i></i><i></i></div><p class="sl" id="sl"></p><ul class="rules" id="rules"><li data-r="len">8+ characters</li><li data-r="case">Upper &amp; lower case</li><li data-r="num">A number</li><li data-r="sym">A symbol</li></ul></div>
<div class="fld only-register"><label for="password2">Confirm password</label><div class="inp pw"><?=ico('lock')?><input id="password2" type="password" name="password2" autocomplete="new-password" placeholder="Type it again"><button type="button" class="eye" aria-label="Show password"><?=ico('eye')?></button></div><small class="caps" hidden>⇪ Caps Lock is on</small><small class="fm"></small></div>
<label class="chk only-login"><input type="checkbox" id="rem"> Remember my email on this device</label>
<button class="btn full" id="go">Sign in</button>
<p class="hint only-admin">Demo librarian: admin@library.local / admin123 <button type="button" class="link" id="demo">Fill it in</button></p>
</form>
<p class="alt"><span class="a-student">New here? <button type="button" class="link" data-go="register">Create an account</button></span><span class="a-register">Already registered? <button type="button" class="link" data-go="student">Sign in</button></span><span class="a-admin">Not library staff? <button type="button" class="link" data-go="student">Student sign in</button></span><a href="reserve.php">Browse spaces →</a></p>
</div></main>
<script src="assets/js/login.js"></script>
<script>
// Student ID, Full name and Confirm password belong to "Create account" only.
// Student and Librarian sign-in show just Email + Password.
(function(){
 var m=document.getElementById('mode');
 function sync(){var reg=m&&m.value==='register';
  document.querySelectorAll('#f .only-register').forEach(function(el){el.style.display=reg?'':'none';});}
 ['click','keyup','change'].forEach(function(t){document.addEventListener(t,function(){setTimeout(sync,0);},true);});
 window.addEventListener('pageshow',sync);
 sync();
})();
</script></body></html>
