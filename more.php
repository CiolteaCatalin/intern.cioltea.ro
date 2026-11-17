<?php
require __DIR__.'/includes/bootstrap.php';require_login();
$vehicleCount=(int)$pdo->query("SELECT COUNT(*) FROM vehicles WHERE active=1")->fetchColumn();$carDue=(int)$pdo->query("SELECT COUNT(*) FROM vehicle_reminders WHERE completed=0 AND due_date IS NOT NULL AND due_date<=DATE_ADD(CURDATE(),INTERVAL 30 DAY)")->fetchColumn();$homeDue=(int)$pdo->query("SELECT COUNT(*) FROM home_reminders WHERE completed=0 AND due_date IS NOT NULL AND due_date<=DATE_ADD(CURDATE(),INTERVAL 30 DAY)")->fetchColumn();$wish=(int)$pdo->query("SELECT COUNT(*) FROM wishlist_items WHERE purchased=0")->fetchColumn();$recipeCount=(int)$pdo->query("SELECT COUNT(*) FROM family_recipes")->fetchColumn();$birthdayCount=(int)$pdo->query("SELECT COUNT(*) FROM family_birthdays")->fetchColumn();$rentalCount=(int)$pdo->query("SELECT COUNT(*) FROM rental_properties WHERE active=1")->fetchColumn();$eventCount=(int)$pdo->query("SELECT COUNT(*) FROM family_events WHERE YEAR(event_date)=YEAR(CURDATE()) AND event_date>=CURDATE()")->fetchColumn();$rentalPending=(int)$pdo->query("SELECT COUNT(*) FROM rental_payments WHERE status='pending' AND due_date<=DATE_ADD(CURDATE(),INTERVAL 31 DAY)")->fetchColumn();
$pageTitle='Mai multe';$active='more';require __DIR__.'/includes/layout_top.php';
?>
<section class="section-head"><div><span class="eyebrow">Family OS</span><h2>Tot ce mai administrați</h2></div></section><div class="hub-grid">
<a class="hub-card" href="/cars.php"><span class="hub-icon coral"><?=icon('car')?></span><div><b>Garaj</b><small><?=$vehicleCount?> mașini · <?=$carDue?> lucruri apropiate</small></div><?=icon('chevron')?></a>
<a class="hub-card" href="/home.php"><span class="hub-icon green"><?=icon('house')?></span><div><b>Casă</b><small><?=$homeDue?> mentenanțe apropiate</small></div><?=icon('chevron')?></a>
<a class="hub-card" href="/wishlist.php"><span class="hub-icon purple"><?=icon('gift')?></span><div><b>Wishlist</b><small><?=$wish?> lucruri planificate</small></div><?=icon('chevron')?></a>
<a class="hub-card" href="/recipes.php"><span class="hub-icon amber"><?=icon('food')?></span><div><b>Rețete</b><small><?=$recipeCount?> rețete salvate</small></div><?=icon('chevron')?></a>
<a class="hub-card" href="/birthdays.php"><span class="hub-icon coral"><?=icon('cake')?></span><div><b>Zile de naștere</b><small><?=$birthdayCount?> aniversări salvate</small></div><?=icon('chevron')?></a>
<a class="hub-card" href="/weddings.php"><span class="hub-icon purple"><?=icon('gift')?></span><div><b>Nunți & invitați</b><small>Invitații noștri · nunți de participat</small></div><?=icon('chevron')?></a>
<a class="hub-card" href="/rents.php"><span class="hub-icon green"><?=icon('building')?></span><div><b>Chirii</b><small><?=$rentalCount?> proprietăți · <?=$rentalPending?> chirii de urmărit</small></div><?=icon('chevron')?></a>
<a class="hub-card" href="/events.php"><span class="hub-icon blue"><?=icon('calendar')?></span><div><b>Evenimente</b><small><?=$eventCount?> rămase anul acesta</small></div><?=icon('chevron')?></a>
<a class="hub-card" href="/cloud.php"><span class="hub-icon blue"><?=icon('cloud')?></span><div><b>Poze</b><small>Digi Storage · albume și upload</small></div><?=icon('chevron')?></a>
<a class="hub-card" href="/notifications.php"><span class="hub-icon amber"><?=icon('bell')?></span><div><b>Notificări</b><small>Push pe iPhone și iPad</small></div><?=icon('chevron')?></a>
<a class="hub-card" href="/settings.php"><span class="hub-icon"><?=icon('settings')?></span><div><b>Setări</b><small>Buget, sold și aplicație</small></div><?=icon('chevron')?></a>
<a class="hub-card" href="/profile.php"><span class="hub-icon"><?=icon('user')?></span><div><b>Schimbă profilul</b><small>Cătălin / Laura</small></div><?=icon('chevron')?></a>
</div>
<section class="info-note"><span class="mini-icon"><?=icon('lock')?></span><div><b>Privat pentru familie</b><p>Aplicația rămâne neindexabilă și toate informațiile sunt vizibile doar după autentificare.</p></div></section>
<?php require __DIR__.'/includes/layout_bottom.php';?>
