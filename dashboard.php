<?php
require __DIR__.'/includes/bootstrap.php';
require_login();
require_member($pdo);
$member=current_member($pdo);
$month=date('Y-m');
$saved=month_savings($pdo,$month);
$vehicleCount=(int)$pdo->query("SELECT COUNT(*) FROM vehicles WHERE active=1")->fetchColumn();
$cloudReady=digistorage_configured($config);
$homeDue=(int)$pdo->query("SELECT COUNT(*) FROM home_reminders WHERE completed=0 AND due_date IS NOT NULL AND due_date<=DATE_ADD(CURDATE(),INTERVAL 30 DAY)")->fetchColumn();
$wish=(int)$pdo->query("SELECT COUNT(*) FROM wishlist_items WHERE purchased=0")->fetchColumn();
$recipeCount=(int)$pdo->query("SELECT COUNT(*) FROM family_recipes")->fetchColumn();
$nextBirthday=next_birthday($pdo);
$rentalCount=(int)$pdo->query("SELECT COUNT(*) FROM rental_properties WHERE active=1")->fetchColumn();
$eventCount=(int)$pdo->query("SELECT COUNT(*) FROM family_events WHERE YEAR(event_date)=YEAR(CURDATE()) AND event_date>=CURDATE()")->fetchColumn();
$rentalPending=(int)$pdo->query("SELECT COUNT(*) FROM rental_payments WHERE status='pending' AND due_date<=DATE_ADD(CURDATE(),INTERVAL 31 DAY)")->fetchColumn();
$shopping=$pdo->query("SELECT l.id,l.name,SUM(i.checked=0) remaining FROM family_lists l LEFT JOIN family_list_items i ON i.list_id=l.id WHERE l.archived=0 AND l.name='Cumpărături' GROUP BY l.id ORDER BY l.id LIMIT 1")->fetch();
if(!$shopping)$shopping=$pdo->query("SELECT l.id,l.name,SUM(i.checked=0) remaining FROM family_lists l LEFT JOIN family_list_items i ON i.list_id=l.id WHERE l.archived=0 GROUP BY l.id ORDER BY l.id LIMIT 1")->fetch();
$shoppingId=(int)($shopping['id']??0);$shoppingRemaining=max(0,(int)($shopping['remaining']??0));
$upcoming=(int)$pdo->query("SELECT COUNT(*) FROM family_tasks WHERE completed=0 AND due_date IS NOT NULL AND due_date<=DATE_ADD(CURDATE(),INTERVAL 7 DAY)")->fetchColumn();
$weekly=weekly_family_summary($pdo);
$currentMonth=(int)date('n');
$currentYear=(int)date('Y');
$todayDay=(int)date('j');

$qBirthdays=$pdo->prepare("SELECT b.*,m.name creator FROM family_birthdays b LEFT JOIN family_members m ON m.id=b.created_by_member_id WHERE MONTH(b.birth_date)=? AND DAY(b.birth_date)>=? ORDER BY DAY(b.birth_date),b.name LIMIT 4");
$qBirthdays->execute([$currentMonth,$todayDay]);
$homeBirthdays=$qBirthdays->fetchAll();

$qEvents=$pdo->prepare("SELECT e.*,m.name member_name FROM family_events e LEFT JOIN family_members m ON m.id=e.member_id WHERE YEAR(e.event_date)=? AND MONTH(e.event_date)=? AND e.event_date>=CURDATE() ORDER BY e.event_date,e.event_time,e.id LIMIT 4");
$qEvents->execute([$currentYear,$currentMonth]);
$homeEvents=$qEvents->fetchAll();

$activity=$pdo->query("SELECT a.*,m.name member_name,m.initial,m.avatar_color FROM activity_log a LEFT JOIN family_members m ON m.id=a.member_id ORDER BY a.id DESC LIMIT 3")->fetchAll();
$pageTitle='Acasă';$active='dashboard';
require __DIR__.'/includes/layout_top.php';
?>
<section class="home-greeting">
  <div><h2>Bună, <?=e($member['name'])?> 👋</h2><p><?=e(date_ro(date('Y-m-d')))?> · tot ce contează pentru familie, într-un singur loc.</p></div>
