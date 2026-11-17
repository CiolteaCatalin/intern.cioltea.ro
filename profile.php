<?php
require __DIR__.'/includes/bootstrap.php';
if(!is_logged_in() || !security_validate_session($pdo)){security_destroy_local_session();header('Location:/login.php');exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $id=(int)($_POST['member_id']??0);
    $s=$pdo->prepare('SELECT id,name FROM family_members WHERE id=? AND active=1');$s->execute([$id]);$m=$s->fetch();
    if($m){
        session_regenerate_id(true);
        $_SESSION['family_member_id']=(int)$m['id'];
        security_bind_member($pdo,(int)$m['id']);
        $next=(string)($_POST['next']??'/dashboard.php'); if(!str_starts_with($next,'/') || str_starts_with($next,'//'))$next='/dashboard.php';
        header('Location: '.$next);exit;
    }
    $error='Alege un profil valid.';
}
$members=family_members($pdo);$next=(string)($_GET['next']??'/dashboard.php');if(!str_starts_with($next,'/') || str_starts_with($next,'//'))$next='/dashboard.php';
?><!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#315CF5"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="robots" content="noindex,nofollow"><link rel="manifest" href="/manifest.json?v=23.1.5"><link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png?v=23.1.5"><link rel="stylesheet" href="/assets/app.css?v=23.1.5"><title>Alege profilul · Cioltea Hub</title></head><body class="login-page"><main class="login-shell"><div class="login-card profile-select-card"><div class="app-mark"><?=icon('users')?></div><div><h1>Cine folosește aplicația?</h1><p>Bugetul și informațiile sunt comune. Activitatea rămâne atribuită profilului ales.</p></div><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="next" value="<?=e($next)?>"><div class="profile-picker"><?php foreach($members as $m):?><button type="submit" name="member_id" value="<?=e($m['id'])?>" class="profile-choice"><span style="--avatar:<?=e($m['avatar_color'])?>"><?=e($m['initial'])?></span><b><?=e($m['name'])?></b><small>Continuă ca <?=e($m['name'])?></small></button><?php endforeach;?></div></form><a class="text-link center" href="/logout.php">Ieșire din cont</a></div></main></body></html>
