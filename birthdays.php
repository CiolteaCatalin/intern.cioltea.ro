<?php
require __DIR__.'/includes/bootstrap.php';
require_login();
require_member($pdo);

$memberId=current_member_id();
$message='';
$error='';

function birthday_date_from_parts($day,$month,$year=null): array {
    $day=(int)$day;
    $month=(int)$month;
    $year=(int)$year;

    if($day<1||$month<1||$month>12||!checkdate($month,$day,2000)){
        throw new RuntimeException('Completează o zi și o lună valide.');
    }

    $storedYear=($year>=1900&&$year<=2100)?$year:2000;
    if(!checkdate($month,$day,$storedYear)){
        throw new RuntimeException('Data nu este validă.');
    }

    return [
        sprintf('%04d-%02d-%02d',$storedYear,$month,$day),
        $storedYear===2000?null:$storedYear
    ];
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action=$_POST['action']??'';

    try{
        if(in_array($action,['add','update'],true)){
            $name=trim($_POST['name']??'');
            if($name===''){
                throw new RuntimeException('Completează numele.');
            }

            $relation=trim($_POST['relation_label']??'')?:null;
            [$date,$birthYear]=birthday_date_from_parts(
                $_POST['birth_day']??0,
                $_POST['birth_month']??0,
                $_POST['birth_year']??0
            );
            $notes=trim($_POST['notes']??'')?:null;

            if($action==='add'){
                $s=$pdo->prepare('INSERT INTO family_birthdays(name,relation_label,birth_date,birth_year,notes,created_by_member_id) VALUES(?,?,?,?,?,?)');
                $s->execute([$name,$relation,$date,$birthYear,$notes,$memberId]);
                $id=(int)$pdo->lastInsertId();
                log_activity($pdo,'birthday_added','birthday',$id,$name);
                $message='Ziua de naștere a fost adăugată.';
            }else{
                $id=(int)($_POST['id']??0);
                $s=$pdo->prepare('UPDATE family_birthdays SET name=?,relation_label=?,birth_date=?,birth_year=?,notes=? WHERE id=?');
                $s->execute([$name,$relation,$date,$birthYear,$notes,$id]);
                log_activity($pdo,'birthday_updated','birthday',$id,$name);
                $message='Ziua de naștere a fost actualizată.';
            }
        }elseif($action==='delete'){
            $id=(int)($_POST['id']??0);
            $q=$pdo->prepare('SELECT name FROM family_birthdays WHERE id=?');
            $q->execute([$id]);
            $name=(string)($q->fetchColumn()?:'Zi de naștere');
            $pdo->prepare('DELETE FROM family_birthdays WHERE id=?')->execute([$id]);
            log_activity($pdo,'birthday_deleted','birthday',$id,$name);
            $message='Ziua de naștere a fost ștearsă.';
        }
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

$months=[
    1=>'Ianuarie',
    2=>'Februarie',
    3=>'Martie',
    4=>'Aprilie',
    5=>'Mai',
    6=>'Iunie',
    7=>'Iulie',
    8=>'August',
    9=>'Septembrie',
    10=>'Octombrie',
    11=>'Noiembrie',
    12=>'Decembrie'
];

/*
 * Ordinea este calendaristică fixă: ianuarie -> decembrie,
 * iar în interiorul fiecărei luni: ziua -> numele.
 */
$rows=$pdo->query(
    'SELECT b.*,m.name creator
     FROM family_birthdays b
     LEFT JOIN family_members m ON m.id=b.created_by_member_id
     ORDER BY MONTH(b.birth_date),DAY(b.birth_date),b.name'
)->fetchAll();

$today=new DateTimeImmutable('today');
$currentMonth=(int)$today->format('n');
$grouped=[];
foreach($months as $monthNumber=>$monthLabel){
    $grouped[$monthNumber]=[];
}

foreach($rows as &$r){
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$r['birth_date']);
    $monthNumber=(int)$d->format('n');

    $candidate=DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $today->format('Y').'-'.$d->format('m-d')
    );
    if($candidate<$today){
        $candidate=$candidate->modify('+1 year');
    }

    $r['_next']=$candidate->format('Y-m-d');
    $r['_days']=(int)$today->diff($candidate)->format('%a');
    $r['_age']=birthday_age_on_next($r);
    $r['_day']=(int)$d->format('j');
    $r['_month']=$monthNumber;

    $grouped[$monthNumber][]=$r;
}
unset($r);

$pageTitle='Zile de naștere';
$active='more';
require __DIR__.'/includes/layout_top.php';
?>

<?php if($message):?>
    <div class="alert success"><?=e($message)?></div>
<?php endif;?>

<?php if($error):?>
    <div class="alert error"><?=e($error)?></div>
<?php endif;?>

<section class="hero-card birthday-hero">
    <div>
        <h2>Nu mai uitați aniversările</h2>
        <p>Este suficient să știți ziua și luna. Anul este opțional. Primiți notificare cu o zi înainte și în dimineața aniversării, la 07:00.</p>
    </div>
    <button class="btn primary small" data-open-sheet="birthdaySheet"><?=icon('plus')?> Adaugă</button>
</section>