</section>


<section class="weekly-family-card">
  <div class="weekly-family-head"><div><span>SĂPTĂMÂNA VOASTRĂ</span><h2><?=e(date_ro($weekly['start']))?> – <?=e(date_ro($weekly['end']))?></h2></div><?=icon('sparkles')?></div>
  <div class="weekly-family-grid">
    <div><small>Cheltuit</small><b><?=money_ro($weekly['expense'])?></b></div>
    <div><small>Economisit</small><b><?=money_ro($weekly['savings'])?></b></div>
    <div><small>Task-uri gata</small><b><?=$weekly['tasks_done']?></b></div>
    <div><small>Urmează</small><b><?=$weekly['upcoming']?></b></div>
  </div>
  <p><?php if($weekly['expense']>0):?>Ați cheltuit <?=money_ro($weekly['expense'])?> săptămâna aceasta.<?php else:?>Săptămâna este la început — aici veți vedea rezumatul vostru.<?php endif;?> <?php if($weekly['upcoming']):?>Aveți <?=$weekly['upcoming']?> lucruri de urmărit în următoarele 7 zile.<?php endif;?></p>
</section>

<section class="home-links-section home-primary-links">
  <div class="section-head compact"><div><h2>Acces rapid</h2></div></div>
  <div class="home-app-grid">
    <a class="home-app-card photos" href="/cloud.php"><span class="home-app-icon"><?=icon('image')?></span><div><b>Poze</b><small><?=$cloudReady?'Digi Storage conectat':'Configurează storage-ul'?></small></div></a>
    <a class="home-app-card cars" href="/cars.php"><span class="home-app-icon"><?=icon('car')?></span><div><b>Mașini</b><small><?=$vehicleCount?> în garaj</small></div></a>
    <a class="home-app-card house" href="/home.php"><span class="home-app-icon"><?=icon('house')?></span><div><b>Casă</b><small><?=$homeDue?> de urmărit</small></div></a>
    <a class="home-app-card list" href="<?=$shoppingId?'/lists.php?list='.$shoppingId:'/lists.php'?>"><span class="home-app-icon"><?=icon('cart')?></span><div><b>Cumpărături</b><small><?=$shoppingRemaining?> rămase</small></div></a>
    <a class="home-app-card wish" href="/wishlist.php"><span class="home-app-icon"><?=icon('gift')?></span><div><b>Lista dorințe</b><small><?=$wish?> active</small></div></a>
    <a class="home-app-card recipes" href="/recipes.php"><span class="home-app-icon"><?=icon('food')?></span><div><b>Rețete</b><small><?=$recipeCount?> salvate</small></div></a>
    <a class="home-app-card birthdays" href="/birthdays.php"><span class="home-app-icon"><?=icon('cake')?></span><div><b>Zile de naștere</b><small><?php if($nextBirthday):?><?=e($nextBirthday['row']['name'])?> · <?=e($nextBirthday['days']===0?'astăzi':($nextBirthday['days']===1?'mâine':'în '.$nextBirthday['days'].' zile'))?><?php else:?>Adaugă aniversări<?php endif;?></small></div></a>
    <a class="home-app-card rents" href="/rents.php"><span class="home-app-icon"><?=icon('building')?></span><div><b>Chirii</b><small><?=$rentalCount?($rentalPending.' de urmărit'):'Adaugă apartamentul'?></small></div></a>
    <a class="home-app-card events" href="/events.php"><span class="home-app-icon"><?=icon('calendar')?></span><div><b>Evenimente</b><small><?=$eventCount?> rămase anul acesta</small></div></a>
  </div>
</section>

