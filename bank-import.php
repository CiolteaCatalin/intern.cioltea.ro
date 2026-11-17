<?php
require __DIR__.'/includes/bootstrap.php';require_login();require_member($pdo);
$summary=null;$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();$bank=strtolower((string)($_POST['bank']??''));if(!in_array($bank,['bt','bcr'],true))$bank='bt';
    try{
        if(empty($_FILES['statement'])||($_FILES['statement']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Alege un extras CSV sau XLSX.');
        $rows=bank_read_rows($_FILES['statement']['tmp_name'],$_FILES['statement']['name']);$items=bank_extract_transactions($rows,$bank);if(!$items)throw new RuntimeException('Nu am găsit tranzacții importabile în extras.');
        $added=0;$duplicates=0;$errors=0;
        foreach($items as $item){
            $check=$pdo->prepare('SELECT id FROM bank_import_rows WHERE bank=? AND fingerprint=? LIMIT 1');$check->execute([$bank,$item['fingerprint']]);if($check->fetchColumn()){$duplicates++;continue;}
            try{$pdo->beginTransaction();$ins=$pdo->prepare('INSERT INTO transactions(type,name,amount,category,transaction_date,created_by_member_id) VALUES(?,?,?,?,?,?)');$ins->execute([$item['type'],$item['description'],$item['amount'],$item['category'],$item['date'].' 12:00:00',current_member_id()]);$tx=(int)$pdo->lastInsertId();add_transaction_meta($pdo,$tx,'bank_'.$bank,$item['type']==='expense'?'variable':'income',null);$track=$pdo->prepare('INSERT INTO bank_import_rows(bank,fingerprint,transaction_id,booking_date,amount,description,reference_value,imported_by_member_id) VALUES(?,?,?,?,?,?,?,?)');$track->execute([$bank,$item['fingerprint'],$tx,$item['date'],$item['type']==='expense'?-1*$item['amount']:$item['amount'],$item['description'],$item['reference']?:null,current_member_id()]);$pdo->commit();$added++;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if((string)$e->getCode()==='23000')$duplicates++;else$errors++;}
        }
        log_activity($pdo,'bank_import','transaction',null,strtoupper($bank).' · '.$added.' tranzacții importate');$summary=['added'=>$added,'duplicates'=>$duplicates,'errors'=>$errors,'total'=>count($items),'bank'=>strtoupper($bank)];
    }catch(Throwable $e){$error=$e->getMessage();}
}
$pageTitle='Import extras';$active='money';$backUrl='/money.php';require __DIR__.'/includes/layout_top.php';
?>
<?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<?php if($summary):?><div class="import-result"><div class="import-result__icon"><?=icon('check')?></div><div><strong><?=$summary['added']?> tranzacții noi</strong><span><?=$summary['duplicates']?> duplicate ignorate<?php if($summary['errors']):?> · <?=$summary['errors']?> erori<?php endif;?> · <?=e($summary['bank'])?></span></div></div><?php endif;?>
<section class="import-card"><div class="import-card__head"><span class="hub-icon"><?=icon('upload')?></span><div><h2>Importă extrasul de cont</h2><p>Cioltea Hub verifică fiecare rând și nu importă din nou o tranzacție deja întâlnită.</p></div></div><form method="post" enctype="multipart/form-data" class="form-stack"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><label>Banca<div class="input-wrap"><?=icon('wallet')?><select name="bank"><option value="bt">Banca Transilvania (BT)</option><option value="bcr">BCR</option></select></div></label><label>Extras de cont<div class="file-drop"><input type="file" name="statement" accept=".csv,.txt,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required><span><?=icon('document')?><b>Alege CSV sau XLSX</b><small>Exportul original al băncii. Nu modifica anteturile.</small></span></div></label><button class="btn primary wide"><?=icon('upload')?> Importă tranzacțiile</button></form></section>
<section class="info-note"><span class="mini-icon blue"><?=icon('transactions')?></span><div><b>Deduplicare automată</b><p>Fiecare tranzacție primește o amprentă pe baza băncii, datei, sumei, descrierii și referinței. Reimportarea aceluiași extras nu dublează operațiunile.</p></div></section>
<?php require __DIR__.'/includes/layout_bottom.php';?>