<div class="birthday-calendar">
    <?php foreach($months as $monthNumber=>$monthLabel): ?>
        <?php
            $monthRows=$grouped[$monthNumber]??[];
            $isCurrentMonth=$monthNumber===$currentMonth;
        ?>

        <section class="birthday-month-section <?=$isCurrentMonth?'is-current-month':''?>">
            <div class="birthday-month-head">
                <div>
                    <span class="birthday-month-name"><?=e($monthLabel)?></span>
                    <?php if($isCurrentMonth):?>
                        <span class="birthday-current-badge">Luna curentă</span>
                    <?php endif;?>
                </div>
                <span class="birthday-month-count">
                    <?=count($monthRows)?> <?=count($monthRows)===1?'aniversare':'aniversări'?>
                </span>
            </div>

            <?php if(!$monthRows):?>
                <div class="birthday-month-empty">Nicio zi de naștere în această lună.</div>
            <?php else:?>
                <div class="birthday-list birthday-list--month">
                    <?php foreach($monthRows as $r):?>
                        <article class="birthday-card <?=($r['_days']<=7?'soon':'')?>">
                            <span class="birthday-date">
                                <b><?=e(str_pad((string)$r['_day'],2,'0',STR_PAD_LEFT))?></b>
                                <small><?=e(strtoupper(substr($monthLabel,0,3)))?></small>
                            </span>

                            <div class="birthday-main">
                                <strong><?=e($r['name'])?></strong>
                                <span>
                                    <?=$r['relation_label']?e($r['relation_label']).' · ':''?>
                                    <?=e($r['_days']===0?'astăzi':($r['_days']===1?'mâine':'în '.$r['_days'].' zile'))?>
                                    <?php if($r['_age']!==null):?>
                                        · împlinește <?=e((string)$r['_age'])?> ani
                                    <?php endif;?>
                                </span>
                                <?php if($r['notes']):?>
                                    <small><?=e($r['notes'])?></small>
                                <?php endif;?>
                            </div>

                            <div class="birthday-actions">
                                <button
                                    class="btn soft small"
                                    type="button"
                                    data-edit-birthday
                                    data-id="<?=$r['id']?>"
                                    data-name="<?=e($r['name'])?>"
                                    data-relation="<?=e((string)$r['relation_label'])?>"
                                    data-day="<?=e(date('d',strtotime($r['birth_date'])))?>"
                                    data-month="<?=e(date('m',strtotime($r['birth_date'])))?>"
                                    data-year="<?=e((string)($r['birth_year']??''))?>"
                                    data-notes="<?=e((string)$r['notes'])?>"
                                >
                                    <?=icon('edit')?> Editează
                                </button>

                                <form method="post" onsubmit="return confirm('Ștergi această zi de naștere?');">
                                    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?=$r['id']?>">
                                    <button class="btn ghost small">Șterge</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach;?>
                </div>
            <?php endif;?>
        </section>
    <?php endforeach;?>
</div>

<?php
function birthday_fields(array $months): void { ?>
<div class="birthday-date-fields">
    <label>Ziua<input type="number" name="birth_day" min="1" max="31" inputmode="numeric" required placeholder="14"></label>
    <label>Luna<select name="birth_month" required><option value="">Alege</option><?php foreach($months as $n=>$label):?><option value="<?=$n?>"><?=e($label)?></option><?php endforeach;?></select></label>
    <label>Anul <small class="field-note">opțional</small><input type="number" name="birth_year" min="1900" max="<?=date('Y')?>" inputmode="numeric" placeholder="Nu e necesar"></label>
</div>
<?php }
?>

<div class="modal" id="birthdaySheet" aria-hidden="true">
    <div class="modal-backdrop" data-close-sheet></div>
    <section class="sheet">
        <div class="sheet-handle"></div>
        <div class="sheet-head">
            <div><h2>Zi de naștere</h2></div>
            <button class="icon-btn" data-close-sheet><?=icon('close')?></button>
        </div>
        <form method="post" class="form-stack">
            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
            <input type="hidden" name="action" value="add">
            <label>Nume<input name="name" required placeholder="Ex: Mama"></label>
            <label>Relația<input name="relation_label" placeholder="Ex: Mamă, frate, prieten"></label>
            <?php birthday_fields($months);?>
            <label>Notițe<textarea name="notes" rows="3"></textarea></label>
            <button class="btn primary wide">Salvează</button>
        </form>
    </section>
</div>

<div class="modal" id="birthdayEditSheet" aria-hidden="true">
    <div class="modal-backdrop" data-close-sheet></div>
    <section class="sheet">
        <div class="sheet-handle"></div>
        <div class="sheet-head">
            <div><h2>Editează</h2></div>
            <button class="icon-btn" data-close-sheet><?=icon('close')?></button>
        </div>
        <form method="post" class="form-stack" id="birthdayEditForm">
            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id">
            <label>Nume<input name="name" required></label>
            <label>Relația<input name="relation_label"></label>
            <?php birthday_fields($months);?>
            <label>Notițe<textarea name="notes" rows="3"></textarea></label>
            <button class="btn primary wide">Salvează modificările</button>
        </form>
    </section>
</div>

<script>
document.querySelectorAll('[data-edit-birthday]').forEach(b=>b.addEventListener('click',()=>{
    const m=document.getElementById('birthdayEditSheet');
    const f=document.getElementById('birthdayEditForm');
    f.elements.id.value=b.dataset.id||'';
    f.elements.name.value=b.dataset.name||'';
    f.elements.relation_label.value=b.dataset.relation||'';
    f.elements.birth_day.value=b.dataset.day||'';
    f.elements.birth_month.value=String(parseInt(b.dataset.month||'0',10)||'');
    f.elements.birth_year.value=b.dataset.year||'';
    f.elements.notes.value=b.dataset.notes||'';
    m.classList.add('open');
    m.setAttribute('aria-hidden','false');
    document.body.classList.add('modal-open');
}));
</script>

<?php require __DIR__.'/includes/layout_bottom.php';?>
