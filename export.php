<?php
require __DIR__.'/includes/bootstrap.php';require_login();
header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="buget-'.date('Y-m-d').'.csv"');
echo "\xEF\xBB\xBF";$o=fopen('php://output','w');fputcsv($o,['ID','Tip','Denumire','Suma','Categorie','Data','Adăugat de','Sursa','Clasa cost'],';');
foreach($pdo->query("SELECT t.*,m.name creator_name,COALESCE(tm.source,'manual') source,COALESCE(tm.bucket,CASE WHEN t.type='expense' THEN 'variable' ELSE 'income' END) bucket FROM transactions t LEFT JOIN transaction_meta tm ON tm.transaction_id=t.id LEFT JOIN family_members m ON m.id=t.created_by_member_id ORDER BY t.transaction_date DESC,t.id DESC") as $r){fputcsv($o,[$r['id'],$r['type'],$r['name'],$r['amount'],$r['category'],$r['transaction_date'],$r['creator_name']?:'Sistem',$r['source'],$r['bucket']],';');}
fclose($o);exit;
