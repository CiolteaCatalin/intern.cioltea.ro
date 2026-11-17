<?php
require __DIR__.'/includes/bootstrap.php';
require_login();
require_member($pdo);
$configured = notifications_configured($config);
$member = current_member($pdo);
$pageTitle = 'Notificări';
$active = 'more';
require __DIR__.'/includes/layout_top.php';
?>
<section class="push-hero notification-hero">
  <div class="push-icon"><?=icon('bell')?></div>
  <div>
    <h2>Alerte pe iPhone și iPad</h2>
    <p>Web Push direct din Cioltea Hub, fără OneSignal și fără abonament lunar.</p>
  </div>
</section>

<?php if(!$configured): ?>
<div class="alert warning"><b>Web Push nu poate porni încă.</b><br>Serverul are nevoie de OpenSSL, acces HTTP outbound și permisiune de scriere în <code>storage/</code>. Cheile VAPID se generează automat.</div>
<?php else: ?>
<section class="push-device-panel">
  <div class="push-device-head">
    <span class="mini-icon blue"><?=icon('bell')?></span>
    <div><strong>Acest dispozitiv</strong><span>Profil activ: <?=e($member['name'])?></span></div>
    <button class="btn primary small" id="enablePushBtn" type="button">Activează</button>
  </div>
  <div id="pushStatus" class="push-status">Verific starea notificărilor…</div>
  <div class="push-actions push-actions-grid">
    <button class="btn soft wide" id="testPushBtn" type="button"><?=icon('send')?> Test</button>
    <button class="btn ghost wide" id="disablePushBtn" type="button"><?=icon('bell-off')?> Dezactivează</button>
  </div>
</section>

<section class="push-install-card">
  <span class="mini-icon blue"><?=icon('phone')?></span>
  <div><b>Activare pe iPhone/iPad</b><p>Safari → Share → <b>Add to Home Screen</b>. Deschide Cioltea Hub din iconița de pe Home Screen și apasă <b>Activează</b>. Este necesar iOS/iPadOS 16.4+.</p></div>
</section>

<div class="section-head compact"><h2>Ce poate notifica</h2></div>
<section class="notify-grid">
  <article><span class="mini-icon amber"><?=icon('receipt')?></span><div><b>Facturi</b><span>Scadențe și restanțe</span></div></article>
  <article><span class="mini-icon coral"><?=icon('car')?></span><div><b>Mașini</b><span>RCA, ITP, rovinietă, revizii</span></div></article>
  <article><span class="mini-icon blue"><?=icon('calendar')?></span><div><b>Calendar</b><span>Programări și evenimente</span></div></article>
  <article><span class="mini-icon purple"><?=icon('clipboard')?></span><div><b>Task-uri</b><span>Lucruri atribuite și termene</span></div></article>
  <article><span class="mini-icon green"><?=icon('document')?></span><div><b>Documente</b><span>CI, permis, pașaport și altele</span></div></article>
  <article><span class="mini-icon green"><?=icon('house')?></span><div><b>Casă</b><span>Revizii, mentenanțe și asigurări</span></div></article>
  <article><span class="mini-icon coral"><?=icon('cake')?></span><div><b>Zile de naștere</b><span>Cu o zi înainte și la 07:00 în ziua aniversării</span></div></article>
  <article><span class="mini-icon green"><?=icon('building')?></span><div><b>Chirii</b><span>Scadențe chirie și lucrări planificate</span></div></article>
</section>

<section class="info-note push-free-note"><span class="mini-icon amber"><?=icon('bell')?></span><div><b>Serviciu gratuit</b><p>Notificările sunt trimise de serverul tău prin standardul Web Push. Nu există abonament separat; folosești doar hostingul existent.</p></div></section>
<?php endif; ?>
<?php require __DIR__.'/includes/layout_bottom.php'; ?>
