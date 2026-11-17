<?php
require __DIR__.'/includes/bootstrap.php';
require_login();require_member($pdo);
$memberId=current_member_id();$members=family_members($pdo);$message='';$error='';
$year=max(2020,min(2100,(int)($_GET['year']??date('Y'))));
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();$action=(string)($_POST['action']??'');
    try{
        if(in_array($action,['add','update'],true)){
            $title=trim((string)($_POST['title']??''));$date=parse_ro_date((string)($_POST['event_date']??''));
            if($title===''||!$date)throw new RuntimeException('Completează titlul și data evenimentului.');
            $time=trim((string)($_POST['event_time']??''))?:null;$category=trim((string)($_POST['category']??'Familie'))?:'Familie';
            $scope=(($_POST['member_scope']??'all')==='member')?'member':'all';$who=$scope==='member'?((int)($_POST['member_id']??0)?:null):null;
            $location=trim((string)($_POST['location']??''))?:null;$notes=trim((string)($_POST['notes']??''))?:null;$remind=max(0,min(60,(int)($_POST['remind_days']??1)));
            if($action==='add'){
                $s=$pdo->prepare('INSERT INTO family_events(title,category,event_date,event_time,member_scope,member_id,location,notes,remind_days,created_by_member_id) VALUES(?,?,?,?,?,?,?,?,?,?)');
                $s->execute([$title,$category,$date,$time,$scope,$who,$location,$notes,$remind,$memberId]);$id=(int)$pdo->lastInsertId();log_activity($pdo,'event_added','event',$id,$title);$message='Evenimentul a fost adăugat.';
            }else{
                $id=(int)($_POST['id']??0);$s=$pdo->prepare('UPDATE family_events SET title=?,category=?,event_date=?,event_time=?,member_scope=?,member_id=?,location=?,notes=?,remind_days=? WHERE id=?');
                $s->execute([$title,$category,$date,$time,$scope,$who,$location,$notes,$remind,$id]);log_activity($pdo,'event_updated','event',$id,$title);$message='Evenimentul a fost actualizat.';
            }
            $year=(int)substr($date,0,4);
        }elseif($action==='delete'){
            $id=(int)($_POST['id']??0);$q=$pdo->prepare('SELECT title FROM family_events WHERE id=?');$q->execute([$id]);$title=(string)($q->fetchColumn()?:'Eveniment');$pdo->prepare('DELETE FROM family_events WHERE id=?')->execute([$id]);log_activity($pdo,'event_deleted','event',$id,$title);$message='Evenimentul a fost șters.';
        }
    }catch(Throwable $e){$error=$e->getMessage();}
}
$q=$pdo->prepare("SELECT e.*,m.name member_name,c.name creator FROM family_events e LEFT JOIN family_members m ON m.id=e.member_id LEFT JOIN family_members c ON c.id=e.created_by_member_id WHERE YEAR(e.event_date)=? ORDER BY e.event_date,e.event_time,e.id");$q->execute([$year]);$events=$q->fetchAll();
$today=date('Y-m-d');$upcoming=array_values(array_filter($events,fn($e)=>$e['event_date']>=$today));
$pageTitle='Evenimente';$active='more';require __DIR__.'/includes/layout_top.php';
?>
<?php if($message):?><div class="alert success"><?=e($message)?></div><?php endif;?><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<section class="hero-card events-hero"><div><h2>Calendarul familiei</h2><p>Evenimente, programări și planuri importante pentru anul <?=e((string)$year)?>.</p></div><button class="btn primary small" data-open-sheet="eventStandaloneSheet"><?=icon('plus')?> Adaugă</button></section>
<div class="events-year-nav"><a class="icon-btn" href="/events.php?year=<?=$year-1?>" aria-label="Anul anterior"><?=icon('back')?></a><strong><?=$year?></strong><a class="icon-btn" href="/events.php?year=<?=$year+1?>" aria-label="Anul următor" style="transform:rotate(180deg)"><?=icon('back')?></a></div>
<div class="events-list"><?php if(!$events):?><div class="empty-card">Nu aveți evenimente salvate pentru <?=$year?>.</div><?php endif;?><?php foreach($events as $e):$past=$e['event_date']<$today;?><article class="event-card <?=$past?'past':''?>"><span class="event-date"><b><?=e(date('d',strtotime($e['event_date'])))?></b><small><?=e(strtoupper(substr(month_label_ro(date('Y-m',strtotime($e['event_date']))),0,3)))?></small></span><div class="event-main"><strong><?=e($e['title'])?></strong><span><?=e($e['category'])?><?=$e['event_time']?' · '.e(substr($e['event_time'],0,5)):''?><?=$e['location']?' · '.e($e['location']):''?></span><small><?=e($e['member_scope']==='all'?'Amândoi':($e['member_name']?:'Membru'))?> · adăugat de <?=e($e['creator']?:'Sistem')?></small><?php if($e['notes']):?><p><?=e($e['notes'])?></p><?php endif;?></div><div class="event-actions"><button class="icon-btn" type="button" data-edit-event data-id="<?=$e['id']?>" data-title="<?=e($e['title'])?>" data-category="<?=e($e['category'])?>" data-date="<?=e(date_ro($e['event_date']))?>" data-time="<?=e((string)substr((string)$e['event_time'],0,5))?>" data-scope="<?=e($e['member_scope'])?>" data-member="<?=e((string)$e['member_id'])?>" data-location="<?=e((string)$e['location'])?>" data-notes="<?=e((string)$e['notes'])?>" data-remind="<?=e((string)$e['remind_days'])?>" aria-label="Editează"><?=icon('edit')?></button><form method="post" onsubmit="return confirm('Ștergi acest eveniment?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$e['id']?>"><button class="icon-btn danger" aria-label="Șterge"><?=icon('trash')?></button></form></div></article><?php endforeach;?></div>

