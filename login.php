<?php
require __DIR__.'/includes/bootstrap.php';
if (is_logged_in()) {
    if (security_validate_session($pdo)) { header('Location: '.(current_member($pdo)?'/dashboard.php':'/profile.php')); exit; }
    security_destroy_local_session();
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
}
$error='';
$blocked=security_is_login_blocked($pdo);
$next=(string)($_GET['next']??$_POST['next']??'/dashboard.php');
if (!str_starts_with($next,'/') || str_starts_with($next,'//')) $next='/dashboard.php';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $blocked=security_is_login_blocked($pdo);
    if ($blocked) {
        security_event($pdo,'blocked_login_attempt',null,null,'Încercare de autentificare după blocare');
        $error='Accesul de pe acest dispozitiv sau această conexiune este blocat. Deblocarea se face din Setări → Securitate de pe un dispozitiv deja conectat.';
    } else {
        $honeypot=trim((string)($_POST['website']??''));
        if ($honeypot!=='') {
            $result=security_record_login_failure($pdo,'honeypot');
            $blocked=$result['blocked'];
            $error=$blocked?'Acces blocat după 5 încercări suspecte.':'Autentificarea nu a putut fi validată.';
        } else {
            $password=(string)($_POST['password']??'');
            if (password_verify($password, (string)$config['app']['password_hash'])) {
                security_clear_login_failures($pdo);
                session_regenerate_id(true);
                $_SESSION['csrf']=bin2hex(random_bytes(32));
                $_SESSION['budget_auth']=true;
                unset($_SESSION['family_member_id']);
                $deviceId=security_register_session($pdo,null);
                security_event($pdo,'login_success',null,$deviceId,'Autentificare reușită');
                header('Location: /profile.php?next='.urlencode($next)); exit;
            }
            $result=security_record_login_failure($pdo,'wrong_password');
            $blocked=$result['blocked'];
            if($blocked){
                $error='Parola a fost introdusă greșit de 5 ori. Accesul a fost blocat permanent până la deblocarea manuală din Setări → Securitate.';
            }else{
                $n=(int)$result['remaining'];
                $error='Parola nu este corectă. '.($n===1?'Mai ai o singură încercare.':'Mai ai '.$n.' încercări înainte de blocare.');
            }
        }
    }
}
?><!doctype html><html lang="ro"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#315CF5"><meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default"><meta name="apple-mobile-web-app-title" content="Cioltea Hub">
<meta name="robots" content="noindex,nofollow,noarchive,nosnippet"><link rel="manifest" href="/manifest.json?v=23.1.5"><link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png?v=23.1.5"><link rel="icon" type="image/svg+xml" href="/assets/icons/favicon.svg"><link rel="icon" href="/assets/icons/favicon-32.png">
<link rel="stylesheet" href="/assets/app.css?v=23.1.5"><title>Autentificare · Cioltea Hub</title></head>
<body class="login-page"><main class="login-shell"><div class="login-card">
<div class="app-mark"><?=icon('house')?></div><div><h1>Cioltea Hub</h1><p>Casa voastră, într-un singur loc. Acces privat pentru familie.</p></div>
<?php if($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?>
<?php if(!$blocked):?>
<form method="post" class="form-stack" autocomplete="on"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="next" value="<?=e($next)?>">
<div class="login-honeypot" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
<label>Parolă<input type="password" name="password" autocomplete="current-password" required autofocus placeholder="••••••••"></label>
<button class="btn primary wide" type="submit">Intră în aplicație</button></form>
<div class="login-note">După 5 parole greșite, dispozitivul/conexiunea este blocată până la deblocare manuală.</div>
<?php else:?><div class="login-note"><b>Acces blocat.</b> O sesiune deja autentificată poate elimina blocajul din Setări → Securitate.</div><?php endif;?>
</div></main></body></html>
