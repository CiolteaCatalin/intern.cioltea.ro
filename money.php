<?php
require __DIR__.'/includes/bootstrap.php';require_login();require_member($pdo);
$member=current_member($pdo);$notice='';$error='';
$month=$_GET['month']??budget_cycle_key();[,,$month]=budget_cycle_bounds($month);$cycleStart=budget_cycle_bounds($month)[0];$cycleEnd=budget_cycle_bounds($month)[1];$prevCycle=date('Y-m',strtotime($month.'-01 -1 month'));$nextCycle=date('Y-m',strtotime($month.'-01 +1 month'));
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
        if(($_POST['action']??'')==='add_savings'){
            $goalId=(int)($_POST['goal_id']??0);$amount=parse_amount_ro((string)($_POST['amount']??''));$date=parse_ro_date((string)($_POST['date']??''),date('Y-m-d'));
            if($goalId<1||$amount<=0||!$date)throw new RuntimeException('Alege obiectivul, suma și data.');
            $q=$pdo->prepare('SELECT id,name FROM savings_goals WHERE id=? AND active=1');$q->execute([$goalId]);$goal=$q->fetch();if(!$goal)throw new RuntimeException('Obiectivul de economii nu mai este activ.');
            $s=$pdo->prepare('INSERT INTO savings_contributions(goal_id,amount,contribution_date,created_by_member_id) VALUES(?,?,?,?)');$s->execute([$goalId,$amount,$date,current_member_id()]);
            log_activity($pdo,'savings_added','savings_goal',$goalId,$goal['name'].' · '.money_ro($amount));$notice='Economia a fost adăugată.';
        }
    }catch(Throwable $e){$error=$e->getMessage();}
}
$summary=family_budget_cycle_summary($pdo,$month);$membersBreakdown=family_budget_cycle_member_breakdown($pdo,$month);
$balance=(float)$summary['income']-(float)$summary['expense'];
$pending=pending_bills_total($pdo,$month)+pending_recurring_expense_total($pdo,$month);$scheduledTotal=scheduled_pending_total($pdo);$scheduledCount=scheduled_pending_count($pdo);$subsMonthly=0.0;
foreach($pdo->query("SELECT amount,billing_cycle FROM subscriptions WHERE active=1") as $r)$subsMonthly+=(float)$r['amount']/($r['billing_cycle']==='yearly'?12:1);
$goals=$pdo->query("SELECT id,name FROM savings_goals WHERE active=1 ORDER BY id DESC")->fetchAll();
$financeActivity=$pdo->query("SELECT a.*,m.name member_name,m.initial,m.avatar_color FROM activity_log a LEFT JOIN family_members m ON m.id=a.member_id WHERE a.action IN ('expense_added','income_added','savings_added','scheduled_added','scheduled_paid','bill_paid') ORDER BY a.id DESC LIMIT 8")->fetchAll();
$incomeKinds=['salary'=>0.0,'advance'=>0.0,'vouchers'=>0.0,'card'=>0.0,'rent'=>0.0,'other'=>0.0];$ik=$pdo->prepare("SELECT COALESCE(tm.income_kind,'other') kind,COALESCE(SUM(t.amount),0) total FROM transactions t LEFT JOIN transaction_meta tm ON tm.transaction_id=t.id WHERE t.type='income' AND t.transaction_date BETWEEN ? AND ? GROUP BY COALESCE(tm.income_kind,'other')");$ik->execute([$cycleStart,$cycleEnd]);foreach($ik as $r)$incomeKinds[$r['kind']]=(float)$r['total'];
$pageTitle='Buget';$active='money';require __DIR__.'/includes/layout_top.php';
?>
<?php if($notice):?><div class="alert success"><?=e($notice)?></div><?php endif;?><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>

<div class="budget-cycle-nav"><a class="icon-btn" href="?month=<?=e($prevCycle)?>" aria-label="Ciclul anterior"><?=icon('back')?></a><div><small>Ciclu bugetar</small><strong><?=e(budget_cycle_label($month))?></strong></div><a class="icon-btn" href="?month=<?=e($nextCycle)?>" aria-label="Ciclul următor"><?=icon('chevron')?></a></div>

<section class="monthly-family-card">
  <div class="monthly-family-card__top"><div><span>Bugetul familiei</span><h2><?=e(budget_cycle_label($month))?></h2><p>De la salariul din 14 până în 13 luna următoare. Include automat avansul și bonurile din 30 și alimentarea cardului din 1.</p></div><span class="wallet-chip"><?=icon('wallet')?></span></div>
  <div class="monthly-family-balance"><span>Rămas după cheltuieli</span><strong><?=money_ro($balance)?></strong></div>
  <div class="monthly-family-stats"><div><span>Încasări</span><b class="pos"><?=money_ro($summary['income'])?></b></div><div><span>Cheltuieli</span><b class="neg"><?=money_ro($summary['expense'])?></b></div><div><span>Economii</span><b><?=money_ro($summary['savings'])?></b></div></div><div class="income-kind-strip"><?php foreach([['salary','Salariu'],['advance','Avans'],['vouchers','Bonuri'],['card','Card']] as [$key,$label]):?><span><small><?=e($label)?></small><b><?=money_ro($incomeKinds[$key]??0)?></b></span><?php endforeach;?></div>
  <div class="family-money-split">
    <?php foreach($membersBreakdown as $mb):?><div class="family-money-member"><span class="family-money-avatar" style="--avatar:<?=e($mb['avatar_color'])?>"><?=e($mb['initial'])?></span><div><b><?=e($mb['name'])?></b><small>Cheltuieli <?=money_ro($mb['expense'])?> · Economii <?=money_ro($mb['savings'])?></small></div></div><?php endforeach;?>
  </div>
