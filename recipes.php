<?php
require __DIR__.'/includes/bootstrap.php';require_login();require_member($pdo);
$memberId=current_member_id();$message='';$error='';
function recipe_store_image(array $file, ?string $oldPath=null): array {
    if(empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name']))return [$oldPath,null,null];
    if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK)throw new RuntimeException('Poza nu a putut fi încărcată.');
    if(($file['size']??0)>15*1024*1024)throw new RuntimeException('Poza este prea mare. Maxim 15 MB.');
    $finfo=new finfo(FILEINFO_MIME_TYPE);$mime=(string)$finfo->file($file['tmp_name']);
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/heic'=>'heic','image/heif'=>'heif'];
    if(!isset($allowed[$mime]))throw new RuntimeException('Format imagine nesuportat. Folosește JPG, PNG, WEBP sau HEIC.');
    $dir=__DIR__.'/storage/recipes';if(!is_dir($dir)&&!@mkdir($dir,0700,true))throw new RuntimeException('Nu pot crea folderul pentru poze.');
    $base=bin2hex(random_bytes(16));$path='storage/recipes/'.$base.'.jpg';$abs=__DIR__.'/'.$path;$made=false;
    try{
        if(class_exists('Imagick')){$im=new Imagick($file['tmp_name']);if(method_exists($im,'autoOrient'))$im->autoOrient();elseif(method_exists($im,'autoOrientImage'))$im->autoOrientImage();if($im->getNumberImages()>1)$im->setIteratorIndex(0);$im->setImageColorspace(Imagick::COLORSPACE_SRGB);$im->thumbnailImage(1600,1600,true,true);$im->setImageFormat('jpeg');$im->setImageCompressionQuality(82);$im->stripImage();$made=$im->writeImage($abs);$im->clear();$im->destroy();}
        elseif(function_exists('imagecreatefromstring')){$data=@file_get_contents($file['tmp_name']);$src=$data!==false?@imagecreatefromstring($data):false;if($src){$w=imagesx($src);$h=imagesy($src);$scale=min(1,1600/max($w,$h));$nw=max(1,(int)round($w*$scale));$nh=max(1,(int)round($h*$scale));$dst=imagecreatetruecolor($nw,$nh);$white=imagecolorallocate($dst,255,255,255);imagefill($dst,0,0,$white);imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h);$made=imagejpeg($dst,$abs,82);imagedestroy($dst);imagedestroy($src);}}
    }catch(Throwable $e){$made=false;}
    if(!$made){$ext=$allowed[$mime];$path='storage/recipes/'.$base.'.'.$ext;$abs=__DIR__.'/'.$path;if(!move_uploaded_file($file['tmp_name'],$abs))throw new RuntimeException('Poza nu a putut fi salvată.');}
    if($oldPath && str_starts_with($oldPath,'storage/recipes/') && is_file(__DIR__.'/'.$oldPath))@unlink(__DIR__.'/'.$oldPath);
    return [$path,$mime,(string)($file['name']??'poza')];
}
function recipe_ingredients_from_text(string $raw): array { $out=[];foreach(preg_split('/\R/u',$raw) as $line){$line=trim($line);if($line!=='')$out[]=$line;}return array_slice($out,0,100); }
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();$action=$_POST['action']??'';
    try{
        if(in_array($action,['add','update'],true)){
            $title=trim($_POST['title']??'');if($title==='')throw new RuntimeException('Completează numele rețetei.');
            $description=null;$preparation=trim($_POST['preparation']??'');if($preparation==='')throw new RuntimeException('Completează modul de preparare.');
            $ingredients=recipe_ingredients_from_text((string)($_POST['ingredients']??''));if(!$ingredients)throw new RuntimeException('Adaugă cel puțin un ingredient.');
            if($action==='add'){
                [$imagePath,$imageMime,$imageName]=recipe_store_image($_FILES['image']??[]);
                $s=$pdo->prepare('INSERT INTO family_recipes(title,description,preparation,image_path,image_mime,image_name,created_by_member_id) VALUES(?,?,?,?,?,?,?)');$s->execute([$title,$description,$preparation,$imagePath,$imageMime,$imageName,$memberId]);$id=(int)$pdo->lastInsertId();
            } else {
                $id=(int)($_POST['id']??0);$q=$pdo->prepare('SELECT * FROM family_recipes WHERE id=?');$q->execute([$id]);$old=$q->fetch();if(!$old)throw new RuntimeException('Rețeta nu există.');
                $imagePath=$old['image_path'];$imageMime=$old['image_mime'];$imageName=$old['image_name'];
                if(!empty($_FILES['image']['tmp_name']))[$imagePath,$imageMime,$imageName]=recipe_store_image($_FILES['image'],$old['image_path']);
                $s=$pdo->prepare('UPDATE family_recipes SET title=?,description=?,preparation=?,image_path=?,image_mime=?,image_name=? WHERE id=?');$s->execute([$title,$description,$preparation,$imagePath,$imageMime,$imageName,$id]);
                $pdo->prepare('DELETE FROM family_recipe_ingredients WHERE recipe_id=?')->execute([$id]);
            }
            $ins=$pdo->prepare('INSERT INTO family_recipe_ingredients(recipe_id,ingredient_text,sort_order) VALUES(?,?,?)');foreach($ingredients as $i=>$line)$ins->execute([$id,$line,$i]);
            log_activity($pdo,$action==='add'?'recipe_added':'recipe_updated','recipe',$id,$title);header('Location: /recipe.php?id='.$id);exit;
        }
        if($action==='delete'){
            $id=(int)($_POST['id']??0);$q=$pdo->prepare('SELECT title,image_path FROM family_recipes WHERE id=?');$q->execute([$id]);$r=$q->fetch();if($r){if($r['image_path']&&str_starts_with($r['image_path'],'storage/recipes/')&&is_file(__DIR__.'/'.$r['image_path']))@unlink(__DIR__.'/'.$r['image_path']);$pdo->prepare('DELETE FROM family_recipes WHERE id=?')->execute([$id]);log_activity($pdo,'recipe_deleted','recipe',$id,$r['title']);}$message='Rețeta a fost ștearsă.';
        }
    }catch(Throwable $e){$error=$e->getMessage();}
}
$recipes=$pdo->query("SELECT r.*,m.name creator,(SELECT COUNT(*) FROM family_recipe_ingredients i WHERE i.recipe_id=r.id) ingredient_count FROM family_recipes r LEFT JOIN family_members m ON m.id=r.created_by_member_id ORDER BY r.updated_at DESC,r.id DESC")->fetchAll();
$edit=null;$editIngredients='';if(isset($_GET['edit'])){$q=$pdo->prepare('SELECT * FROM family_recipes WHERE id=?');$q->execute([(int)$_GET['edit']]);$edit=$q->fetch()?:null;if($edit){$q=$pdo->prepare('SELECT ingredient_text FROM family_recipe_ingredients WHERE recipe_id=? ORDER BY sort_order,id');$q->execute([$edit['id']]);$editIngredients=implode("\n",array_column($q->fetchAll(),'ingredient_text'));}}
$pageTitle='Rețete';$active='more';require __DIR__.'/includes/layout_top.php';
?>
<?php if($message):?><div class="alert success"><?=e($message)?></div><?php endif;?><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<section class="hero-card recipe-hero"><div><h2>Rețetele voastre</h2><p>Salvați ce vă place, cu ingrediente, fotografie și pașii de preparare.</p></div><button class="btn primary small" data-open-sheet="recipeSheet"><?=icon('plus')?> Adaugă</button></section>
<?php if($edit):?>
<section class="settings-card recipe-edit-card"><div class="section-head compact"><div><h2>Editează <?=e($edit['title'])?></h2></div><a class="btn ghost small" href="/recipes.php">Închide</a></div><form method="post" enctype="multipart/form-data" class="form-stack"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?=$edit['id']?>"><label>Nume<input name="title" value="<?=e($edit['title'])?>" required></label><label>Ingrediente <small class="field-note">Câte unul pe rând, exact cum vrei să apară în lista de cumpărături.</small><textarea name="ingredients" rows="8" required><?=e($editIngredients)?></textarea></label><label>Mod de preparare<textarea name="preparation" rows="10" required><?=e($edit['preparation'])?></textarea></label><label>Schimbă poza<input type="file" name="image" accept="image/*"></label><button class="btn primary wide">Salvează modificările</button></form></section>
<?php endif;?>
<section><div class="section-head"><div><h2>Toate rețetele</h2></div></div><div class="recipe-grid"><?php if(!$recipes):?><div class="empty">Nu aveți încă rețete salvate.</div><?php endif;?><?php foreach($recipes as $r):?><article class="recipe-card"><a class="recipe-card__media" href="/recipe.php?id=<?=$r['id']?>"><?php if($r['image_path']):?><img src="/recipe-media.php?id=<?=$r['id']?>" loading="lazy" decoding="async" alt=""><?php else:?><span><?=icon('food')?></span><?php endif;?></a><div class="recipe-card__body"><a href="/recipe.php?id=<?=$r['id']?>"><h3><?=e($r['title'])?></h3></a><small><?=$r['ingredient_count']?> ingrediente<?=$r['creator']?' · '.e($r['creator']):''?></small><div class="recipe-card__actions"><a class="btn soft small" href="/recipes.php?edit=<?=$r['id']?>"><?=icon('edit')?> Editează</a><form method="post" onsubmit="return confirm('Ștergi această rețetă?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn ghost small">Șterge</button></form></div></div></article><?php endforeach;?></div></section>
<div class="modal" id="recipeSheet" aria-hidden="true"><div class="modal-backdrop" data-close-sheet></div><section class="sheet"><div class="sheet-handle"></div><div class="sheet-head"><div><h2>Rețetă nouă</h2></div><button class="icon-btn" data-close-sheet><?=icon('close')?></button></div><form method="post" enctype="multipart/form-data" class="form-stack"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="add"><label>Nume<input name="title" required placeholder="Ex: Paste carbonara"></label><label>Poza preparatului<input type="file" name="image" accept="image/*"></label><label>Ingrediente <small class="field-note">Câte unul pe rând.</small><textarea name="ingredients" rows="8" required placeholder="400 g paste&#10;4 ouă&#10;150 g parmezan"></textarea></label><label>Mod de preparare<textarea name="preparation" rows="9" required placeholder="Scrie pașii de preparare..."></textarea></label><button class="btn primary wide">Salvează rețeta</button></form></section></div>
<?php require __DIR__.'/includes/layout_bottom.php';?>
