<?php
require __DIR__.'/includes/bootstrap.php';
require_login();
require_member($pdo);

$memberId=current_member_id();
$message='';$error='';

function rental_period_label(string $period): string { return month_label_ro($period); }

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action=(string)($_POST['action']??'');
    try{
        if($action==='add_property'){
            $name=trim((string)($_POST['name']??''));
            if($name==='') throw new RuntimeException('Completează denumirea apartamentului.');
            $address=trim((string)($_POST['address']??''))?:null;
            $tenant=trim((string)($_POST['tenant_name']??''))?:null;
            $rent=parse_amount_ro((string)($_POST['monthly_rent']??''));
            if($rent<=0) throw new RuntimeException('Completează chiria lunară.');
            $due=max(1,min(31,(int)($_POST['due_day']??1)));
            $notes=trim((string)($_POST['notes']??''))?:null;
            $pdo->beginTransaction();
            $g=$pdo->prepare('INSERT INTO savings_goals(name,target_amount,active) VALUES(?,?,1)');
            $g->execute(['Fond lucrări · '.$name,0]);
            $goalId=(int)$pdo->lastInsertId();
            $s=$pdo->prepare('INSERT INTO rental_properties(name,address,tenant_name,monthly_rent,due_day,savings_goal_id,notes,created_by_member_id) VALUES(?,?,?,?,?,?,?,?)');
            $s->execute([$name,$address,$tenant,$rent,$due,$goalId,$notes,$memberId]);
            $id=(int)$pdo->lastInsertId();
            $pdo->commit();
            ensure_rental_periods($pdo,$id);
            log_activity($pdo,'rental_property_added','rental_property',$id,$name);
            header('Location: /rents.php?property='.$id);exit;
        }
        if($action==='update_property'){
            $id=(int)($_POST['id']??0);
            $name=trim((string)($_POST['name']??''));
            if($id<1||$name==='') throw new RuntimeException('Datele apartamentului nu sunt valide.');
            $address=trim((string)($_POST['address']??''))?:null;
            $tenant=trim((string)($_POST['tenant_name']??''))?:null;
            $rent=parse_amount_ro((string)($_POST['monthly_rent']??''));
            if($rent<=0) throw new RuntimeException('Completează chiria lunară.');
            $due=max(1,min(31,(int)($_POST['due_day']??1)));
            $notes=trim((string)($_POST['notes']??''))?:null;
            $q=$pdo->prepare('SELECT savings_goal_id FROM rental_properties WHERE id=?');$q->execute([$id]);$goalId=(int)$q->fetchColumn();
            $s=$pdo->prepare('UPDATE rental_properties SET name=?,address=?,tenant_name=?,monthly_rent=?,due_day=?,notes=? WHERE id=?');
            $s->execute([$name,$address,$tenant,$rent,$due,$notes,$id]);
            if($goalId>0){$pdo->prepare('UPDATE savings_goals SET name=? WHERE id=?')->execute(['Fond lucrări · '.$name,$goalId]);}
            // Only future pending rows inherit the new standard amount/day.
            $rows=$pdo->prepare("SELECT id,period FROM rental_payments WHERE property_id=? AND status='pending' AND period>=?");$rows->execute([$id,date('Y-m',strtotime('-2 months'))]);
            $u=$pdo->prepare('UPDATE rental_payments SET expected_amount=?,due_date=? WHERE id=?');
            foreach($rows as $r){$u->execute([$rent,rental_due_date_for_period((string)$r['period'],$due),$r['id']]);}
            ensure_rental_periods($pdo,$id);
            log_activity($pdo,'rental_property_updated','rental_property',$id,$name);
            $message='Apartamentul a fost actualizat.';
        }
        if($action==='update_payment'){
            $id=(int)($_POST['id']??0);
            $amount=parse_amount_ro((string)($_POST['amount']??''));
            $due=parse_ro_date((string)($_POST['due_date']??''));
            if($id<1||$amount<=0||!$due) throw new RuntimeException('Completează suma și data scadenței.');
            $q=$pdo->prepare('SELECT rp.*,p.name property_name,p.savings_goal_id FROM rental_payments rp JOIN rental_properties p ON p.id=rp.property_id WHERE rp.id=?');$q->execute([$id]);$r=$q->fetch();
            if(!$r) throw new RuntimeException('Chiria nu a fost găsită.');
            $pdo->prepare('UPDATE rental_payments SET expected_amount=?,due_date=? WHERE id=?')->execute([$amount,$due,$id]);
            if($r['status']==='paid'){
                if($r['transaction_id']) $pdo->prepare('UPDATE transactions SET amount=?,name=? WHERE id=?')->execute([$amount,'Chirie · '.$r['property_name'],$r['transaction_id']]);
                if($r['savings_contribution_id']) $pdo->prepare('UPDATE savings_contributions SET amount=? WHERE id=?')->execute([$amount,$r['savings_contribution_id']]);
                $pdo->prepare('UPDATE rental_payments SET paid_amount=? WHERE id=?')->execute([$amount,$id]);
            }
            log_activity($pdo,'rental_payment_updated','rental_payment',$id,'Chirie actualizată · '.money_ro($amount));
            $message='Chiria programată a fost actualizată.';
        }
        if($action==='mark_paid'){
            $id=(int)($_POST['id']??0);
            $amount=parse_amount_ro((string)($_POST['paid_amount']??''));
            $paidDate=parse_ro_date((string)($_POST['paid_date']??''));
            if($id<1||$amount<=0||!$paidDate) throw new RuntimeException('Completează suma încasată și data plății.');
            $q=$pdo->prepare('SELECT rp.*,p.name property_name,p.savings_goal_id FROM rental_payments rp JOIN rental_properties p ON p.id=rp.property_id WHERE rp.id=?');$q->execute([$id]);$r=$q->fetch();
            if(!$r) throw new RuntimeException('Chiria nu a fost găsită.');
            $pdo->beginTransaction();
            $txId=(int)($r['transaction_id']??0);$contribId=(int)($r['savings_contribution_id']??0);
            $txDate=$paidDate.' '.date('H:i:s');
            if($txId>0){
                $pdo->prepare("UPDATE transactions SET type='income',name=?,amount=?,category='Chirii',transaction_date=?,created_by_member_id=? WHERE id=?")->execute(['Chirie · '.$r['property_name'],$amount,$txDate,$memberId,$txId]);
            }else{
                $t=$pdo->prepare("INSERT INTO transactions(type,name,amount,category,transaction_date,created_by_member_id) VALUES('income',?,?,?,?,?)");
                $t->execute(['Chirie · '.$r['property_name'],$amount,'Chirii',$txDate,$memberId]);$txId=(int)$pdo->lastInsertId();
                add_transaction_meta($pdo,$txId,'rent','income',$id);
            }
            if((int)$r['savings_goal_id']>0){
                if($contribId>0){
                    $pdo->prepare('UPDATE savings_contributions SET amount=?,contribution_date=?,created_by_member_id=? WHERE id=?')->execute([$amount,$paidDate,$memberId,$contribId]);
                }else{
                    $c=$pdo->prepare('INSERT INTO savings_contributions(goal_id,amount,contribution_date,created_by_member_id) VALUES(?,?,?,?)');
                    $c->execute([(int)$r['savings_goal_id'],$amount,$paidDate,$memberId]);$contribId=(int)$pdo->lastInsertId();
                }
            }
            $pdo->prepare("UPDATE rental_payments SET status='paid',paid_amount=?,paid_date=?,transaction_id=?,savings_contribution_id=?,paid_by_member_id=? WHERE id=?")->execute([$amount,$paidDate,$txId,$contribId?:null,$memberId,$id]);
            $pdo->commit();
            log_activity($pdo,'rental_paid','rental_payment',$id,'Chirie încasată · '.$r['property_name'].' · '.money_ro($amount));
            $message='Chiria a fost încasată și mutată automat în economiile apartamentului.';
        }
        if($action==='skip_payment'){
            $id=(int)($_POST['id']??0);
            $q=$pdo->prepare("SELECT rp.*,p.name property_name FROM rental_payments rp JOIN rental_properties p ON p.id=rp.property_id WHERE rp.id=?");$q->execute([$id]);$r=$q->fetch();
            if(!$r) throw new RuntimeException('Luna de chirie nu a fost găsită.');
            if($r['status']==='paid') throw new RuntimeException('O lună deja încasată trebuie întâi anulată.');
            $pdo->prepare("UPDATE rental_payments SET status='cancelled',paid_amount=NULL,paid_date=NULL WHERE id=?")->execute([$id]);
            log_activity($pdo,'rental_payment_skipped','rental_payment',$id,'Fără chirie · '.$r['property_name'].' · '.rental_period_label($r['period']));
            $message='Luna a fost dezactivată. Nu va apărea ca scadentă și nu va genera notificări.';
        }
        if($action==='restore_payment'){
            $id=(int)($_POST['id']??0);
            $q=$pdo->prepare('SELECT period,property_id FROM rental_payments WHERE id=?');$q->execute([$id]);$r=$q->fetch();
            if(!$r) throw new RuntimeException('Luna nu a fost găsită.');
            $pdo->prepare("UPDATE rental_payments SET status='pending' WHERE id=?")->execute([$id]);
            log_activity($pdo,'rental_payment_restored','rental_payment',$id,'Lună de chirie reactivată · '.rental_period_label($r['period']));
            $message='Luna a fost reactivată.';
        }
        if($action==='reopen_payment'){
            $id=(int)($_POST['id']??0);
            $q=$pdo->prepare('SELECT transaction_id,savings_contribution_id FROM rental_payments WHERE id=?');$q->execute([$id]);$r=$q->fetch();
            if(!$r) throw new RuntimeException('Încasarea nu a fost găsită.');
            $pdo->beginTransaction();
            if($r['transaction_id']) $pdo->prepare('DELETE FROM transactions WHERE id=?')->execute([$r['transaction_id']]);
            if($r['savings_contribution_id']) $pdo->prepare('DELETE FROM savings_contributions WHERE id=?')->execute([$r['savings_contribution_id']]);
            $pdo->prepare("UPDATE rental_payments SET status='pending',paid_amount=NULL,paid_date=NULL,transaction_id=NULL,savings_contribution_id=NULL,paid_by_member_id=NULL WHERE id=?")->execute([$id]);
            $pdo->commit();
            log_activity($pdo,'rental_reopened','rental_payment',$id,'Chirie marcată din nou ca neîncasată');
            $message='Încasarea a fost anulată și a revenit la De încasat.';
        }
        if($action==='add_improvement' || $action==='update_improvement'){
            $propertyId=(int)($_POST['property_id']??0);$title=trim((string)($_POST['title']??''));$cost=parse_amount_ro((string)($_POST['estimated_cost']??''));
            $target=trim((string)($_POST['target_date']??'')).'';$target=$target!==''?parse_ro_date($target):null;
            $priority=(string)($_POST['priority']??'medium');if(!in_array($priority,['high','medium','low'],true))$priority='medium';
            $notes=trim((string)($_POST['notes']??''))?:null;
            if($propertyId<1||$title==='') throw new RuntimeException('Completează lucrarea dorită.');
            if($action==='add_improvement'){
                $s=$pdo->prepare("INSERT INTO rental_improvements(property_id,title,estimated_cost,target_date,priority,notes,created_by_member_id) VALUES(?,?,?,?,?,?,?)");
                $s->execute([$propertyId,$title,$cost,$target,$priority,$notes,$memberId]);$iid=(int)$pdo->lastInsertId();
                update_rental_goal_target($pdo,$propertyId);log_activity($pdo,'rental_improvement_added','rental_improvement',$iid,$title.' · '.money_ro($cost));$message='Lucrarea a fost adăugată.';
            }else{
                $iid=(int)($_POST['id']??0);$s=$pdo->prepare('UPDATE rental_improvements SET title=?,estimated_cost=?,target_date=?,priority=?,notes=? WHERE id=? AND property_id=?');$s->execute([$title,$cost,$target,$priority,$notes,$iid,$propertyId]);
                update_rental_goal_target($pdo,$propertyId);log_activity($pdo,'rental_improvement_updated','rental_improvement',$iid,$title);$message='Lucrarea a fost actualizată.';
            }
        }
        if($action==='toggle_improvement'){
            $id=(int)($_POST['id']??0);$q=$pdo->prepare('SELECT title,status FROM rental_improvements WHERE id=?');$q->execute([$id]);$r=$q->fetch();if(!$r)throw new RuntimeException('Lucrarea nu a fost găsită.');
            $new=$r['status']==='done'?'planned':'done';$pdo->prepare("UPDATE rental_improvements SET status=?,completed_at=IF(?='done',NOW(),NULL) WHERE id=?")->execute([$new,$new,$id]);
            $pp=$pdo->prepare('SELECT property_id FROM rental_improvements WHERE id=?');$pp->execute([$id]);update_rental_goal_target($pdo,(int)$pp->fetchColumn());log_activity($pdo,$new==='done'?'rental_improvement_done':'rental_improvement_reopened','rental_improvement',$id,$r['title']);$message=$new==='done'?'Lucrarea a fost marcată realizată.':'Lucrarea a fost redeschisă.';
        }
        if($action==='delete_improvement'){
            $id=(int)($_POST['id']??0);$q=$pdo->prepare('SELECT title,property_id FROM rental_improvements WHERE id=?');$q->execute([$id]);$rr=$q->fetch();$title=(string)($rr['title']??'Lucrare');$propertyId=(int)($rr['property_id']??0);$pdo->prepare('DELETE FROM rental_improvements WHERE id=?')->execute([$id]);if($propertyId)update_rental_goal_target($pdo,$propertyId);log_activity($pdo,'rental_improvement_deleted','rental_improvement',$id,$title);$message='Lucrarea a fost ștearsă.';
        }
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();}
}

