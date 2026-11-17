<?php
require __DIR__.'/includes/bootstrap.php'; require_login();
$month=date('Y-m');
$recurringCount=(int)$pdo->query("SELECT COUNT(*) FROM recurring_items WHERE active=1")->fetchColumn();
$billCount=(int)$pdo->query("SELECT COUNT(*) FROM bills WHERE active=1")->fetchColumn();
$goalCount=(int)$pdo->query("SELECT COUNT(*) FROM savings_goals WHERE active=1")->fetchColumn();
$pendingBills=pending_bills_total($pdo,$month);
$saved=month_savings($pdo,$month);
$pageTitle='Plan';$active='money';require __DIR__.'/includes/layout_top.php';
?>
<section class="plan-hero">
  <span class="eyebrow">Automatizează ce se repetă</span>
  <h2>Mai puține completări, mai mult control</h2>
  <p>Ține aici veniturile fixe, facturile, economiile și limitele pe categorii. Dashboard-ul folosește totul automat.</p>
</section>
<div class="plan-grid">
  <a class="plan-card" href="/recurring.php"><span class="plan-icon blue"><?=icon('repeat')?></span><div><strong>Recurențe</strong><span><?=$recurringCount?> active · salariu, avans, rate, abonamente</span></div><?=icon('chevron')?></a>
  <a class="plan-card" href="/bills.php"><span class="plan-icon amber"><?=icon('receipt')?></span><div><strong>Facturi</strong><span><?=$billCount?> active · <?=money_ro($pendingBills)?> estimate rămase</span></div><?=icon('chevron')?></a>
  <a class="plan-card" href="/goals.php"><span class="plan-icon green"><?=icon('piggy')?></span><div><strong>Economii</strong><span><?=$goalCount?> obiective · <?=money_ro($saved)?> puse deoparte luna asta</span></div><?=icon('chevron')?></a>
  <a class="plan-card" href="/budgets.php"><span class="plan-icon purple"><?=icon('target')?></span><div><strong>Bugete pe categorii</strong><span>Limite pentru mâncare, transport, shopping și altele</span></div><?=icon('chevron')?></a>
</div>
<section class="tip-card"><div class="tip-icon"><?=icon('sparkles')?></div><div><strong>QOL</strong><p>Cheltuielile obișnuite le poți adăuga direct din shortcut-urile de pe Acasă. Facturile și recurențele nu mai trebuie introduse manual în fiecare lună.</p></div></section>
<?php require __DIR__.'/includes/layout_bottom.php'; ?>
