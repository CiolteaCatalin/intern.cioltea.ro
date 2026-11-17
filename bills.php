<?php
require __DIR__.'/includes/bootstrap.php'; require_login();
$message='';$error='';$month=$_GET['month']??date('Y-m');[,,$month]=month_bounds($month);
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();$action=$_POST['action']??'';
    try{
        if($action==='add'){
            $name=trim($_POST['name']??'');$category=trim($_POST['category']??'Facturi');$estimate=parse_amount_ro($_POST['estimated_amount']??'0');$due=(int)($_POST['due_day']??1);
            if($name===''||$estimate<0||$due<1||$due>31)throw new RuntimeException('Completează corect denumirea, estimarea și ziua scadenței.');
            $s=$pdo->prepare('INSERT INTO bills(name,category,estimated_amount,due_day,active,created_by_member_id) VALUES(?,?,?,?,1,?)');$s->execute([$name,$category?:'Facturi',$estimate,$due,current_member_id()]); log_activity($pdo,'bill_added','bill',(int)$pdo->lastInsertId(),$name); $message='Factura a fost adăugată.';
        }
        if($action==='pay'){
            $id=(int)($_POST['id']??0);$amount=parse_amount_ro($_POST['amount']??'0');$paidDate=parse_ro_date($_POST['paid_date']??'',date('Y-m-d'));
            if(!$paidDate||$amount<=0)throw new RuntimeException('Completează o sumă și o dată valide.');
            $q=$pdo->prepare('SELECT * FROM bills WHERE id=? AND active=1');$q->execute([$id]);$bill=$q->fetch();if(!$bill)throw new RuntimeException('Factura nu mai există.');
            $period=substr($paidDate,0,7);
            $check=$pdo->prepare('SELECT id FROM bill_payments WHERE bill_id=? AND period=?');$check->execute([$id,$period]);if($check->fetchColumn())throw new RuntimeException('Factura este deja marcată ca plătită pentru luna respectivă.');
            $pdo->beginTransaction();
            $s=$pdo->prepare('INSERT INTO transactions(type,name,amount,category,transaction_date,created_by_member_id) VALUES(?,?,?,?,?,?)');$s->execute(['expense',$bill['name'],$amount,$bill['category']?:'Facturi',$paidDate.' '.date('H:i:s'),current_member_id()]);$tx=(int)$pdo->lastInsertId();
            add_transaction_meta($pdo,$tx,'bill','fixed',$id);
            $s=$pdo->prepare('INSERT INTO bill_payments(bill_id,period,amount,paid_date,transaction_id) VALUES(?,?,?,?,?)');$s->execute([$id,$period,$amount,$paidDate,$tx]);
            $pdo->commit(); log_activity($pdo,'bill_paid','bill',$id,$bill['name'].' · '.money_ro($amount)); $message='Factura a fost marcată ca plătită și adăugată la cheltuieli.';
        }
        if($action==='toggle'){$id=(int)($_POST['id']??0);$s=$pdo->prepare('UPDATE bills SET active=IF(active=1,0,1) WHERE id=?');$s->execute([$id]);$message='Factura a fost actualizată.';}
        if($action==='delete'){$id=(int)($_POST['id']??0);$s=$pdo->prepare('DELETE FROM bills WHERE id=?');$s->execute([$id]);$message='Factura a fost ștearsă.';}
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();}
}
$daysInMonth=(int)date('t',strtotime($month.'-01'));
$q=$pdo->prepare("SELECT b.*,bp.id payment_id,bp.amount paid_amount,bp.paid_date FROM bills b LEFT JOIN bill_payments bp ON bp.bill_id=b.id AND bp.period=? ORDER BY b.active DESC,b.due_day,b.name");$q->execute([$month]);$bills=$q->fetchAll();
$paid=0;$pending=0;foreach($bills as $b){if(!$b['active'])continue;if($b['payment_id'])$paid+=(float)$b['paid_amount'];else$pending+=(float)$b['estimated_amount'];}
$pageTitle='Facturi';$active='money';require __DIR__.'/includes/layout_top.php';
?>
<?php if($message):?><div class="alert success"><?=e($message)?></div><?php endif;?><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<form class="month-switch" method="get"><input type="month" name="month" value="<?=e($month)?>"><button class="btn soft">Vezi luna</button></form>
<section class="mini-summary"><article><span>Plătite</span><strong class="pos"><?=money_ro($paid)?></strong></article><article><span>Estimate rămase</span><strong class="neg"><?=money_ro($pending)?></strong></article></section>
<section class="action-head"><div><span class="eyebrow">Costuri variabile lunar</span><h2>Facturile tale</h2></div><button class="btn primary compact" type="button" data-toggle-target="#billAddCard"><?=icon('plus')?> Adaugă</button></section>
<section class="settings-card" id="billAddCard" hidden><form method="post" class="form-stack"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="add">
<label>Denumire<div class="input-wrap"><?=icon('receipt')?><input name="name" required placeholder="Ex: Curent, Gaz, Internet"></div></label>
<div class="two-col"><label>Estimare<div class="input-wrap money-input"><?=icon('wallet')?><input name="estimated_amount" inputmode="decimal" placeholder="250"><span>lei</span></div></label><label>Scadență<div class="input-wrap"><?=icon('calendar')?><input type="number" name="due_day" min="1" max="31" value="15" required></div></label></div>
<label>Categorie<div class="input-wrap"><?=icon('tag')?><select name="category"><option>Facturi</option><option>Casă</option><option>Abonamente</option><option>Altele</option></select></div></label><button class="btn primary wide">Salvează factura</button></form></section>
<div class="bill-list">
<?php if(!$bills):?><div class="empty-card">Nu ai facturi salvate. Adaugă curent, gaz, internet, telefon sau întreținere.</div><?php endif;?>
<?php foreach($bills as $b):$due=min((int)$b['due_day'],$daysInMonth);$dueDate=$month.'-'.str_pad((string)$due,2,'0',STR_PAD_LEFT);$overdue=!$b['payment_id']&&$b['active']&&$month===date('Y-m')&&$dueDate<date('Y-m-d');?>
<article class="bill-card <?=$b['active']?'':'is-paused'?> <?=$overdue?'is-overdue':''?>">
<div class="bill-top"><div class="bill-icon"><?=icon('receipt')?></div><div class="bill-main"><div><strong><?=e($b['name'])?></strong><?php if($b['payment_id']):?><span class="status paid">Plătită</span><?php elseif($overdue):?><span class="status overdue">Scadentă</span><?php elseif(!$b['active']):?><span class="status">Pauză</span><?php endif;?></div><span><?=e($b['category']?:'Facturi')?> · scadență <?=sprintf('%02d',$due)?>.<?=substr($month,5,2)?></span></div><strong><?=money_ro($b['payment_id']?$b['paid_amount']:$b['estimated_amount'])?></strong></div>
<?php if($b['active']&&!$b['payment_id']):?><form method="post" class="bill-pay"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="pay"><input type="hidden" name="id" value="<?=$b['id']?>"><input name="amount" inputmode="decimal" value="<?=e(number_format((float)$b['estimated_amount'],2,',',''))?>" aria-label="Suma"><input class="ro-date" name="paid_date" inputmode="numeric" maxlength="10" value="<?=e($month===date('Y-m')?date('d.m.Y'):date('d.m.Y',strtotime($dueDate)))?>" aria-label="Data plății"><button class="btn primary compact">Marchează plătită</button></form><?php endif;?>
<div class="row-actions"><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$b['id']?>"><button class="text-btn"><?=$b['active']?'Pauză':'Activează'?></button></form><form method="post" onsubmit="return confirm('Ștergi această factură? Istoricul plăților ei va fi eliminat, tranzacțiile deja create rămân.');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$b['id']?>"><button class="text-btn danger">Șterge</button></form></div>
</article><?php endforeach;?></div>
<?php require __DIR__.'/includes/layout_bottom.php'; ?>