$properties=$pdo->query("SELECT p.*,m.name creator FROM rental_properties p LEFT JOIN family_members m ON m.id=p.created_by_member_id WHERE p.active=1 ORDER BY p.id")->fetchAll();
foreach($properties as $p) ensure_rental_periods($pdo,(int)$p['id']);
$propertyId=(int)($_GET['property']??($properties[0]['id']??0));
$property=null;foreach($properties as $p)if((int)$p['id']===$propertyId){$property=$p;break;}
if(!$property && $properties){$property=$properties[0];$propertyId=(int)$property['id'];}
$payments=[];$improvements=[];$fund=0;$planned=0;
if($property){
    $q=$pdo->prepare("SELECT rp.*,m.name paid_by FROM rental_payments rp LEFT JOIN family_members m ON m.id=rp.paid_by_member_id WHERE rp.property_id=? ORDER BY rp.period DESC LIMIT 14");$q->execute([$propertyId]);$payments=$q->fetchAll();
    $q=$pdo->prepare("SELECT ri.*,m.name creator FROM rental_improvements ri LEFT JOIN family_members m ON m.id=ri.created_by_member_id WHERE ri.property_id=? ORDER BY FIELD(ri.status,'planned','done','cancelled'),FIELD(ri.priority,'high','medium','low'),ri.target_date IS NULL,ri.target_date,ri.id DESC");$q->execute([$propertyId]);$improvements=$q->fetchAll();
    $fund=rental_goal_balance($pdo,(int)$property['savings_goal_id']);$planned=rental_planned_total($pdo,$propertyId);
}
$pageTitle='Chirii';$active='more';require __DIR__.'/includes/layout_top.php';
?>
<?php if($message):?><div class="alert success"><?=e($message)?></div><?php endif;?><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<section class="rental-hero"><div><span>APARTAMENT ÎNCHIRIAT</span><h2><?=e($property['name']??'Chirii')?></h2><p><?php if($property):?>Chiria încasată intră automat în fondul apartamentului pentru lucrări viitoare.<?php else:?>Adaugă apartamentul și urmărește chiria, economiile și lucrările într-un singur loc.<?php endif;?></p></div><button class="btn primary small" type="button" data-open-sheet="rentalPropertySheet"><?=icon($property?'edit':'plus')?> <?=$property?'Editează':'Adaugă'?></button></section>
<?php if($properties):?><div class="rental-property-switch"><?php foreach($properties as $p):?><a class="<?=((int)$p['id']===$propertyId?'active':'')?>" href="/rents.php?property=<?=$p['id']?>"><?=e($p['name'])?></a><?php endforeach;?></div><?php endif;?>
<?php if($property):?>
<section class="rental-fund-card"><div class="rental-fund-top"><div><small>Fondul apartamentului</small><strong><?=money_ro($fund)?></strong><span>din chirii încasate</span></div><span class="rental-fund-icon"><?=icon('piggy')?></span></div><div class="rental-fund-grid"><div><small>Chirie lunară</small><b><?=money_ro($property['monthly_rent'])?></b></div><div><small>Scadență chirie</small><b>ziua <?=$property['due_day']?> · luna următoare</b></div><div><small>Lucrări planificate</small><b><?=money_ro($planned)?></b></div></div><?php if($planned>0):$pct=min(100,(int)round($fund/$planned*100));?><div class="rental-progress-label"><span>Fond disponibil pentru lucrările planificate</span><b><?=$pct?>%</b></div><div class="progress savings"><i style="width:<?=$pct?>%"></i></div><?php endif;?></section>

