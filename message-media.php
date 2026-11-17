<?php
require __DIR__.'/includes/bootstrap.php';require_login();require_member($pdo);
$id=(int)($_GET['id']??0);if($id<1){http_response_code(404);exit;}
$q=$pdo->prepare('SELECT image_path,image_mime,image_name FROM family_messages WHERE id=?');$q->execute([$id]);$r=$q->fetch();
if(!$r||empty($r['image_path'])){http_response_code(404);exit;}
$base=realpath(__DIR__.'/storage/messages');$file=realpath(__DIR__.'/'.$r['image_path']);
if(!$base||!$file||!str_starts_with($file,$base.DIRECTORY_SEPARATOR)||!is_file($file)){http_response_code(404);exit;}
header('Content-Type: '.($r['image_mime']?:'application/octet-stream'));header('Content-Length: '.filesize($file));header('Cache-Control: private, max-age=86400');header('Content-Disposition: inline; filename="'.rawurlencode($r['image_name']?:basename($file)).'"');readfile($file);