<section class="home-overview-section">
  <div class="section-head compact"><div><h2>Pe scurt</h2></div></div>
  <div class="home-overview-grid">
    <a href="/goals.php" class="home-overview-card"><span class="mini-icon green"><?=icon('piggy')?></span><div><b><?=money_ro($saved)?></b><small>economisit în <?=e(month_label_ro($month))?></small></div></a>
    <a href="<?=$shoppingId?'/lists.php?list='.$shoppingId:'/lists.php'?>" class="home-overview-card"><span class="mini-icon blue"><?=icon('cart')?></span><div><b><?=$shoppingRemaining?></b><small>produse de cumpărat</small></div></a>
    <a href="/wishlist.php" class="home-overview-card"><span class="mini-icon purple"><?=icon('gift')?></span><div><b><?=$wish?></b><small>dorințe în listă</small></div></a>
    <a href="/family.php" class="home-overview-card"><span class="mini-icon amber"><?=icon('clipboard')?></span><div><b><?=$upcoming?></b><small>task-uri în 7 zile</small></div></a>
  </div>
</section>

<section class="home-monthly-section">
  <div class="section-head compact"><div><h2>Urmează luna aceasta</h2></div></div>
  <div class="home-monthly-grid">
    <article class="home-month-card">
      <div class="home-month-card__head"><div><span class="mini-icon birthdays"><?=icon('cake')?></span><div><b>Zile de naștere</b><small><?=e(month_label_ro(date('Y-m')))?></small></div></div><a href="/birthdays.php">Vezi toate</a></div>
      <div class="home-month-list">
        <?php if(!$homeBirthdays):?><div class="home-month-empty">Nicio aniversare care urmează luna aceasta.</div><?php endif;?>
        <?php foreach($homeBirthdays as $b):$day=(int)date('j',strtotime($b['birth_date']));?>
          <a class="home-month-row" href="/birthdays.php"><span class="home-month-date"><b><?=e(str_pad((string)$day,2,'0',STR_PAD_LEFT))?></b><small><?=e(strtoupper(substr(month_label_ro(date('Y-m')),0,3)))?></small></span><div><strong><?=e($b['name'])?></strong><span><?=e($b['relation_label']?:'Aniversare')?><?=$day===$todayDay?' · astăzi':''?></span></div></a>
        <?php endforeach;?>
      </div>
    </article>

    <article class="home-month-card">
      <div class="home-month-card__head"><div><span class="mini-icon events"><?=icon('calendar')?></span><div><b>Evenimente</b><small><?=e(month_label_ro(date('Y-m')))?></small></div></div><a href="/events.php">Vezi toate</a></div>
      <div class="home-month-list">
        <?php if(!$homeEvents):?><div class="home-month-empty">Niciun eveniment care urmează luna aceasta.</div><?php endif;?>
        <?php foreach($homeEvents as $ev):?>
          <a class="home-month-row" href="/events.php?year=<?=e(date('Y',strtotime($ev['event_date'])))?>"><span class="home-month-date"><b><?=e(date('d',strtotime($ev['event_date'])))?></b><small><?=e(strtoupper(substr(month_label_ro(date('Y-m',strtotime($ev['event_date']))),0,3)))?></small></span><div><strong><?=e($ev['title'])?></strong><span><?=e($ev['category'])?><?=$ev['event_time']?' · '.e(substr($ev['event_time'],0,5)):''?></span></div></a>
        <?php endforeach;?>
      </div>
    </article>
  </div>
</section>

<section class="home-activity-compact">
  <div class="section-head compact"><div><h2>Activitate recentă</h2></div></div>
  <div class="activity-strip">
    <?php if(!$activity):?><div class="empty">Activitatea voastră va apărea aici.</div><?php endif;?>
    <?php foreach($activity as $a):?><article><span class="activity-icon"><?=icon(activity_icon_name($a))?></span><div><strong><?=e($a['label'])?></strong><span><?=e($a['member_name']?:'Sistem')?> · <?=e(tx_date_ro($a['created_at']))?></span></div></article><?php endforeach;?>
  </div>
</section>
<?php require __DIR__.'/includes/layout_bottom.php';?>