<section class="action-head"><div><h2>Chirie</h2><p><?=$property['tenant_name']?'Chiriaș: '.e($property['tenant_name']).' · ':''?><?=e((string)$property['address'])?></p></div></section>
<div class="rental-payment-list">
<?php foreach($payments as $r):$isPaid=$r['status']==='paid';$isSkipped=$r['status']==='cancelled';$days=$isSkipped?null:days_until($r['due_date']);?>
<article class="rental-payment-card <?=$isSkipped?'skipped':($isPaid?'paid':(($days!==null&&$days<0)?'late':''))?>"><div class="rental-payment-main"><span class="rental-payment-status"><?=$isSkipped?icon('minus'):($isPaid?icon('check'):icon('calendar'))?></span><div><strong><?=e(rental_period_label($r['period']))?></strong><span><?=$isSkipped?'Fără chirie în această lună':($isPaid?'Încasată '.e(date_ro($r['paid_date'])):('Scadență '.e(date_ro($r['due_date'])).' · '.e(short_due_label($r['due_date']))))?></span><?php if($isPaid&&$r['paid_by']):?><small>Confirmată de <?=e($r['paid_by'])?></small><?php endif;?></div></div><div class="rental-payment-side"><?php if(!$isSkipped):?><b><?=money_ro($isPaid?$r['paid_amount']:$r['expected_amount'])?></b><?php endif;?><div class="rental-payment-actions"><?php if($isSkipped):?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="restore_payment"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn soft small" type="submit">Reactivează</button></form><?php elseif(!$isPaid):?><button class="btn primary small" type="button" data-pay-rent data-id="<?=$r['id']?>" data-amount="<?=e(number_format((float)$r['expected_amount'],2,',',''))?>" data-date="<?=e(date('d.m.Y'))?>">Încasată</button><form method="post" onsubmit="return confirm('Dezactivezi chiria pentru această lună? Nu va mai apărea ca scadentă.');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="skip_payment"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn ghost small" type="submit">Fără chirie</button></form><?php else:?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="reopen_payment"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn ghost small" type="submit">Anulează</button></form><?php endif;?><?php if(!$isSkipped):?><button class="icon-btn" type="button" data-edit-rent data-id="<?=$r['id']?>" data-amount="<?=e(number_format((float)$r['expected_amount'],2,',',''))?>" data-date="<?=e(date_ro($r['due_date']))?>" aria-label="Editează"><?=icon('edit')?></button><?php endif;?></div></div></article>
<?php endforeach;?></div>