<?php function event_form_fields(array $members): void { ?>
<label>Titlu<input name="title" required placeholder="Ex: Nuntă, concert, dentist"></label><div class="form-grid"><label>Data<input class="ro-date" name="event_date" required inputmode="numeric" maxlength="10" value="<?=date('d.m.Y')?>"></label><label>Ora<input type="time" name="event_time"></label></div><label>Categorie<select name="category"><option>Familie</option><option>Medical</option><option>Eveniment</option><option>Vacanță</option><option>Auto</option><option>Casă</option><option>Altele</option></select></label><label>Pentru<select name="member_scope" data-scope-select><option value="all">Amândoi</option><option value="member">Un singur profil</option></select></label><label data-member-target hidden>Profil<select name="member_id"><?php foreach($members as $m):?><option value="<?=$m['id']?>"><?=e($m['name'])?></option><?php endforeach;?></select></label><label>Locație<input name="location" placeholder="Opțional"></label><label>Notifică înainte<select name="remind_days"><option value="0">În ziua evenimentului</option><option value="1" selected>1 zi</option><option value="3">3 zile</option><option value="7">7 zile</option><option value="14">14 zile</option><option value="30">30 zile</option></select></label><label>Notițe<textarea name="notes" rows="3"></textarea></label>
<?php } ?>
<div class="modal" id="eventStandaloneSheet" aria-hidden="true"><div class="modal-backdrop" data-close-sheet></div><section class="sheet"><div class="sheet-handle"></div><div class="sheet-head"><h2>Eveniment nou</h2><button class="icon-btn" data-close-sheet><?=icon('close')?></button></div><form method="post" class="form-stack"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="add"><?php event_form_fields($members);?><button class="btn primary wide">Salvează evenimentul</button></form></section></div>
<div class="modal" id="eventEditSheet" aria-hidden="true"><div class="modal-backdrop" data-close-sheet></div><section class="sheet"><div class="sheet-handle"></div><div class="sheet-head"><h2>Editează evenimentul</h2><button class="icon-btn" data-close-sheet><?=icon('close')?></button></div><form method="post" class="form-stack" id="eventEditForm"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id"><?php event_form_fields($members);?><button class="btn primary wide">Salvează modificările</button></form></section></div>
<script>
const syncEventScope=f=>{const s=f.querySelector('[name="member_scope"]'),t=f.querySelector('[data-member-target]');if(s&&t)t.hidden=s.value!=='member';};document.querySelectorAll('[name="member_scope"]').forEach(s=>{s.addEventListener('change',()=>syncEventScope(s.form));syncEventScope(s.form);});
document.querySelectorAll('[data-edit-event]').forEach(b=>b.addEventListener('click',()=>{const m=document.getElementById('eventEditSheet'),f=document.getElementById('eventEditForm');['id','title','category','event_date','event_time','member_scope','member_id','location','notes','remind_days'].forEach(n=>{const el=f.elements[n];if(!el)return;const key={event_date:'date',event_time:'time',member_scope:'scope',member_id:'member',remind_days:'remind'}[n]||n;el.value=b.dataset[key]||'';});syncEventScope(f);m.classList.add('open');m.setAttribute('aria-hidden','false');document.body.classList.add('modal-open');}));
</script>
<?php require __DIR__.'/includes/layout_bottom.php';?>
