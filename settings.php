<?php
require __DIR__.'/includes/bootstrap.php';
require_login();
require_member($pdo);
$saved=false;$message='';$error='';
$member=current_member($pdo);
$themes=app_theme_palettes();

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action=$_POST['action']??'save_plan';
    try{
        if($action==='save_plan'){
            set_setting($pdo,'initial_balance',(string)parse_amount_ro($_POST['initial_balance']??'0'));
            set_setting($pdo,'monthly_budget',(string)parse_amount_ro($_POST['monthly_budget']??'0'));
            set_setting($pdo,'monthly_savings_target',(string)parse_amount_ro($_POST['monthly_savings_target']??'0'));
            $message='Setările de buget au fost salvate.';
        }
        if($action==='save_theme'){
            $key=(string)($_POST['theme_key']??'blue');
            if(!isset($themes[$key]))$key='blue';
            $accent=$themes[$key]['accent'];
            $s=$pdo->prepare('UPDATE family_members SET theme_key=?,avatar_color=? WHERE id=?');
            $s->execute([$key,$accent,current_member_id()]);
            log_activity($pdo,'theme_updated','settings',current_member_id(),'Temă aplicație · '.$themes[$key]['label']);
            $member=current_member($pdo);
            $message='Culoarea aplicației a fost actualizată pentru profilul tău.';
        }
        if($action==='revoke_device'){
            $id=(int)($_POST['device_id']??0);
            if($id<1)throw new RuntimeException('Dispozitiv invalid.');
            if($id===(int)($_SESSION['security_device_id']??0))throw new RuntimeException('Pentru sesiunea curentă folosește butonul Ieși din cont.');
            security_revoke_device($pdo,$id,(int)current_member_id());
            $message='Sesiunea dispozitivului a fost revocată.';
        }
        if($action==='revoke_others'){
            $count=security_revoke_other_devices($pdo,(int)current_member_id());
            $message=$count?'Au fost deconectate '.$count.' sesiuni.':'Nu există alte sesiuni active.';
        }
        if($action==='unlock_block'){
            $id=(int)($_POST['block_id']??0);
            if($id<1)throw new RuntimeException('Blocaj invalid.');
            $message=security_unlock_block($pdo,$id,(int)current_member_id())?'Blocajul a fost eliminat.':'Blocajul nu mai este activ.';
        }
    }catch(Throwable $e){$error=$e->getMessage();}
}
$initial=setting($pdo,'initial_balance','0');
$budget=setting($pdo,'monthly_budget','0');
$savingTarget=setting($pdo,'monthly_savings_target','0');
$currentTheme=(string)($member['theme_key']??'blue');
$devices=security_active_devices($pdo);
$blocks=security_blocked_sources($pdo);
$events=security_recent_events($pdo,30);
$currentDeviceId=(int)($_SESSION['security_device_id']??0);
$pageTitle='Setări';$active='more';require __DIR__.'/includes/layout_top.php';
?>
<?php if($message):?><div class="alert success"><?=e($message)?></div><?php endif;?>
<?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<section class="settings-card"><h2>Aspectul aplicației</h2><p>Alege culoarea Cioltea Hub pentru profilul <b><?=e($member['name'])?></b>. Laura și Cătălin pot avea teme diferite.</p><form method="post" class="form-stack"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_theme"><div class="theme-picker"><?php foreach($themes as $key=>$t):?><label class="theme-choice" style="--theme-accent:<?=e($t['accent'])?>;--theme-soft:<?=e($t['soft'])?>;--theme-surface:<?=e($t['surface'])?>;--theme-text:<?=e($t['text'])?>" data-mode="<?=e($t['mode'])?>"><input type="radio" name="theme_key" value="<?=e($key)?>" <?=$currentTheme===$key?'checked':''?>><span class="theme-choice__swatch"><i></i></span><b><?=e($t['label'])?></b></label><?php endforeach;?></div><button class="btn primary wide">Salvează culoarea</button></form></section>
<section class="settings-card"><h2>Plan lunar</h2><p>Valorile comune ale familiei pentru buget și economii.</p><form method="post" class="form-stack"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_plan"><label>Sold inițial<div class="money-input"><input name="initial_balance" inputmode="decimal" value="<?=e($initial)?>"><span>lei</span></div></label><label>Buget lunar de cheltuieli<div class="money-input"><input name="monthly_budget" inputmode="decimal" value="<?=e($budget)?>"><span>lei</span></div><small class="field-note">Cât vreți să permiteți cheltuielilor într-o lună.</small></label><label>Țintă de economisire / lună<div class="money-input"><input name="monthly_savings_target" inputmode="decimal" value="<?=e($savingTarget)?>"><span>lei</span></div></label><button class="btn primary wide">Salvează setările</button></form></section>
<section class="settings-card"><h2>Cioltea Hub</h2><p>Schimbă profilul folosit pe acest dispozitiv sau configurează notificările push.</p><div class="settings-links"><a class="btn soft wide" href="/profile.php"><?=icon('user')?> Schimbă profilul</a><a class="btn soft wide" href="/notifications.php"><?=icon('bell')?> Notificări</a></div></section>

