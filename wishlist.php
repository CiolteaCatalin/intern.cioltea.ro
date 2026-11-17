<?php
require __DIR__.'/includes/bootstrap.php';
require_login();
require_member($pdo);
$memberId=current_member_id();
$message='';$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action=$_POST['action']??'';
    try{
        if(in_array($action,['add','update'],true)){
            $name=trim($_POST['name']??'');
            if($name==='') throw new RuntimeException('Completează denumirea dorinței.');
            $estimated=parse_amount_ro($_POST['estimated_cost']??'');
            $priority=in_array($_POST['priority']??'',['need','nice','want'],true)?$_POST['priority']:'want';
            $targetDate=parse_ro_date($_POST['target_date']??'');
            $goalId=(int)($_POST['savings_goal_id']??0); $goalId=$goalId>0?$goalId:null;
            if($goalId){$q=$pdo->prepare('SELECT id FROM savings_goals WHERE id=? LIMIT 1');$q->execute([$goalId]);if(!$q->fetchColumn())$goalId=null;}
            $url=trim($_POST['url']??'')?:null;
            $notes=trim($_POST['notes']??'')?:null;
            if($action==='add'){
                $s=$pdo->prepare('INSERT INTO wishlist_items(name,estimated_cost,target_date,savings_goal_id,priority,url,notes,created_by_member_id) VALUES(?,?,?,?,?,?,?,?)');
                $s->execute([$name,$estimated>0?$estimated:null,$targetDate,$goalId,$priority,$url,$notes,$memberId]);
                $id=(int)$pdo->lastInsertId();
                log_activity($pdo,'wishlist_added','wishlist',$id,$name);
                $message='Dorința a fost adăugată.';
            }else{
                $id=(int)($_POST['id']??0); if($id<1) throw new RuntimeException('Dorința nu este validă.');
                $s=$pdo->prepare('UPDATE wishlist_items SET name=?,estimated_cost=?,target_date=?,savings_goal_id=?,priority=?,url=?,notes=? WHERE id=?');
                $s->execute([$name,$estimated>0?$estimated:null,$targetDate,$goalId,$priority,$url,$notes,$id]);
                log_activity($pdo,'wishlist_updated','wishlist',$id,$name);
                $message='Dorința a fost actualizată.';
            }
        }
        if($action==='toggle'){
            $id=(int)($_POST['id']??0);
            $pdo->prepare('UPDATE wishlist_items SET purchased=1-purchased WHERE id=?')->execute([$id]);
            $message='Statusul dorinței a fost actualizat.';
        }
        if($action==='delete'){
            $id=(int)($_POST['id']??0);
            $q=$pdo->prepare('SELECT name FROM wishlist_items WHERE id=?');$q->execute([$id]);$name=(string)($q->fetchColumn()?:'Dorință');
            $pdo->prepare('DELETE FROM wishlist_items WHERE id=?')->execute([$id]);
            log_activity($pdo,'wishlist_deleted','wishlist',$id,$name);
            $message='Dorința a fost ștearsă.';
        }
    }catch(Throwable $e){$error=$e->getMessage();}
}

$goals=$pdo->query("SELECT g.id,g.name,g.target_amount,COALESCE(SUM(sc.amount),0) saved FROM savings_goals g LEFT JOIN savings_contributions sc ON sc.goal_id=g.id WHERE g.active=1 GROUP BY g.id ORDER BY g.id DESC")->fetchAll();
$overallSaved=(float)$pdo->query('SELECT COALESCE(SUM(amount),0) FROM savings_contributions')->fetchColumn();
$rows=$pdo->query("SELECT w.*,m.name creator,sg.name savings_goal_name,sg.target_amount savings_goal_target,COALESCE(SUM(sc.amount),0) goal_saved FROM wishlist_items w LEFT JOIN family_members m ON m.id=w.created_by_member_id LEFT JOIN savings_goals sg ON sg.id=w.savings_goal_id LEFT JOIN savings_contributions sc ON sc.goal_id=w.savings_goal_id GROUP BY w.id ORDER BY w.purchased,FIELD(w.priority,'need','nice','want'),w.target_date IS NULL,w.target_date,w.id DESC")->fetchAll();
$pageTitle='Wishlist';$active='more';require __DIR__.'/includes/layout_top.php';
$labels=['need'=>'Necesitate','nice'=>'Ar fi bine','want'=>'Dorință'];
?>
<?php if($message):?><div class="alert success"><?=e($message)?></div><?php endif;?>
<?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<section class="section-head"><div><h2>Vrem să cumpărăm</h2><p class="section-copy">Dorințe, buget orientativ și progresul economiilor într-un singur loc.</p></div><button class="btn primary small" data-open-sheet="wishSheet"><?=icon('plus')?> Adaugă</button></section>
<div class="wish-grid">
<?php if(!$rows):?><div class="empty">Adaugă lucrurile mai mari pe care vreți să le cumpărați.</div><?php endif;?>
<?php foreach($rows as $r):
    $target=(float)($r['estimated_cost']??0);
    $saved=$r['savings_goal_id']?(float)$r['goal_saved']:$overallSaved;
    $pct=$target>0?min(100,(int)round($saved/$target*100)):0;
    $remaining=$target>0?max(0,$target-$saved):0;
    $dateLabel=$r['target_date']?date_ro($r['target_date']):'';
