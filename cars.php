<?php
require __DIR__.'/includes/bootstrap.php';
require_login();
$memberId=current_member_id();

function vehicle_status(?string $date): array {
    if(!$date) return ['missing','Fără dată',null];
    $days=days_until($date);
    if($days===null) return ['missing','Fără dată',null];
    if($days<0) return ['expired','Expirată de '.abs($days).' zile',$days];
    if($days===0) return ['warning','Expiră azi',0];
    if($days<=30) return ['warning',$days.' zile rămase',$days];
    return ['ok','Valabilă · '.$days.' zile',$days];
}
function vehicle_icon_name(string $type): string { return match($type){'motorcycle'=>'motorcycle','atv'=>'atv',default=>'car'}; }
function reminder_icon_name(string $kind): string {
    return in_array($kind,['RCA','CASCO','ITP','Rovinietă','Impozit'],true)?'document':'wrench';
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action=$_POST['action']??'';
    $error='';
    try{
        if($action==='add_vehicle'){
            $name=trim($_POST['name']??'');
            if($name==='') throw new RuntimeException('Completează denumirea mașinii.');
            $color=trim($_POST['color']??'#315CF5');
            if(!preg_match('/^#[0-9A-Fa-f]{6}$/',$color)) $color='#315CF5';
            $s=$pdo->prepare('INSERT INTO vehicles(name,color,vehicle_type,plate,vin,current_km,notes,created_by_member_id) VALUES(?,?,?,?,?,?,?,?)');
            $type=in_array(($_POST['vehicle_type']??'car'),['car','atv','motorcycle'],true)?$_POST['vehicle_type']:'car';$s->execute([$name,$color,$type,trim($_POST['plate']??'')?:null,strtoupper(trim($_POST['vin']??''))?:null,(int)($_POST['current_km']??0)?:null,trim($_POST['notes']??'')?:null,$memberId]);
            $id=(int)$pdo->lastInsertId();log_activity($pdo,'vehicle_added','vehicle',$id,$name);
            header('Location:/cars.php?id='.$id);exit;
        }elseif($action==='update_vehicle'){
            $id=(int)($_POST['id']??0);
            $name=trim($_POST['name']??'');if(!$id||$name==='')throw new RuntimeException('Date incomplete.');
            $color=trim($_POST['color']??'#315CF5');if(!preg_match('/^#[0-9A-Fa-f]{6}$/',$color))$color='#315CF5';
            $type=in_array(($_POST['vehicle_type']??'car'),['car','atv','motorcycle'],true)?$_POST['vehicle_type']:'car';$pdo->prepare('UPDATE vehicles SET name=?,color=?,vehicle_type=?,plate=?,vin=?,current_km=?,notes=? WHERE id=?')->execute([$name,$color,$type,trim($_POST['plate']??'')?:null,strtoupper(trim($_POST['vin']??''))?:null,max(0,(int)($_POST['current_km']??0))?:null,trim($_POST['notes']??'')?:null,$id]);
            log_activity($pdo,'vehicle_updated','vehicle',$id,$name);
            header('Location:/cars.php?id='.$id);exit;
        }elseif($action==='update_km'){
            $id=(int)($_POST['id']??0);$km=max(0,(int)($_POST['current_km']??0));
            $pdo->prepare('UPDATE vehicles SET current_km=? WHERE id=?')->execute([$km?:null,$id]);
            header('Location:/cars.php?id='.$id);exit;
        }elseif($action==='add_reminder'){
            $vehicle=(int)($_POST['vehicle_id']??0);$title=trim($_POST['title']??'');
            if(!$vehicle||$title==='')throw new RuntimeException('Completează mașina și denumirea.');
            $date=parse_ro_date($_POST['due_date']??'');$km=max(0,(int)($_POST['due_km']??0))?:null;
            $s=$pdo->prepare('INSERT INTO vehicle_reminders(vehicle_id,kind,title,due_date,due_km,remind_days,last_cost,notes,created_by_member_id) VALUES(?,?,?,?,?,?,?,?,?)');
            $s->execute([$vehicle,trim($_POST['kind']??'Altele')?:'Altele',$title,$date,$km,max(0,(int)($_POST['remind_days']??14)),parse_amount_ro($_POST['last_cost']??'')?:null,trim($_POST['notes']??'')?:null,$memberId]);
            $id=(int)$pdo->lastInsertId();log_activity($pdo,'vehicle_reminder_added','vehicle_reminder',$id,$title);
            header('Location:/cars.php?id='.$vehicle);exit;
        }elseif($action==='update_reminder'){
            $id=(int)($_POST['id']??0);$vehicle=(int)($_POST['vehicle_id']??0);$title=trim($_POST['title']??'');
            if(!$id||!$vehicle||$title==='')throw new RuntimeException('Completează denumirea termenului.');
            $date=parse_ro_date($_POST['due_date']??'');$km=max(0,(int)($_POST['due_km']??0))?:null;
            $s=$pdo->prepare('UPDATE vehicle_reminders SET kind=?,title=?,due_date=?,due_km=?,remind_days=?,last_cost=?,notes=? WHERE id=? AND vehicle_id=?');
            $s->execute([trim($_POST['kind']??'Altele')?:'Altele',$title,$date,$km,max(0,(int)($_POST['remind_days']??14)),parse_amount_ro($_POST['last_cost']??'')?:null,trim($_POST['notes']??'')?:null,$id,$vehicle]);
            log_activity($pdo,'vehicle_reminder_updated','vehicle_reminder',$id,$title);
            header('Location:/cars.php?id='.$vehicle);exit;
        }elseif($action==='delete_reminder'){
            $id=(int)($_POST['id']??0);$vehicle=(int)($_POST['vehicle_id']??0);
            if($id&&$vehicle){$pdo->prepare('DELETE FROM vehicle_reminders WHERE id=? AND vehicle_id=?')->execute([$id,$vehicle]);}
            header('Location:/cars.php?id='.$vehicle);exit;
        }elseif($action==='complete_reminder'){
            $id=(int)($_POST['id']??0);$vehicle=(int)($_POST['vehicle_id']??0);
            $s=$pdo->prepare('SELECT vr.title,vr.last_cost,v.name vehicle_name FROM vehicle_reminders vr JOIN vehicles v ON v.id=vr.vehicle_id WHERE vr.id=?');$s->execute([$id]);$r=$s->fetch();
            if($r){$pdo->prepare('UPDATE vehicle_reminders SET completed=1,completed_at=NOW() WHERE id=?')->execute([$id]);log_activity($pdo,'vehicle_reminder_done','vehicle_reminder',$id,$r['title'].' · '.$r['vehicle_name']);}
            header('Location:/cars.php'.($vehicle?'?id='.$vehicle:''));exit;
        }elseif($action==='add_cost'){
            $vehicle=(int)($_POST['vehicle_id']??0);$name=trim($_POST['name']??'');$category=trim($_POST['category']??'Altele')?:'Altele';$amount=parse_amount_ro($_POST['amount']??'0');$date=parse_ro_date($_POST['cost_date']??'',date('Y-m-d'));$odometer=max(0,(int)($_POST['odometer']??0))?:null;$notes=trim($_POST['notes']??'')?:null;$addBudget=!empty($_POST['add_to_budget']);
            if(!$vehicle||$name===''||$amount<=0||!$date)throw new RuntimeException('Completează denumirea, suma și data costului.');$tx=null;
            if($addBudget){$pdo->prepare('INSERT INTO transactions(type,name,amount,category,transaction_date,created_by_member_id) VALUES(?,?,?,?,?,?)')->execute(['expense',$name,$amount,'Transport',$date.' '.date('H:i:s'),$memberId]);$tx=(int)$pdo->lastInsertId();add_transaction_meta($pdo,$tx,'vehicle','variable',$vehicle);}
            $pdo->prepare('INSERT INTO vehicle_costs(vehicle_id,category,name,amount,cost_date,odometer,notes,transaction_id,created_by_member_id) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$vehicle,$category,$name,$amount,$date,$odometer,$notes,$tx,$memberId]);
            log_activity($pdo,'vehicle_cost_added','vehicle',$vehicle,$name.' · '.money_ro($amount));header('Location:/cars.php?id='.$vehicle);exit;
        }elseif($action==='delete_cost'){
            $cid=(int)($_POST['id']??0);$vehicle=(int)($_POST['vehicle_id']??0);$q=$pdo->prepare('SELECT transaction_id FROM vehicle_costs WHERE id=? AND vehicle_id=?');$q->execute([$cid,$vehicle]);$tx=$q->fetchColumn();$pdo->prepare('DELETE FROM vehicle_costs WHERE id=? AND vehicle_id=?')->execute([$cid,$vehicle]);if($tx)$pdo->prepare('DELETE FROM transactions WHERE id=?')->execute([$tx]);header('Location:/cars.php?id='.$vehicle);exit;
        }elseif($action==='delete_vehicle'){
            $id=(int)($_POST['id']??0);$pdo->prepare('DELETE FROM vehicles WHERE id=?')->execute([$id]);
            header('Location:/cars.php');exit;
        }
    }catch(Throwable $e){$error=$e->getMessage();}
}

$selectedId=max(0,(int)($_GET['id']??0));
$vehicles=$pdo->query('SELECT * FROM vehicles WHERE active=1 ORDER BY id DESC')->fetchAll();

// Active status rows for every car, used by the garage overview.
$statusRows=$pdo->query("SELECT vr.* FROM vehicle_reminders vr JOIN vehicles v ON v.id=vr.vehicle_id WHERE vr.completed=0 AND v.active=1 AND vr.kind IN ('Rovinietă','RCA','ITP') ORDER BY vr.due_date IS NULL,vr.due_date,vr.id DESC")->fetchAll();
$statuses=[];
foreach($statusRows as $row){ if(!isset($statuses[$row['vehicle_id']][$row['kind']])) $statuses[$row['vehicle_id']][$row['kind']]=$row; }

$vehicle=null;$reminders=[];$history=[];$vehicleCosts=[];$vehicleCostTotals=[];
if($selectedId){
    $s=$pdo->prepare('SELECT * FROM vehicles WHERE id=? AND active=1');$s->execute([$selectedId]);$vehicle=$s->fetch();
    if(!$vehicle){header('Location:/cars.php');exit;}
    $s=$pdo->prepare('SELECT * FROM vehicle_reminders WHERE vehicle_id=? AND completed=0 ORDER BY due_date IS NULL,due_date,due_km IS NULL,due_km,id');$s->execute([$selectedId]);$reminders=$s->fetchAll();
    $s=$pdo->prepare('SELECT * FROM vehicle_reminders WHERE vehicle_id=? AND completed=1 ORDER BY completed_at DESC,id DESC LIMIT 12');$s->execute([$selectedId]);$history=$s->fetchAll();
    $s=$pdo->prepare('SELECT vc.*,m.name creator FROM vehicle_costs vc LEFT JOIN family_members m ON m.id=vc.created_by_member_id WHERE vc.vehicle_id=? ORDER BY vc.cost_date DESC,vc.id DESC LIMIT 30');$s->execute([$selectedId]);$vehicleCosts=$s->fetchAll();
    $s=$pdo->prepare('SELECT category,COALESCE(SUM(amount),0) total FROM vehicle_costs WHERE vehicle_id=? AND cost_date>=DATE_SUB(CURDATE(),INTERVAL 12 MONTH) GROUP BY category ORDER BY total DESC');$s->execute([$selectedId]);foreach($s as $cr)$vehicleCostTotals[$cr['category']]=(float)$cr['total'];
}

$pageTitle='Garaj';$active='more';require __DIR__.'/includes/layout_top.php';
?>
<?php if(!empty($error)):?><div class="alert error"><?=e($error)?></div><?php endif;?>

<?php if(!$vehicle): ?>
<section class="section-head"><div><span class="eyebrow">Mașini comune</span><h2>Garajul familiei</h2></div><button class="btn primary small" data-open-sheet="vehicleSheet"><?=icon('plus')?> Mașină</button></section>
<div class="vehicle-profile-grid">
<?php if(!$vehicles):?><div class="empty">Adaugă prima mașină.</div><?php endif;?>
<?php foreach($vehicles as $v):
    $rov=$statuses[$v['id']]['Rovinietă']??null;$rovStatus=vehicle_status($rov['due_date']??null);
    $rca=$statuses[$v['id']]['RCA']??null;$rcaStatus=vehicle_status($rca['due_date']??null);
    $itp=$statuses[$v['id']]['ITP']??null;$itpStatus=vehicle_status($itp['due_date']??null);
?>
<a class="vehicle-profile-card" href="/cars.php?id=<?=$v['id']?>" style="--vehicle-color:<?=e($v['color']?:'#315CF5')?>">
    <div class="vehicle-card-top"><span class="vehicle-color-mark"><?=icon(vehicle_icon_name((string)($v['vehicle_type']??'car')))?></span><span class="vehicle-open"><?=icon('chevron')?></span></div>
    <strong><?=e($v['name'])?></strong>
    <span class="vehicle-sub"><?=e($v['plate']?:'Fără număr')?><?php if($v['current_km']):?> · <?=number_format((int)$v['current_km'],0,',','.')?> km<?php endif;?></span>
    <div class="road-status <?=$rovStatus[0]?>"><b>Rovinietă</b><span><?=$rov?e(date_ro($rov['due_date'])):'Neconfigurată'?></span><small><?=e($rovStatus[1])?></small></div>
    <div class="vehicle-mini-status"><span class="<?=$rcaStatus[0]?>">RCA · <?=$rca?e($rcaStatus[1]):'—'?></span><span class="<?=$itpStatus[0]?>">ITP · <?=$itp?e($itpStatus[1]):'—'?></span></div>
</a>
<?php endforeach;?></div>

<div class="modal" id="vehicleSheet" aria-hidden="true"><div class="modal-backdrop" data-close-sheet></div><section class="sheet"><div class="sheet-handle"></div><div class="sheet-head"><div><span class="eyebrow">Garaj</span><h2>Mașină nouă</h2></div><button class="icon-btn" data-close-sheet><?=icon('close')?></button></div><form method="post" class="form-stack"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="add_vehicle"><label>Denumire<input name="name" required placeholder="Ex: BMW Seria 5"></label><label>Tip vehicul<select name="vehicle_type"><option value="car">Mașină</option><option value="atv">ATV</option><option value="motorcycle">Motocicletă</option></select></label><label>Culoare mașină<div class="vehicle-color-input"><input type="color" name="color" value="#315CF5"><span>Alege culoarea pentru cardul mașinii</span></div></label><label>Număr înmatriculare<input name="plate" placeholder="MM 00 ABC" autocapitalize="characters"></label><label>Serie șasiu (VIN)<input name="vin" maxlength="40" placeholder="WBA..." autocapitalize="characters"></label><label>Kilometraj actual<input name="current_km" inputmode="numeric" placeholder="Ex: 99850"></label><label>Notițe<textarea name="notes" rows="3" placeholder="Anvelope, particularități, service preferat etc."></textarea></label><button class="btn primary wide">Adaugă mașina</button></form></section></div>

<?php else: ?>
<div class="garage-breadcrumb"><a class="back-link" href="/cars.php">‹ Toate mașinile</a></div>
<section class="vehicle-profile-hero" style="--vehicle-color:<?=e($vehicle['color']?:'#315CF5')?>">
    <div class="vehicle-profile-icon"><?=icon(vehicle_icon_name((string)($vehicle['vehicle_type']??'car')))?></div>
    <div class="vehicle-profile-main">
        <span class="eyebrow">Profil mașină</span>
        <h2><?=e($vehicle['name'])?></h2>
        <div class="vehicle-hero-meta">
            <?php if(!empty($vehicle['plate'])):?><span><?=e($vehicle['plate'])?></span><?php endif;?>
            <span><?=number_format((int)($vehicle['current_km']??0),0,',','.')?> km</span>
        </div>
    </div>
    <button class="btn ghost small vehicle-edit-btn" data-open-sheet="editVehicleSheet">Editează</button>
</section>

<div class="vehicle-data-grid">
    <article class="vehicle-data-card"><span>Număr înmatriculare</span><strong><?=e($vehicle['plate']?:'—')?></strong><?php if($vehicle['plate']):?><button type="button" class="copy-btn" data-copy="<?=e($vehicle['plate'])?>"><?=icon('clipboard')?> Copiază</button><?php endif;?></article>
    <article class="vehicle-data-card"><span>Serie șasiu / VIN</span><strong class="vin-text"><?=e($vehicle['vin']?:'—')?></strong><?php if($vehicle['vin']):?><button type="button" class="copy-btn" data-copy="<?=e($vehicle['vin'])?>"><?=icon('clipboard')?> Copiază</button><?php endif;?></article>
    <article class="vehicle-data-card"><span>Kilometraj actual</span><strong><?=number_format((int)($vehicle['current_km']??0),0,',','.')?> km</strong><button type="button" class="copy-btn" data-open-sheet="kmSheet"><?=icon('wrench')?> Actualizează</button></article>
</div>

<?php $profileKinds=['Rovinietă','RCA','ITP']; ?>
<div class="vehicle-important-status">
<?php foreach($profileKinds as $kind):$row=$statuses[$vehicle['id']][$kind]??null;$st=vehicle_status($row['due_date']??null);?>
<article class="important-status <?=$st[0]?>"><span><?=e($kind)?></span><strong><?=$row?e(date_ro($row['due_date'])):'Neconfigurat'?></strong><small><?=e($st[1])?></small></article>
<?php endforeach;?>
</div>

<section class="section-head"><div><span class="eyebrow">Plan auto</span><h2>Expirări & mentenanță</h2></div><button class="btn primary small" data-open-sheet="reminderSheet"><?=icon('plus')?> Adaugă</button></section>
<div class="reminder-list">
<?php if(!$reminders):?><div class="empty">Nu ai încă termene sau mentenanțe pentru această mașină.</div><?php endif;?>
<?php foreach($reminders as $r):$days=days_until($r['due_date']);$kmLeft=($r['due_km']&&$vehicle['current_km'])?(int)$r['due_km']-(int)$vehicle['current_km']:null;$urgent=($days!==null&&$days<=30)||($kmLeft!==null&&$kmLeft<=1500);$expired=$days!==null&&$days<0;?>
<article class="reminder-card <?=$expired?'expired':($urgent?'urgent':'')?>"><span class="mini-icon <?=$expired?'coral':($urgent?'amber':'')?>"><?=icon(reminder_icon_name($r['kind']))?></span><div class="reminder-main"><div><strong><?=e($r['title'])?></strong><span><?=e($r['kind'])?></span></div><div class="reminder-meta"><?php if($r['due_date']):?><span><?=e(date_ro($r['due_date']))?> · <?=e(short_due_label($r['due_date']))?></span><?php endif;?><?php if($r['due_km']):?><span><?=number_format((int)$r['due_km'],0,',','.')?> km<?php if($kmLeft!==null):?> · <?= $kmLeft>=0?number_format($kmLeft,0,',','.').' km rămase':'depășit cu '.number_format(abs($kmLeft),0,',','.').' km'?><?php endif;?></span><?php endif;?><?php if($r['last_cost']):?><span><?=money_ro($r['last_cost'])?></span><?php endif;?></div><?php if(!empty($r['notes'])):?><p class="reminder-note"><?=nl2br(e($r['notes']))?></p><?php endif;?></div><div class="reminder-actions"><button type="button" class="btn ghost small" data-edit-reminder data-id="<?=$r['id']?>" data-kind="<?=e($r['kind'])?>" data-title="<?=e($r['title'])?>" data-date="<?=e($r['due_date']?date('d.m.Y',strtotime($r['due_date'])):'')?>" data-km="<?=e($r['due_km']??'')?>" data-days="<?=e($r['remind_days']??14)?>" data-cost="<?=e($r['last_cost']??'')?>" data-notes="<?=e($r['notes']??'')?>">Editează</button><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="complete_reminder"><input type="hidden" name="id" value="<?=$r['id']?>"><input type="hidden" name="vehicle_id" value="<?=$vehicle['id']?>"><button class="btn ghost small">Rezolvat</button></form></div></article>
<?php endforeach;?></div>

<section class="section-head"><div><span class="eyebrow">Cost real</span><h2>Ultimele 12 luni</h2></div><button class="btn primary small" data-open-sheet="vehicleCostSheet"><?=icon('plus')?> Cost</button></section>
<?php $vehicleCostTotal=array_sum($vehicleCostTotals);?><section class="vehicle-cost-summary"><article><span>Total</span><strong><?=money_ro($vehicleCostTotal)?></strong><small><?=money_ro($vehicleCostTotal/12)?> / lună</small></article><?php foreach(array_slice($vehicleCostTotals,0,4,true) as $cat=>$total):?><article><span><?=e($cat)?></span><strong><?=money_ro($total)?></strong></article><?php endforeach;?></section>
<div class="vehicle-cost-list"><?php foreach(array_slice($vehicleCosts,0,8) as $c):?><article><span class="mini-icon"><?=icon($c['category']==='Combustibil'?'fuel':'wrench')?></span><div><b><?=e($c['name'])?></b><small><?=e($c['category'])?> · <?=e(date_ro($c['cost_date']))?><?=$c['creator']?' · '.e($c['creator']):''?><?php if($c['transaction_id']):?> · în Buget<?php endif;?></small></div><strong><?=money_ro($c['amount'])?></strong></article><?php endforeach;?><?php if(!$vehicleCosts):?><div class="empty">Nu ai costuri înregistrate.</div><?php endif;?></div>
<div class="modal" id="vehicleCostSheet" aria-hidden="true"><div class="modal-backdrop" data-close-sheet></div><section class="sheet"><div class="sheet-handle"></div><div class="sheet-head"><h2>Cost mașină</h2><button class="icon-btn" data-close-sheet><?=icon('close')?></button></div><form method="post" class="form-stack"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="add_cost"><input type="hidden" name="vehicle_id" value="<?=$vehicle['id']?>"><label>Denumire<input name="name" required placeholder="Ex: Alimentare OMV"></label><div class="two-col"><label>Categorie<select name="category"><option>Combustibil</option><option>Service</option><option>RCA/CASCO</option><option>Taxe</option><option>Anvelope</option><option>Parcare</option><option>Altele</option></select></label><label>Sumă<input name="amount" inputmode="decimal" required></label></div><div class="two-col"><label>Data<input class="ro-date" name="cost_date" value="<?=date('d.m.Y')?>" required></label><label>Kilometraj<input name="odometer" inputmode="numeric" value="<?=e($vehicle['current_km']??'')?>"></label></div><label class="check-row"><input type="checkbox" name="add_to_budget" value="1" checked><span>Adaugă și în Buget → Transport</span></label><label>Notițe<textarea name="notes" rows="3"></textarea></label><button class="btn primary wide">Salvează costul</button></form></section></div>

<?php if(!empty($vehicle['notes'])):?><section class="vehicle-notes"><span class="eyebrow">Notițe mașină</span><p><?=nl2br(e($vehicle['notes']))?></p></section><?php endif;?>

<?php if($history):?><section class="section-head"><div><span class="eyebrow">Istoric</span><h2>Rezolvate recent</h2></div></section><div class="history-list"><?php foreach($history as $r):?><article><span><?=icon(reminder_icon_name($r['kind']))?></span><div><strong><?=e($r['title'])?></strong><small><?=e($r['kind'])?> · <?=e(date_ro($r['completed_at']))?></small></div></article><?php endforeach;?></div><?php endif;?>

<div class="modal" id="editVehicleSheet" aria-hidden="true"><div class="modal-backdrop" data-close-sheet></div><section class="sheet"><div class="sheet-handle"></div><div class="sheet-head"><div><span class="eyebrow">Profil</span><h2>Editează mașina</h2></div><button class="icon-btn" data-close-sheet><?=icon('close')?></button></div><form method="post" class="form-stack"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="update_vehicle"><input type="hidden" name="id" value="<?=$vehicle['id']?>"><label>Denumire<input name="name" required value="<?=e($vehicle['name'])?>"></label><label>Tip vehicul<select name="vehicle_type"><option value="car" <?=($vehicle['vehicle_type']??'car')==='car'?'selected':''?>>Mașină</option><option value="atv" <?=($vehicle['vehicle_type']??'car')==='atv'?'selected':''?>>ATV</option><option value="motorcycle" <?=($vehicle['vehicle_type']??'car')==='motorcycle'?'selected':''?>>Motocicletă</option></select></label><label>Culoare<div class="vehicle-color-input"><input type="color" name="color" value="<?=e($vehicle['color']?:'#315CF5')?>"><span>Culoarea cardului în garaj</span></div></label><label>Număr înmatriculare<input name="plate" value="<?=e($vehicle['plate'])?>" autocapitalize="characters"></label><label>Serie șasiu / VIN<input name="vin" maxlength="40" value="<?=e($vehicle['vin'])?>" autocapitalize="characters"></label><label>Kilometraj<input name="current_km" inputmode="numeric" value="<?=e($vehicle['current_km'])?>"></label><label>Notițe<textarea name="notes" rows="4"><?=e($vehicle['notes'])?></textarea></label><button class="btn primary wide">Salvează profilul</button></form></section></div>

<div class="modal" id="kmSheet" aria-hidden="true"><div class="modal-backdrop" data-close-sheet></div><section class="sheet"><div class="sheet-handle"></div><div class="sheet-head"><div><span class="eyebrow">Kilometraj</span><h2>Actualizează km</h2></div><button class="icon-btn" data-close-sheet><?=icon('close')?></button></div><form method="post" class="form-stack"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="update_km"><input type="hidden" name="id" value="<?=$vehicle['id']?>"><label>Kilometraj actual<input name="current_km" inputmode="numeric" value="<?=e($vehicle['current_km'])?>" autofocus></label><button class="btn primary wide">Actualizează</button></form></section></div>

<div class="modal" id="reminderSheet" aria-hidden="true"><div class="modal-backdrop" data-close-sheet></div><section class="sheet"><div class="sheet-handle"></div><div class="sheet-head"><div><span class="eyebrow">Auto</span><h2>Termen / mentenanță</h2></div><button class="icon-btn" data-close-sheet><?=icon('close')?></button></div><form method="post" class="form-stack"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="add_reminder"><input type="hidden" name="vehicle_id" value="<?=$vehicle['id']?>"><label>Tip<select name="kind"><option>Rovinietă</option><option>RCA</option><option>CASCO</option><option>ITP</option><option>Revizie</option><option>Schimb ulei</option><option>Anvelope</option><option>Impozit</option><option>Altele</option></select></label><label>Denumire<input name="title" required placeholder="Ex: Schimb ulei și filtre"></label><div class="form-grid"><label>Data<input class="ro-date" name="due_date" inputmode="numeric" maxlength="10" placeholder="ZZ.LL.AAAA"></label><label>La km<input name="due_km" inputmode="numeric" placeholder="Ex: 102000"></label></div><label>Notifică înainte<select name="remind_days"><option value="7">7 zile</option><option value="14" selected>14 zile</option><option value="30">30 zile</option><option value="60">60 zile</option></select></label><label>Cost anterior / estimat<input name="last_cost" inputmode="decimal" placeholder="0,00"></label><label>Notițe<textarea name="notes" rows="3" placeholder="Service, marcă ulei, detalii poliță etc."></textarea></label><button class="btn primary wide">Salvează</button></form></section></div>

<div class="modal" id="editReminderSheet" aria-hidden="true"><div class="modal-backdrop" data-close-sheet></div><section class="sheet"><div class="sheet-handle"></div><div class="sheet-head"><div><h2>Editează termenul</h2></div><button class="icon-btn" type="button" data-close-sheet><?=icon('close')?></button></div><form method="post" class="form-stack" id="editReminderForm"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="update_reminder"><input type="hidden" name="vehicle_id" value="<?=$vehicle['id']?>"><input type="hidden" name="id" value=""><label>Tip<select name="kind"><option>Rovinietă</option><option>RCA</option><option>CASCO</option><option>ITP</option><option>Revizie</option><option>Schimb ulei</option><option>Anvelope</option><option>Impozit</option><option>Altele</option></select></label><label>Denumire<input name="title" required></label><div class="form-grid"><label>Data<input class="ro-date" name="due_date" inputmode="numeric" maxlength="10" placeholder="ZZ.LL.AAAA"></label><label>La km<input name="due_km" inputmode="numeric" placeholder="Ex: 102000"></label></div><label>Notifică înainte<select name="remind_days"><option value="7">7 zile</option><option value="14">14 zile</option><option value="30">30 zile</option><option value="60">60 zile</option></select></label><label>Cost anterior / estimat<input name="last_cost" inputmode="decimal" placeholder="0,00"></label><label>Notițe<textarea name="notes" rows="3"></textarea></label><button class="btn primary wide">Salvează modificările</button></form><form method="post" class="reminder-delete-form" onsubmit="return confirm('Ștergi acest termen?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_reminder"><input type="hidden" name="vehicle_id" value="<?=$vehicle['id']?>"><input type="hidden" name="id" value=""><button class="btn ghost wide danger" type="submit">Șterge termenul</button></form></section></div>
<?php endif;?>

<?php require __DIR__.'/includes/layout_bottom.php';?>