</section>

<div class="money-primary-actions money-primary-actions--three">
  <button class="btn expense" data-open-tx="expense"><?=icon('minus')?> <span>Cheltuială</span></button>
  <button class="btn income" data-open-tx="income"><?=icon('plus')?> <span>Încasare</span></button>
  <?php if($goals):?><button class="btn savings" type="button" data-open-sheet="budgetSavingsSheet"><?=icon('piggy')?> <span>Economii</span></button><?php else:?><a class="btn savings" href="/goals.php"><?=icon('piggy')?> <span>Economii</span></a><?php endif;?>
</div>

<div class="money-utility-actions"><a href="/scheduled.php" class="utility-action"><?=icon('calendar')?><span><b>Cheltuieli programate</b><small><?=$scheduledCount?> în așteptare<?php if($scheduledTotal>0):?> · <?=money_ro($scheduledTotal)?><?php endif;?></small></span></a><a href="/bank-import.php" class="utility-action subtle"><?=icon('upload')?><span><b>Import extras de cont</b><small>BT sau BCR · fără duplicate</small></span></a></div>

<section><div class="section-head"><div><h2>Instrumente</h2><p>Lucrurile pe care le folosești mai rar.</p></div></div><div class="hub-grid compact-hub-grid">
<a class="hub-card" href="/transactions.php"><span class="hub-icon"><?=icon('transactions')?></span><div><b>Tranzacții</b><small>Istoric, filtre și cine a adăugat</small></div><?=icon('chevron')?></a>
<a class="hub-card" href="/bills.php"><span class="hub-icon amber"><?=icon('receipt')?></span><div><b>Facturi</b><small><?=money_ro($pending)?> estimate/rămase</small></div><?=icon('chevron')?></a>
<a class="hub-card" href="/recurring.php"><span class="hub-icon purple"><?=icon('repeat')?></span><div><b>Recurențe</b><small>Salarii, rate și sume fixe</small></div><?=icon('chevron')?></a>
<a class="hub-card" href="/goals.php"><span class="hub-icon green"><?=icon('piggy')?></span><div><b>Economii</b><small>Obiective și bani puși deoparte</small></div><?=icon('chevron')?></a>
<a class="hub-card" href="/budgets.php"><span class="hub-icon"><?=icon('target')?></span><div><b>Bugete</b><small>Limite pe categorii</small></div><?=icon('chevron')?></a>
<a class="hub-card" href="/subscriptions.php"><span class="hub-icon coral"><?=icon('repeat')?></span><div><b>Abonamente</b><small>~<?=money_ro($subsMonthly)?> / lună</small></div><?=icon('chevron')?></a>
<a class="hub-card" href="/statistics.php"><span class="hub-icon"><?=icon('chart')?></span><div><b>Statistici</b><small>Fixe, variabile și evoluție</small></div><?=icon('chevron')?></a>
</div></section>

<section><div class="section-head"><div><h2>Activitate financiară</h2><p class="section-subtle">Vezi imediat cine a făcut fiecare modificare.</p></div></div><div class="activity-list compact-activity">
<?php if(!$financeActivity):?><div class="empty">Nu există activitate financiară încă.</div><?php endif;?>
<?php foreach($financeActivity as $a):?><article><span class="activity-icon"><?=icon(activity_icon_name($a))?></span><div><strong><?=e($a['label'])?></strong><span><?=e($a['member_name']?:'Sistem')?> · <?=e(tx_date_ro($a['created_at']))?></span></div></article><?php endforeach;?>
</div></section>

<?php if($goals):?><div class="modal" id="budgetSavingsSheet" aria-hidden="true"><div class="modal-backdrop" data-close-sheet></div><section class="sheet"><div class="sheet-handle"></div><div class="sheet-head"><div><span class="eyebrow">Economii</span><h2>Pune bani deoparte</h2></div><button class="icon-btn" data-close-sheet><?=icon('close')?></button></div><form method="post" class="form-stack"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="add_savings"><label>Obiectiv<select name="goal_id" required><?php foreach($goals as $g):?><option value="<?=$g['id']?>"><?=e($g['name'])?></option><?php endforeach;?></select></label><label>Sumă<div class="input-wrap money-input"><?=icon('wallet')?><input name="amount" inputmode="decimal" required placeholder="500,00"><span>lei</span></div></label><label>Data<input class="ro-date" name="date" inputmode="numeric" maxlength="10" value="<?=date('d.m.Y')?>" required></label><button class="btn primary wide">Adaugă la economii</button></form></section></div><?php endif;?>
<?php require __DIR__.'/includes/layout_bottom.php';?>