?>
<article class="wish-card <?=$r['purchased']?'is-done':''?>">
  <div class="wish-card__top"><span class="mini-icon purple"><?=icon('gift')?></span><div class="wish-card__title"><strong><?=e($r['name'])?></strong><span><?=e($labels[$r['priority']]??'Dorință')?><?=$r['creator']?' · adăugat de '.e($r['creator']):''?></span></div><b class="wish-card__price"><?=$target>0?money_ro($target):'Fără valoare'?></b></div>
  <?php if($dateLabel):?><div class="wish-target"><?=icon('calendar')?><span>Țintă: <b><?=e($dateLabel)?></b> · <?=e(short_due_label($r['target_date']))?></span></div><?php endif;?>
  <?php if($target>0):?>
  <div class="wish-progress"><div class="wish-progress__head"><span><?=$r['savings_goal_id']?'Economii · '.e($r['savings_goal_name']):'Economii totale'?></span><b><?=$pct?>%</b></div><div class="progress savings"><i style="width:<?=$pct?>%"></i></div><div class="wish-progress__foot"><span><?=money_ro($saved)?> strânși</span><span><?=$remaining>0?'Mai lipsesc '.money_ro($remaining):'Țintă atinsă 🎉'?></span></div></div>
  <?php endif;?>
  <?php if($r['notes']):?><p class="wish-notes"><?=nl2br(e($r['notes']))?></p><?php endif;?>
  <div class="wish-actions">
    <?php if($r['url']):?><a class="btn ghost small" href="<?=e($r['url'])?>" target="_blank" rel="noopener">Vezi link</a><?php endif;?>
    <button class="btn soft small" type="button" data-edit-wish data-id="<?=$r['id']?>" data-name="<?=e($r['name'])?>" data-cost="<?=e((string)$r['estimated_cost'])?>" data-date="<?=e($r['target_date']?date_ro($r['target_date']):'')?>" data-goal="<?=e((string)($r['savings_goal_id']??''))?>" data-priority="<?=e($r['priority'])?>" data-url="<?=e((string)$r['url'])?>" data-notes="<?=e((string)$r['notes'])?>"><?=icon('edit')?> Editează</button>
    <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn ghost small"><?=$r['purchased']?'Reia':'Cumpărat'?></button></form>
    <form method="post" onsubmit="return confirm('Ștergi această dorință?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn danger-outline small">Șterge</button></form>
  </div>
</article>
<?php endforeach;?>
</div>

<div class="modal" id="wishSheet" aria-hidden="true"><div class="modal-backdrop" data-close-sheet></div><section class="sheet"><div class="sheet-handle"></div><div class="sheet-head"><div><h2>Adaugă o dorință</h2><p>Poți lega dorința de un obiectiv de economii.</p></div><button class="icon-btn" data-close-sheet><?=icon('close')?></button></div><form method="post" class="form-stack"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="add"><label>Denumire<input name="name" required placeholder="Ex: Espressor"></label><label>Valoare estimată<div class="money-input"><input name="estimated_cost" inputmode="decimal" placeholder="2.500"><span>lei</span></div></label><label>Țintă până la data<input class="ro-date" name="target_date" inputmode="numeric" maxlength="10" placeholder="ZZ.LL.AAAA"></label><label>Leagă de economii<select name="savings_goal_id"><option value="">Economii totale</option><?php foreach($goals as $g):?><option value="<?=$g['id']?>"><?=e($g['name'])?> · <?=money_ro($g['saved'])?></option><?php endforeach;?></select></label><label>Prioritate<select name="priority"><option value="need">Necesitate</option><option value="nice">Ar fi bine</option><option value="want" selected>Dorință</option></select></label><label>Link<input type="url" name="url" placeholder="https://"></label><label>Notițe<textarea name="notes" rows="3"></textarea></label><button class="btn primary wide">Adaugă dorința</button></form></section></div>

<div class="modal" id="wishEditSheet" aria-hidden="true"><div class="modal-backdrop" data-close-sheet></div><section class="sheet"><div class="sheet-handle"></div><div class="sheet-head"><div><h2>Editează dorința</h2></div><button class="icon-btn" data-close-sheet><?=icon('close')?></button></div><form method="post" class="form-stack" id="wishEditForm"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id"><label>Denumire<input name="name" required></label><label>Valoare estimată<div class="money-input"><input name="estimated_cost" inputmode="decimal"><span>lei</span></div></label><label>Țintă până la data<input class="ro-date" name="target_date" inputmode="numeric" maxlength="10" placeholder="ZZ.LL.AAAA"></label><label>Leagă de economii<select name="savings_goal_id"><option value="">Economii totale</option><?php foreach($goals as $g):?><option value="<?=$g['id']?>"><?=e($g['name'])?> · <?=money_ro($g['saved'])?></option><?php endforeach;?></select></label><label>Prioritate<select name="priority"><option value="need">Necesitate</option><option value="nice">Ar fi bine</option><option value="want">Dorință</option></select></label><label>Link<input type="url" name="url"></label><label>Notițe<textarea name="notes" rows="3"></textarea></label><button class="btn primary wide">Salvează modificările</button></form></section></div>
<script>
document.querySelectorAll('[data-edit-wish]').forEach(btn=>btn.addEventListener('click',()=>{const modal=document.getElementById('wishEditSheet'),form=document.getElementById('wishEditForm');if(!modal||!form)return;form.elements.id.value=btn.dataset.id||'';form.elements.name.value=btn.dataset.name||'';form.elements.estimated_cost.value=(btn.dataset.cost||'').replace('.',',');form.elements.target_date.value=btn.dataset.date||'';form.elements.savings_goal_id.value=btn.dataset.goal||'';form.elements.priority.value=btn.dataset.priority||'want';form.elements.url.value=btn.dataset.url||'';form.elements.notes.value=btn.dataset.notes||'';modal.classList.add('open');modal.setAttribute('aria-hidden','false');document.body.classList.add('modal-open');}));
</script>
<?php require __DIR__.'/includes/layout_bottom.php';?>
