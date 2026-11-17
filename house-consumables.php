<?php
require __DIR__.'/includes/bootstrap.php';require_login();require_member($pdo);$message='';$error='';$memberId=current_member_id();
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();try{$a=$_POST['action']??'';$id=(int)($_POST['id']??0);
 if($a==='add'||$a==='update'){$name=trim($_POST['name']??'');if(!$name)throw new RuntimeException('Completează denumirea.');$qty=parse_amount_ro($_POST['quantity']??'0');$min=parse_amount_ro($_POST['minimum_qty']??'0');$unit=trim($_POST['unit']??'buc')?:'buc';$loc=trim($_POST['location']??'')?:null;$notes=trim($_POST['notes']??'')?:null;if($a==='add'){$pdo->prepare('INSERT INTO house_consumables(name,quantity,unit,minimum_qty,location,notes,created_by_member_id) VALUES(?,?,?,?,?,?,?)')->execute([$name,$qty,$unit,$min,$loc,$notes,$memberId]);}else{if($id<1)throw new RuntimeException('Consumabil invalid.');$pdo->prepare('UPDATE house_consumables SET name=?,quantity=?,unit=?,minimum_qty=?,location=?,notes=? WHERE id=?')->execute([$name,$qty,$unit,$min,$loc,$notes,$id]);}$message='Consumabilul a fost salvat.';}
 if($a==='adjust'){$delta=parse_amount_ro($_POST['delta']??'0');if(($_POST['direction']??'minus')==='minus')$delta=-$delta;$pdo->prepare('UPDATE house_consumables SET quantity=GREATEST(0,quantity+?) WHERE id=?')->execute([$delta,$id]);$message='Stoc actualizat.';}
 if($a==='shopping'){$q=$pdo->prepare('SELECT name FROM house_consumables WHERE id=?');$q->execute([$id]);$name=$q->fetchColumn();if($name){$listId=shopping_list_id($pdo);$pdo->prepare('INSERT INTO family_list_items(list_id,name,created_by_member_id) VALUES(?,?,?)')->execute([$listId,$name,$memberId]);$message='Adăugat în lista de cumpărături.';}}
 if($a==='delete'){if($id<1)throw new RuntimeException('Consumabil invalid.');$pdo->prepare('DELETE FROM house_consumables WHERE id=?')->execute([$id]);$message='Consumabil șters.';}
}catch(Throwable $e){$error=$e->getMessage();}}
$items=$pdo->query('SELECT * FROM house_consumables ORDER BY (quantity<=minimum_qty) DESC,name')->fetchAll();$pageTitle='Consumabile casă';$active='more';$backUrl='/home.php';require __DIR__.'/includes/layout_top.php';?>
<?php if($message):?><div class="alert success"><?=e($message)?></div><?php endif;?><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<section class="hero-card subtle"><div><h2>Consumabile</h2><p>Sare dedurizator, filtre, becuri, baterii și alte lucruri de ținut în stoc.</p></div><button class="btn primary small" type="button" data-consumable-add><?=icon('plus')?> Adaugă</button></section>
<div class="consumable-grid">
<?php foreach($items as $r):$low=(float)$r['quantity']<=(float)$r['minimum_qty'];?>
<article class="consumable-card <?=$low?'is-low':''?>">
  <div><strong><?=e($r['name'])?></strong><span><?=e($r['location']?:'Casă')?></span></div>
  <div class="consumable-qty"><b><?=rtrim(rtrim(number_format((float)$r['quantity'],2,',','.'),'0'),',')?> <?=e($r['unit'])?></b><?php if($low):?><small>Stoc mic</small><?php endif;?></div>
  <div class="row-actions consumable-stock-actions">
    <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="adjust"><input type="hidden" name="id" value="<?=$r['id']?>"><input type="hidden" name="delta" value="1"><input type="hidden" name="direction" value="minus"><button class="btn ghost small" type="submit">−1</button></form>
    <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="adjust"><input type="hidden" name="id" value="<?=$r['id']?>"><input type="hidden" name="delta" value="1"><input type="hidden" name="direction" value="plus"><button class="btn soft small" type="submit">+1</button></form>
    <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="shopping"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn primary small" type="submit"><?=icon('cart')?> Cumpărături</button></form>
  </div>
  <div class="consumable-manage-actions">
    <button class="btn ghost small" type="button" data-consumable-edit data-id="<?=$r['id']?>" data-name="<?=e($r['name'])?>" data-quantity="<?=e((string)$r['quantity'])?>" data-unit="<?=e($r['unit'])?>" data-minimum="<?=e((string)$r['minimum_qty'])?>" data-location="<?=e((string)($r['location']??''))?>" data-notes="<?=e((string)($r['notes']??''))?>"><?=icon('edit')?> Editează</button>
    <form method="post" onsubmit="return confirm('Ștergi acest consumabil?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn ghost small danger" type="submit"><?=icon('trash')?> Șterge</button></form>
  </div>
</article>
<?php endforeach;?><?php if(!$items):?><div class="empty">Nu ai consumabile salvate.</div><?php endif;?></div>

<div class="modal" id="consSheet" aria-hidden="true"><div class="modal-backdrop" data-close-sheet></div><section class="sheet"><div class="sheet-handle"></div><div class="sheet-head"><h2 id="consSheetTitle">Consumabil nou</h2><button class="icon-btn" type="button" data-close-sheet><?=icon('close')?></button></div><form method="post" class="form-stack" id="consForm"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="add"><input type="hidden" name="id" value=""><label>Denumire<input name="name" required placeholder="Ex: Sare dedurizator"></label><div class="two-col"><label>Cantitate<input name="quantity" inputmode="decimal" value="1"></label><label>Unitate<input name="unit" value="buc"></label></div><label>Prag minim<input name="minimum_qty" inputmode="decimal" value="0"></label><label>Locație<input name="location" placeholder="Garaj"></label><label>Notițe<textarea name="notes" rows="3"></textarea></label><button class="btn primary wide" id="consSubmit" type="submit">Salvează</button></form></section></div>
<script>
(()=>{
  const modal=document.getElementById('consSheet');
  const form=document.getElementById('consForm');
  const title=document.getElementById('consSheetTitle');
  const submit=document.getElementById('consSubmit');
  if(!modal||!form)return;
  const open=()=>{modal.classList.add('open');modal.setAttribute('aria-hidden','false');document.body.classList.add('modal-open');setTimeout(()=>form.elements.name?.focus(),120)};
  const setAdd=()=>{form.reset();form.elements.action.value='add';form.elements.id.value='';form.elements.quantity.value='1';form.elements.unit.value='buc';form.elements.minimum_qty.value='0';title.textContent='Consumabil nou';submit.textContent='Salvează';open();};
  document.querySelector('[data-consumable-add]')?.addEventListener('click',setAdd);
  document.querySelectorAll('[data-consumable-edit]').forEach(btn=>btn.addEventListener('click',()=>{
    form.reset();
    form.elements.action.value='update';
    form.elements.id.value=btn.dataset.id||'';
    form.elements.name.value=btn.dataset.name||'';
    form.elements.quantity.value=(btn.dataset.quantity||'0').replace('.',',');
    form.elements.unit.value=btn.dataset.unit||'buc';
    form.elements.minimum_qty.value=(btn.dataset.minimum||'0').replace('.',',');
    form.elements.location.value=btn.dataset.location||'';
    form.elements.notes.value=btn.dataset.notes||'';
    title.textContent='Editează consumabil';
    submit.textContent='Salvează modificările';
    open();
  }));
})();
</script>
<?php require __DIR__.'/includes/layout_bottom.php';?>
