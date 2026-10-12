<?php require 'config.php';need('student');   // MODULE 3: Student profile (view, edit, change password)
function ico($k){static $p=[
 'id'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6 16c.6-1.6 4.4-1.6 5 0M14 10h4M14 14h3"/>',
 'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
 'mail'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
 'phone'=>'<path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2"/>',
 'cap'=>'<path d="M2 9l10-5 10 5-10 5zM6 11.5V16c0 1.5 3 3 6 3s6-1.5 6-3v-4.5"/>',
 'lock'=>'<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/>',
 'eye'=>'<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/><path class="slash" d="M4 4l16 16"/>'];
 return '<svg viewBox="0 0 24 24" aria-hidden="true">'.$p[$k].'</svg>';}

$id=(int)$_SESSION['id'];$ok=$err='';$tab='details';
if(isset($_SESSION['flash'])){[$ok,$tab]=$_SESSION['flash'];unset($_SESSION['flash']);}   // message saved before the redirect
$a=$_POST['a']??'';
$in=['name'=>trim((string)($_POST['name']??'')),'email'=>trim((string)($_POST['email']??'')),'phone'=>trim((string)($_POST['phone']??'')),'programme'=>trim((string)($_POST['programme']??''))];

if($_SERVER['REQUEST_METHOD']=='POST'){
 if($a=='info'){$tab='details';
  if(mb_strlen($in['name'])<2)$err='Please enter your full name.';
  elseif(!filter_var($in['email'],FILTER_VALIDATE_EMAIL))$err='Please enter a valid email address.';
  elseif($in['phone']!==''&&!preg_match('/^[0-9+\-\s()]{7,20}$/',$in['phone']))$err='Phone number can only contain digits, spaces, + , - and brackets.';
  else{try{sdb()->prepare("UPDATE students SET name=?,email=?,phone=?,programme=? WHERE id=?")->execute([$in['name'],$in['email'],$in['phone'],$in['programme'],$id]);$_SESSION['name']=$in['name'];$_SESSION['flash']=['Profile updated.','details'];header('Location: profile.php#details');exit;}
   catch(Exception $x){$err='That email is already used by another account.';}}
 }
 if($a=='pw'){$tab='security';
  $s=sdb()->prepare("SELECT password FROM students WHERE id=?");$s->execute([$id]);$p=$s->fetch();
  $cur=(string)($_POST['cur']??'');$new=(string)($_POST['new']??'');$new2=(string)($_POST['new2']??'');
  if(!$p||!password_verify($cur,$p['password']))$err='Current password is incorrect.';
  elseif(strlen($new)<6)$err='New password must be at least 6 characters.';
  elseif($new!==$new2)$err='New passwords do not match.';
  else{sdb()->prepare("UPDATE students SET password=? WHERE id=?")->execute([password_hash($new,PASSWORD_DEFAULT),$id]);$_SESSION['flash']=['Password changed.','security'];header('Location: profile.php#security');exit;}
 }
}
$s=sdb()->prepare("SELECT * FROM students WHERE id=?");$s->execute([$id]);$u=$s->fetch();
$s=sdb()->prepare("SELECT COUNT(*) t,COALESCE(SUM(status='Confirmed'),0) k,COALESCE(SUM(status='Confirmed' AND booking_date>=CURDATE()),0) up,COALESCE(SUM(CASE WHEN status='Confirmed' THEN end_hour-start_hour END),0) h FROM bookings WHERE student_id=?");$s->execute([$id]);$c=$s->fetch();

$orig=['name'=>$u['name'],'email'=>$u['email'],'phone'=>(string)$u['phone'],'programme'=>(string)$u['programme']];   // what is saved in the database
$fv=($a=='info'&&$err)?$in:$orig;                       // what the form shows (keeps your typing after an error)
$editStart=($a=='info'&&$err);
$pct=(int)round(count(array_filter($orig,fn($x)=>trim($x)!==''))/4*100);
$since=date('j M Y',strtotime($u['created_at']));
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>My profile · Reading Room</title><link rel="stylesheet" href="assets/css/base.css"><link rel="stylesheet" href="assets/css/dashboard.css"><link rel="stylesheet" href="assets/css/profile.css"></head><body data-tab="<?=e($tab)?>">
<?php $active='profile';include 'nav.php';?>
<main class="dash">
<section class="pfh">
 <div class="ring" id="ring" style="--p:<?=$pct?>"><i id="av"><?=e(strtoupper(mb_substr($u['name'],0,1)))?></i><em id="pct"><?=$pct?>%</em></div>
 <div class="who2"><h1 id="pname"><?=e($u['name'])?></h1>
  <p class="meta"><span class="chip">Student ID <?=e($u['student_no'])?></span><span class="chip">Member since <?=e($since)?></span></p>
  <button type="button" class="link nudge" id="nudge"></button></div>
</section>

