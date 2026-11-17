<?php
require __DIR__.'/includes/bootstrap.php';require_login();$saved=false;
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();foreach(category_options() as $cat){$key='limit_'.md5($cat);$amount=parse_amount_ro($_POST[$key]??'0');if($amount>0){$s=$pdo->prepare('INSERT INTO category_budgets(category,monthly_limit,active) VALUES(?,?,1) ON DUPLICATE KEY UPDATE monthly_limit=VALUES(monthly_limit),active=1');$s->execute([$cat,$amount]);}else{$s=$pdo->prepare('DELETE FROM category_budgets WHERE category=?');$s->execute([$cat]);}}$saved=true;}
$limits=[];foreach($pdo->query('SELECT * FROM category_budgets WHERE active=1') as $r)$limits[$r['category']]=(float)$r['monthly_limit'];$spend=category_spend($pdo,date('Y-m'));
$pageTitle='Bugete categorii';$active='money';require __DIR__.'/includes/layout_top.php';
?>
<?php if($saved):?><div class="alert success">Limitele au fost salvate.</div><?php endif;?>
<section class="plan-hero compact"><span class="eyebrow">Limite simple</span><h2>Controlează doar categoriile care contează</h2><p>Lasă 0 la categoriile pe care nu vrei să le urmărești. Pe dashboard vei fi atenționat când te apropii de limită.</p></section>
<form method="post" class="budget-category-list"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><?php foreach(category_options() as $cat):$limit=$limits[$cat]??0;$used=$spend[$cat]??0;$pct=$limit>0?min(100,round($used/$limit*100)):0;?><article class="category-budget-row"><div><strong><?=e($cat)?></strong><span><?=money_ro($used)?> cheltuiți luna asta</span><?php if($limit>0):?><div class="micro-progress"><i style="width:<?=$pct?>%"></i></div><?php endif;?></div><div class="limit-input"><input name="limit_<?=md5($cat)?>" inputmode="decimal" value="<?=$limit>0?e(number_format($limit,0,',','.')):''?>" placeholder="0"><span>lei</span></div></article><?php endforeach;?><button class="btn primary wide">Salvează limitele</button></form>
<?php require __DIR__.'/includes/layout_bottom.php'; ?>