<section class="settings-card security-panel"><div class="security-title-row"><div><h2>Securitate</h2><p>Sesiunile sunt verificate server-side. IP-ul este doar informativ și nu este folosit pentru whitelist.</p></div><span class="security-ok">Protejat</span></div>
<div class="security-summary"><div><span>Sesiuni active</span><strong><?=count($devices)?></strong></div><div><span>Blocaje active</span><strong><?=count($blocks)?></strong></div><div><span>Limită login</span><strong>5 încercări</strong></div></div>
<div class="section-head compact"><div><h2>Dispozitive conectate</h2></div><?php if(count($devices)>1):?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="revoke_others"><button class="btn ghost small" type="submit">Deconectează restul</button></form><?php endif;?></div>
<div class="security-device-list"><?php foreach($devices as $d):$current=(int)$d['id']===$currentDeviceId;?><article class="security-device <?=$current?'is-current':''?>"><span class="mini-icon"><?=icon('phone')?></span><div><div class="security-device-name"><b><?=e($d['device_label'])?></b><?php if($current):?><em>Acest dispozitiv</em><?php endif;?></div><small><?=e($d['member_name']?:'Profil neales')?> · <?=e($d['ip_address']?:'IP necunoscut')?></small><small>Activ: <?=e(tx_date_ro($d['last_seen']))?> · Prima conectare: <?=e(tx_date_ro($d['first_seen']))?></small></div><?php if(!$current):?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="revoke_device"><input type="hidden" name="device_id" value="<?=e($d['id'])?>"><button class="btn ghost small danger-outline" type="submit">Revocă</button></form><?php endif;?></article><?php endforeach;?></div>

<div class="section-head compact"><div><h2>Accesuri blocate</h2></div></div>
<?php if(!$blocks):?><div class="security-empty">Niciun dispozitiv sau conexiune blocată.</div><?php else:?><div class="security-block-list"><?php foreach($blocks as $b):?><article><div><b><?=e($b['block_type']==='network'?'Conexiune blocată':'Dispozitiv blocat')?></b><small><?=e($b['ip_address']?:'IP necunoscut')?> · <?=e($b['failures'])?> încercări · <?=e(tx_date_ro($b['blocked_at']))?></small></div><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="unlock_block"><input type="hidden" name="block_id" value="<?=e($b['id'])?>"><button class="btn primary small" type="submit">Deblochează</button></form></article><?php endforeach;?></div><?php endif;?>

<div class="section-head compact"><div><h2>Jurnal securitate</h2></div></div>
<div class="security-log"><?php foreach($events as $ev):?><article><span class="security-dot"></span><div><b><?=e(str_replace('_',' ',ucfirst($ev['event_type'])))?></b><small><?=e($ev['member_name']?:'Sistem')?> · <?=e($ev['device_label']?:'Dispozitiv')?> · <?=e(tx_date_ro($ev['created_at']))?></small><?php if($ev['detail']):?><small><?=e($ev['detail'])?></small><?php endif;?></div></article><?php endforeach;?></div>
<a class="btn danger-outline wide security-logout" href="/logout.php">Ieși din cont pe acest dispozitiv</a></section>

<section class="settings-card"><h2>Cache & performanță</h2><p>Preview-urile Digi Storage se păstrează local până la 180 de zile de la ultima utilizare. Sunt pregătite doar fotografiile lipsă sau modificate. Videoclipurile sunt ignorate. Dacă procesul se întrerupe, îl poți continua.</p>
<div class="cloud-cache-control" id="cloudCacheControl">
<div class="cloud-cache-buttons"><button class="btn primary" id="cloudCacheStart" type="button">Pregătește preview-urile</button><button class="btn soft" id="cloudCacheStop" type="button" disabled>Oprește</button></div>
<div class="cloud-cache-progress" id="cloudCacheProgress" hidden><div class="cloud-cache-progress__track"><span id="cloudCacheBar"></span></div><div class="cloud-cache-progress__meta"><strong id="cloudCachePercent">0%</strong><span id="cloudCacheNumbers">0 pregătite</span></div></div>
<p class="field-note" id="cloudCacheMessage" role="status" aria-live="polite">Procesarea folosește maximum 2 descărcări simultane. Poți relua procesul dacă pagina se închide; sunt salvate doar preview-uri, nu videoclipuri.</p>
</div><button class="btn soft wide" type="button" id="clearAppCacheBtn"><?=icon('trash')?> Șterge cache-ul aplicației</button><div class="push-status" id="cacheClearStatus" hidden></div></section>
<section class="settings-card"><h2>Date</h2><p>Poți descărca toate tranzacțiile într-un fișier CSV.</p><a class="btn soft wide" href="/export.php">Exportă CSV</a></section>
<?php require __DIR__.'/includes/layout_bottom.php';?>