<section class="stats">
 <div class="tc mint"><small>Bookings made</small><b data-count="<?=(int)$c['t']?>"><?=(int)$c['t']?></b></div>
 <div class="tc sun"><small>Confirmed</small><b data-count="<?=(int)$c['k']?>"><?=(int)$c['k']?></b></div>
 <div class="tc lilac"><small>Upcoming</small><b data-count="<?=(int)$c['up']?>"><?=(int)$c['up']?></b></div>
 <div class="tc rose"><small>Hours reserved</small><b data-count="<?=(int)$c['h']?>"><?=(int)$c['h']?></b></div>
</section>

<div class="dtabs" role="tablist"><button type="button" role="tab" data-t="details" class="on">Personal details</button><button type="button" role="tab" data-t="security">Security</button></div>

<section class="tp pcard" id="details">
 <div class="ph"><h3>Personal details</h3><span class="dirty" id="dirty" hidden>Unsaved changes</span><button type="button" class="btn sm ghost" id="edit">✎ Edit details</button></div>
 <?php if($err&&$tab=='details'):?><p class="msg bad srv" role="alert"><?=e($err)?></p><?php endif;?>
 <form id="info" method="post" novalidate data-orig="<?=e(json_encode($orig))?>" data-edit="<?=$editStart?1:0?>"><input type="hidden" name="a" value="info">
  <div class="fgrid">
   <div class="fld"><label for="i-no">Student ID</label><div class="inp"><?=ico('id')?><input id="i-no" value="<?=e($u['student_no'])?>" readonly><span class="lk" title="Student ID can’t be changed"><?=ico('lock')?></span></div><small class="fm">Can’t be changed.</small></div>
   <div class="fld"><label for="i-name">Full name</label><div class="inp"><?=ico('user')?><input id="i-name" name="name" autocomplete="name" value="<?=e($fv['name'])?>" <?=$editStart?'':'readonly'?>></div><small class="fm"></small></div>
   <div class="fld"><label for="i-email">Email</label><div class="inp"><?=ico('mail')?><input id="i-email" type="email" name="email" autocomplete="email" value="<?=e($fv['email'])?>" <?=$editStart?'':'readonly'?>></div><small class="fm"></small></div>
   <div class="fld"><label for="i-phone">Phone</label><div class="inp"><?=ico('phone')?><input id="i-phone" type="tel" name="phone" autocomplete="tel" placeholder="07X XXX XXXX" value="<?=e($fv['phone'])?>" <?=$editStart?'':'readonly'?>></div><small class="fm"></small></div>
   <div class="fld wide"><label for="i-prog">Degree programme</label><div class="inp"><?=ico('cap')?><input id="i-prog" name="programme" list="progs" placeholder="e.g. BSc in Computer Science" value="<?=e($fv['programme'])?>" <?=$editStart?'':'readonly'?>></div><small class="fm"></small></div>
  </div>
  <datalist id="progs"><option value="BSc in Computer Science"><option value="BSc in Information Technology"><option value="BSc in Engineering"><option value="BSc in Business Management"><option value="BA in English"><option value="LLB"><option value="MBA"></datalist>
  <div class="acts"><button type="button" class="btn ghost" id="cancel" hidden>Cancel</button><button class="btn" id="save" hidden disabled>Save changes</button></div>
 </form>
</section>

<section class="tp pcard" id="security">
 <div class="ph"><h3>Change password</h3></div>
 <?php if($err&&$tab=='security'):?><p class="msg bad srv" role="alert"><?=e($err)?></p><?php endif;?>
 <form id="pwform" method="post" novalidate><input type="hidden" name="a" value="pw">
  <div class="fgrid one">
   <div class="fld"><label for="p-cur">Current password</label><div class="inp pw"><?=ico('lock')?><input id="p-cur" type="password" name="cur" autocomplete="current-password"><button type="button" class="eye" aria-label="Show password"><?=ico('eye')?></button></div><small class="fm"></small></div>
   <div class="fld"><label for="p-new">New password</label><div class="inp pw"><?=ico('lock')?><input id="p-new" type="password" name="new" autocomplete="new-password"><button type="button" class="eye" aria-label="Show password"><?=ico('eye')?></button></div><small class="caps" hidden>⇪ Caps Lock is on</small><small class="fm"></small></div>
   <div class="strength"><div class="meter" id="meter" data-s="0"><i></i><i></i><i></i><i></i></div><p class="sl" id="sl"></p><ul class="rules" id="rules"><li data-r="len">8+ characters</li><li data-r="case">Upper &amp; lower case</li><li data-r="num">A number</li><li data-r="sym">A symbol</li></ul></div>
   <div class="fld"><label for="p-new2">Confirm new password</label><div class="inp pw"><?=ico('lock')?><input id="p-new2" type="password" name="new2" autocomplete="new-password"><button type="button" class="eye" aria-label="Show password"><?=ico('eye')?></button></div><small class="fm"></small></div>
  </div>
  <div class="acts"><button type="button" class="btn ghost" id="gen">✦ Suggest a strong password</button><button class="btn" id="pwgo" disabled>Update password</button></div>
 </form>
</section>
</main>
<div id="ptoast" class="ptoast" role="status" aria-live="polite" data-ok="<?=e($ok)?>"></div>
<?php include 'footer.php';?><script src="assets/js/profile.js"></script></body></html>