<section class="action-head rental-improvement-head"><div><h2>Îmbunătățiri & renovări</h2><p>Ce vreți să faceți în apartament și cât estimați că va costa.</p></div><button class="btn primary compact" type="button" data-open-sheet="rentalImprovementSheet"><?=icon('plus')?> Adaugă</button></section>
<div class="rental-improvement-list"><?php if(!$improvements):?><div class="empty-card">Adaugă lucrări precum zugrăvit, mobilă, electrocasnice sau renovări.</div><?php endif;?><?php foreach($improvements as $i):$done=$i['status']==='done';?><article class="rental-improvement-card <?=$done?'done':''?>"><span class="rental-improvement-icon"><?=icon($done?'check':'hammer')?></span><div class="rental-improvement-main"><div class="rental-improvement-title"><strong><?=e($i['title'])?></strong><span class="priority <?=$i['priority']?>"><?=['high'=>'Prioritar','medium'=>'Mediu','low'=>'Când se poate'][$i['priority']]?></span></div><span><?=money_ro($i['estimated_cost'])?><?=$i['target_date']?' · țintă '.e(date_ro($i['target_date'])):''?></span><?php if($i['notes']):?><small><?=e($i['notes'])?></small><?php endif;?><small>Adăugat de <?=e($i['creator']?:'Sistem')?></small></div><div class="rental-improvement-actions"><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle_improvement"><input type="hidden" name="id" value="<?=$i['id']?>"><button class="btn soft small"><?=$done?'Redeschide':'Realizată'?></button></form><button class="icon-btn" type="button" data-edit-improvement data-id="<?=$i['id']?>" data-title="<?=e($i['title'])?>" data-cost="<?=e(number_format((float)$i['estimated_cost'],2,',',''))?>" data-date="<?=e($i['target_date']?date_ro($i['target_date']):'')?>" data-priority="<?=e($i['priority'])?>" data-notes="<?=e((string)$i['notes'])?>" aria-label="Editează"><?=icon('edit')?></button><form method="post" onsubmit="return confirm('Ștergi această lucrare?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_improvement"><input type="hidden" name="id" value="<?=$i['id']?>"><button class="icon-btn danger" aria-label="Șterge"><?=icon('trash')?></button></form></div></article><?php endforeach;?></div>
<?php endif;?>

<div class="modal" id="rentalPropertySheet" aria-hidden="true"><div class="modal-backdrop" data-close-sheet></div><section class="sheet"><div class="sheet-handle"></div><div class="sheet-head"><div><h2><?=$property?'Editează apartamentul':'Adaugă apartament'?></h2></div><button class="icon-btn" data-close-sheet><?=icon('close')?></button></div><form method="post" class="form-stack"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="<?=$property?'update_property':'add_property'?>"><?php if($property):?><input type="hidden" name="id" value="<?=$propertyId?>"><?php endif;?><label>Denumire<input name="name" required value="<?=e((string)($property['name']??''))?>" placeholder="Ex: Apartament Cluj"></label><label>Adresă<input name="address" value="<?=e((string)($property['address']??''))?>"></label><label>Chiriaș<input name="tenant_name" value="<?=e((string)($property['tenant_name']??''))?>"></label><div class="form-grid two"><label>Chirie lunară<input name="monthly_rent" inputmode="decimal" required value="<?=e(isset($property['monthly_rent'])?number_format((float)$property['monthly_rent'],2,',',''):'')?>" placeholder="2.000"></label><label>Zi plată <small class="field-note">în luna următoare</small><input name="due_day" type="number" min="1" max="31" value="<?=e((string)($property['due_day']??1))?>" required></label></div><label>Notițe<textarea name="notes" rows="3"><?=e((string)($property['notes']??''))?></textarea></label><button class="btn primary wide">Salvează</button></form></section></div>

<?php if($property):?>
<div class="modal" id="rentPaySheet" aria-hidden="true"><div class="modal-backdrop" data-close-sheet></div><section class="sheet"><div class="sheet-handle"></div><div class="sheet-head"><div><h2>Confirmă chiria</h2></div><button class="icon-btn" data-close-sheet><?=icon('close')?></button></div><form method="post" class="form-stack" id="rentPayForm"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="mark_paid"><input type="hidden" name="id"><label>Sumă încasată<div class="input-wrap money-input"><?=icon('wallet')?><input name="paid_amount" inputmode="decimal" required><span>lei</span></div></label><label>Data încasării<input class="ro-date" name="paid_date" inputmode="numeric" maxlength="10" required></label><div class="info-note compact"><span class="mini-icon green"><?=icon('piggy')?></span><div><b>Intră automat în fond</b><p>Încasarea apare în Buget și aceeași sumă este pusă deoparte pentru lucrările apartamentului.</p></div></div><button class="btn primary wide">Confirmă încasarea</button></form></section></div>
<div class="modal" id="rentEditSheet" aria-hidden="true"><div class="modal-backdrop" data-close-sheet></div><section class="sheet"><div class="sheet-handle"></div><div class="sheet-head"><div><h2>Editează chiria</h2></div><button class="icon-btn" data-close-sheet><?=icon('close')?></button></div><form method="post" class="form-stack" id="rentEditForm"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="update_payment"><input type="hidden" name="id"><label>Sumă<div class="input-wrap money-input"><?=icon('wallet')?><input name="amount" inputmode="decimal" required><span>lei</span></div></label><label>Scadență<input class="ro-date" name="due_date" inputmode="numeric" maxlength="10" required></label><button class="btn primary wide">Salvează modificările</button></form></section></div>
<div class="modal" id="rentalImprovementSheet" aria-hidden="true"><div class="modal-backdrop" data-close-sheet></div><section class="sheet"><div class="sheet-handle"></div><div class="sheet-head"><div><h2>Lucrare nouă</h2></div><button class="icon-btn" data-close-sheet><?=icon('close')?></button></div><form method="post" class="form-stack"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="add_improvement"><input type="hidden" name="property_id" value="<?=$propertyId?>"><label>Ce vreți să faceți?<input name="title" required placeholder="Ex: Zugrăvit living"></label><label>Cost estimativ<div class="input-wrap money-input"><?=icon('wallet')?><input name="estimated_cost" inputmode="decimal" placeholder="2.500"><span>lei</span></div></label><div class="form-grid two"><label>Țintă<input class="ro-date" name="target_date" inputmode="numeric" maxlength="10" placeholder="ZZ.LL.AAAA"></label><label>Prioritate<select name="priority"><option value="high">Prioritar</option><option value="medium" selected>Mediu</option><option value="low">Când se poate</option></select></label></div><label>Notițe<textarea name="notes" rows="3"></textarea></label><button class="btn primary wide">Adaugă lucrarea</button></form></section></div>
<div class="modal" id="rentalImprovementEditSheet" aria-hidden="true"><div class="modal-backdrop" data-close-sheet></div><section class="sheet"><div class="sheet-handle"></div><div class="sheet-head"><div><h2>Editează lucrarea</h2></div><button class="icon-btn" data-close-sheet><?=icon('close')?></button></div><form method="post" class="form-stack" id="rentalImprovementEditForm"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="update_improvement"><input type="hidden" name="property_id" value="<?=$propertyId?>"><input type="hidden" name="id"><label>Denumire<input name="title" required></label><label>Cost estimativ<div class="input-wrap money-input"><?=icon('wallet')?><input name="estimated_cost" inputmode="decimal"><span>lei</span></div></label><div class="form-grid two"><label>Țintă<input class="ro-date" name="target_date" inputmode="numeric" maxlength="10"></label><label>Prioritate<select name="priority"><option value="high">Prioritar</option><option value="medium">Mediu</option><option value="low">Când se poate</option></select></label></div><label>Notițe<textarea name="notes" rows="3"></textarea></label><button class="btn primary wide">Salvează modificările</button></form></section></div>
<script>
document.querySelectorAll('[data-pay-rent]').forEach(b=>b.addEventListener('click',()=>{const m=document.getElementById('rentPaySheet'),f=document.getElementById('rentPayForm');f.elements.id.value=b.dataset.id||'';f.elements.paid_amount.value=b.dataset.amount||'';f.elements.paid_date.value=b.dataset.date||'';m.classList.add('open');m.setAttribute('aria-hidden','false');document.body.classList.add('modal-open');}));
document.querySelectorAll('[data-edit-rent]').forEach(b=>b.addEventListener('click',()=>{const m=document.getElementById('rentEditSheet'),f=document.getElementById('rentEditForm');f.elements.id.value=b.dataset.id||'';f.elements.amount.value=b.dataset.amount||'';f.elements.due_date.value=b.dataset.date||'';m.classList.add('open');m.setAttribute('aria-hidden','false');document.body.classList.add('modal-open');}));
document.querySelectorAll('[data-edit-improvement]').forEach(b=>b.addEventListener('click',()=>{const m=document.getElementById('rentalImprovementEditSheet'),f=document.getElementById('rentalImprovementEditForm');f.elements.id.value=b.dataset.id||'';f.elements.title.value=b.dataset.title||'';f.elements.estimated_cost.value=b.dataset.cost||'';f.elements.target_date.value=b.dataset.date||'';f.elements.priority.value=b.dataset.priority||'medium';f.elements.notes.value=b.dataset.notes||'';m.classList.add('open');m.setAttribute('aria-hidden','false');document.body.classList.add('modal-open');}));
</script>
<?php endif;?>
<?php require __DIR__.'/includes/layout_bottom.php';?>
