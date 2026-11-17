<?php
require __DIR__.'/includes/bootstrap.php';require_login();require_member($pdo);
if(!digistorage_configured($config)){http_response_code(404);exit;}
$path=digistorage_relative_path((string)($_GET['path']??''),false);$version=preg_replace('/[^0-9A-Za-z_-]/','',(string)($_GET['v']??''));if($path===''){http_response_code(404);exit;}
$cacheDir=__DIR__.'/storage/thumbs';if(!is_dir($cacheDir))@mkdir($cacheDir,0700,true);
// Curățare probabilistică: cache local 180 zile de la ultima folosire, fără cron separat.
if(random_int(1,100)===1){$cut=time()-180*86400;foreach(glob($cacheDir.'/*.jpg')?:[] as $f){if(@filemtime($f)<$cut)@unlink($f);}}
$key=sha1($path.'|'.$version);$thumb=$cacheDir.'/'.$key.'.jpg';
$serve=function(string $file):void{@touch($file);header('Content-Type: image/jpeg');header('Cache-Control: private, max-age=2592000');header('Content-Length: '.filesize($file));readfile($file);exit;};
if(is_file($thumb)&&filesize($thumb)>100)$serve($thumb);
// Evităm blocarea celorlalte request-uri ale utilizatorului cât așteptăm Digi.
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
try{$url=digistorage_download_link($config,$path,false);}catch(Throwable $e){http_response_code(404);exit;}
$tmp=tempnam(sys_get_temp_dir(),'chthumb_');$fp=fopen($tmp,'wb');$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_FILE=>$fp,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>18,CURLOPT_USERAGENT=>'CiolteaHub/23']);$ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);fclose($fp);
if(!$ok||$status<200||$status>=300||!is_file($tmp)){@unlink($tmp);header('Content-Type: image/svg+xml; charset=utf-8');header('Cache-Control: private, max-age=300');echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 480 480"><rect width="480" height="480" fill="%23eef2f7"/><path d="M160 310l70-85 45 55 30-34 55 64H160z" fill="%23c4ccda"/><circle cx="184" cy="168" r="24" fill="%23c4ccda"/></svg>';exit;}
$made=false;try{if(class_exists('Imagick')){$im=new Imagick($tmp);if(method_exists($im,'autoOrient'))$im->autoOrient();$im->setImageColorspace(Imagick::COLORSPACE_SRGB);$im->thumbnailImage(420,420,true,true);$im->setImageFormat('jpeg');$im->setImageCompressionQuality(70);$im->stripImage();$made=$im->writeImage($thumb);$im->clear();$im->destroy();}elseif(function_exists('imagecreatefromstring')){$data=@file_get_contents($tmp);$src=$data!==false?@imagecreatefromstring($data):false;if($src){$w=imagesx($src);$h=imagesy($src);$scale=min(1,420/max($w,$h));$nw=max(1,(int)round($w*$scale));$nh=max(1,(int)round($h*$scale));$dst=imagecreatetruecolor($nw,$nh);$white=imagecolorallocate($dst,255,255,255);imagefill($dst,0,0,$white);imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h);$made=imagejpeg($dst,$thumb,70);imagedestroy($dst);imagedestroy($src);}}}catch(Throwable $e){$made=false;}@unlink($tmp);if($made&&is_file($thumb))$serve($thumb);http_response_code(404);
