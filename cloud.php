<?php
require __DIR__.'/includes/bootstrap.php';
require_login();
require_member($pdo);

$member = current_member($pdo);
$configured = digistorage_configured($config);
$current = isset($_GET['path']) ? digistorage_relative_path((string)$_GET['path'], true) : '';
$error = '';
$notice = '';
$share = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $current = digistorage_relative_path((string)($_POST['current_path'] ?? $current), true);
    try {
        if (!$configured) throw new RuntimeException('Digi Storage nu este configurat încă.');
        if ($action === 'create_folder') {
            digistorage_create_folder($config, $current, (string)($_POST['folder_name'] ?? ''));
            $notice = 'Folderul a fost creat.';
        } elseif ($action === 'upload') {
            $uploaded = 0;
            if (!empty($_FILES['files']['name']) && is_array($_FILES['files']['name'])) {
                foreach ($_FILES['files']['name'] as $i => $name) {
                    if ((int)($_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                    digistorage_upload_file($config, $current, [
                        'name' => $name,
                        'type' => $_FILES['files']['type'][$i] ?? 'application/octet-stream',
                        'tmp_name' => $_FILES['files']['tmp_name'][$i] ?? '',
                        'error' => $_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                        'size' => $_FILES['files']['size'][$i] ?? 0,
                    ]);
                    $uploaded++;
                }
            }
            $notice = $uploaded === 1 ? 'Poza/fișierul a fost încărcat.' : ($uploaded . ' fișiere au fost încărcate.');
        } elseif ($action === 'rename') {
            $target = digistorage_relative_path((string)($_POST['target'] ?? ''), !empty($_POST['is_dir']));
            digistorage_rename($config, $target, !empty($_POST['is_dir']), (string)($_POST['new_name'] ?? ''));
            $notice = 'Elementul a fost redenumit.';
        } elseif ($action === 'delete') {
            $target = digistorage_relative_path((string)($_POST['target'] ?? ''), !empty($_POST['is_dir']));
            digistorage_remove($config, $target, !empty($_POST['is_dir']));
            $notice = 'Elementul a fost șters.';
        } elseif ($action === 'share') {
            $target = digistorage_relative_path((string)($_POST['target'] ?? $current), !empty($_POST['is_dir']));
            $share = digistorage_share_link($config, $target, !empty($_POST['is_dir']));
            $notice = 'Linkul de partajare este pregătit.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$files = [];
if ($configured && $error === '') {
    try { $files = digistorage_list($config, $current); }
    catch (Throwable $e) { $error = $e->getMessage(); }
}

$segments = $current === '' ? [] : explode('/', trim($current, '/'));
$crumbs = [['label' => 'Poze', 'path' => '']];
$acc = '';
foreach ($segments as $segment) {
    $acc .= $segment . '/';
    $crumbs[] = ['label' => $segment, 'path' => $acc];
}

$pageTitle = 'Poze';
$active = 'more';
require __DIR__.'/includes/layout_top.php';
?>

<section class="cloud-hero">
  <div class="cloud-hero__icon"><?=icon('cloud')?></div>
  <div class="cloud-hero__copy">
    <h2>Pozele familiei</h2>
    <p>Digi Storage este spațiul de stocare. Cioltea Hub este interfața voastră comună.</p>
  </div>
</section>

<?php if(!$configured): ?>
<section class="setup-card">
  <span class="mini-icon amber"><?=icon('cloud')?></span>
  <div>
    <b>Conectează Digi Storage</b>
    <p>Adaugă datele contului în <code>/home/cioltea.ro/security/config.php</code>. Aplicația va lucra doar în folderul configurat, de exemplu <code>/7. POZE/</code>.</p>
  </div>
</section>
<?php else: ?>

<?php if($error): ?><div class="alert warning"><?=e($error)?></div><?php endif; ?>
<?php if($notice): ?><div class="alert success"><?=e($notice)?></div><?php endif; ?>

<?php if($share): ?>
<section class="share-card">
  <span class="mini-icon blue"><?=icon('link')?></span>
  <div class="share-card__body">
    <b>Link de partajare</b>
    <p>Poți trimite acest link familiei sau prietenilor.</p>
    <div class="copy-line">
      <input readonly value="<?=e($share['shortUrl'] ?? $share['url'] ?? '')?>">
      <button type="button" class="btn ghost small" data-copy="<?=e($share['shortUrl'] ?? $share['url'] ?? '')?>"><?=icon('copy')?> Copiază</button>
    </div>
    <?php if(!empty($share['password'])): ?><small>Parolă: <b><?=e($share['password'])?></b></small><?php endif; ?>
  </div>
</section>
<?php endif; ?>

<nav class="cloud-breadcrumbs" aria-label="Cale folder">
  <?php foreach($crumbs as $i=>$crumb): ?>
    <?php if($i): ?><span><?=icon('chevron')?></span><?php endif; ?>
    <a href="/cloud.php?path=<?=urlencode($crumb['path'])?>"><?=e($crumb['label'])?></a>
  <?php endforeach; ?>
</nav>

<div class="cloud-actions">
  <button class="btn primary" type="button" data-open-sheet="cloudUploadSheet"><?=icon('upload')?> Încarcă</button>
  <button class="btn ghost" type="button" data-open-sheet="cloudFolderSheet"><?=icon('folder-plus')?> Folder nou</button>
  <form method="post" class="cloud-share-current">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
    <input type="hidden" name="action" value="share">
    <input type="hidden" name="current_path" value="<?=e($current)?>">
    <input type="hidden" name="target" value="<?=e($current)?>">
    <input type="hidden" name="is_dir" value="1">
    <button class="btn ghost" type="submit"><?=icon('share')?> Partajează</button>
  </form>
</div>

<?php
$folders=[];$media=[];$other=[];
foreach($files as $file){
  $isDir=(($file['type']??'')==='dir');
  $ct=(string)($file['contentType']??'');
  if($isDir)$folders[]=$file;
  elseif(str_starts_with($ct,'image/')||str_starts_with($ct,'video/'))$media[]=$file;
  else $other[]=$file;
}
?>

<?php if(!$files): ?>
<section class="empty-state"><span class="mini-icon blue"><?=icon('folder')?></span><b>Folder gol</b><p>Încarcă poze sau creează primul subfolder.</p></section>
<?php else: ?>

<?php if($folders): ?>
<section class="cloud-section">
  <div class="section-head compact"><div><h2>Albume</h2></div><span class="section-count"><?=count($folders)?></span></div>
  <div class="cloud-folder-grid">
  <?php foreach($folders as $file): $name=(string)($file['name']??'');$relative=$current.$name.'/'; ?>
    <article class="cloud-folder-card">
      <a class="cloud-folder-card__main" href="/cloud.php?path=<?=urlencode($relative)?>"><span class="cloud-folder-icon"><?=icon('folder')?></span><div><strong><?=e($name)?></strong><span>Album</span></div><?=icon('chevron')?></a>
      <button class="icon-btn small-icon-btn cloud-more-btn" type="button" data-cloud-edit data-target="<?=e($relative)?>" data-name="<?=e($name)?>" data-dir="1" aria-label="Opțiuni"><?=icon('more')?></button>
    </article>
  <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if($media): ?>
<?php
$mediaBatchSize = 10;
$initialMedia = array_slice($media, 0, $mediaBatchSize);
$remainingMedia = array_slice($media, $mediaBatchSize);
$galleryQueue = [];
foreach ($remainingMedia as $file) {
    $name = (string)($file['name'] ?? '');
    $ct = (string)($file['contentType'] ?? '');
    $galleryQueue[] = [
        'name' => $name,
        'path' => $current . $name,
        'contentType' => $ct,
        'modified' => (string)($file['modified'] ?? ''),
    ];
}
?>
<section class="cloud-section">
  <div class="section-head compact"><div><h2>Poze și video</h2><p class="section-subtle">Se încarcă în loturi de 10 pentru a rămâne rapid pe iPhone și iPad.</p></div><span class="section-count"><?=count($media)?></span></div>
  <div class="photo-grid" id="cloudPhotoGrid">
  <?php foreach($initialMedia as $file):
    $name=(string)($file['name']??'');$relative=$current.$name;$ct=(string)($file['contentType']??'');$isImage=str_starts_with($ct,'image/');$isVideo=str_starts_with($ct,'video/');
  ?>
    <article class="photo-tile">
      <a class="photo-tile__preview" href="/cloud-download.php?path=<?=urlencode($relative)?>" aria-label="Deschide <?=e($name)?>">
        <?php if($isImage): ?><img data-cloud-src="/cloud-thumb.php?path=<?=urlencode($relative)?>&v=<?=urlencode((string)($file['modified']??''))?>" alt="" loading="lazy" decoding="async" fetchpriority="low"><?php else: ?><span class="video-placeholder"><?=icon('video')?></span><?php endif; ?>
      </a>
      <div class="photo-tile__meta"><strong title="<?=e($name)?>"><?=e($name)?></strong><button class="photo-menu" type="button" data-cloud-edit data-target="<?=e($relative)?>" data-name="<?=e($name)?>" data-dir="0" aria-label="Opțiuni"><?=icon('more')?></button></div>
    </article>
  <?php endforeach; ?>
  </div>
  <?php if($remainingMedia): ?>
  <div class="photo-load-more-wrap">
    <button class="btn soft photo-load-more" id="cloudLoadMore" type="button">
      <?=icon('plus')?> Încarcă încă 10
      <span id="cloudLoadMoreMeta"><?=count($initialMedia)?> din <?=count($media)?></span>
    </button>
  </div>
  <script>
    window.CIOLTEA_CLOUD_MEDIA = <?=json_encode($galleryQueue, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
    window.CIOLTEA_CLOUD_MEDIA_TOTAL = <?=count($media)?>;
    window.CIOLTEA_CLOUD_MEDIA_SHOWN = <?=count($initialMedia)?>;
  </script>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if($other): ?>
<section class="cloud-section">
  <div class="section-head compact"><div><h2>Fișiere</h2></div><span class="section-count"><?=count($other)?></span></div>
  <div class="cloud-list">
  <?php foreach($other as $file): $name=(string)($file['name']??'');$relative=$current.$name; ?>
    <article class="cloud-row"><a class="cloud-row__main" href="/cloud-download.php?path=<?=urlencode($relative)?>"><span class="cloud-file-icon file"><?=icon('document')?></span><div><strong><?=e($name)?></strong><span><?=e(digistorage_human_size($file['size']??0))?></span></div><?=icon('chevron')?></a><div class="cloud-row__actions"><button class="icon-btn small-icon-btn" type="button" data-cloud-edit data-target="<?=e($relative)?>" data-name="<?=e($name)?>" data-dir="0" aria-label="Opțiuni"><?=icon('more')?></button></div></article>
  <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<?php endif; ?>

<div class="modal" id="cloudUploadSheet" aria-hidden="true"><div class="modal-backdrop"></div><section class="sheet"><div class="sheet-handle"></div><div class="sheet-head"><h2>Încarcă poze și fișiere</h2><button class="icon-btn" type="button" data-close-sheet><?=icon('close')?></button></div>
<form method="post" enctype="multipart/form-data" class="form-stack">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="upload"><input type="hidden" name="current_path" value="<?=e($current)?>">
<label>Fișiere<input class="file-picker" type="file" name="files[]" accept="image/*,video/*" multiple required><small class="field-note">Pe iPhone/iPad se deschide selectorul Photos. Fișierele originale sunt trimise în Digi Storage.</small></label>
<button class="btn primary wide" type="submit"><?=icon('upload')?> Încarcă în acest folder</button>
</form></section></div>

<div class="modal" id="cloudFolderSheet" aria-hidden="true"><div class="modal-backdrop"></div><section class="sheet"><div class="sheet-handle"></div><div class="sheet-head"><h2>Folder nou</h2><button class="icon-btn" type="button" data-close-sheet><?=icon('close')?></button></div>
<form method="post" class="form-stack"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create_folder"><input type="hidden" name="current_path" value="<?=e($current)?>"><label>Nume folder<div class="input-wrap"><?=icon('folder')?><input name="folder_name" maxlength="120" required placeholder="Ex: Vacanță Grecia 2026"></div></label><button class="btn primary wide" type="submit">Creează folderul</button></form>
</section></div>

<div class="modal" id="cloudEditSheet" aria-hidden="true"><div class="modal-backdrop"></div><section class="sheet"><div class="sheet-handle"></div><div class="sheet-head"><h2>Opțiuni</h2><button class="icon-btn" type="button" data-close-sheet><?=icon('close')?></button></div>
<div class="cloud-selected-name" id="cloudSelectedName"></div>
<form method="post" class="form-stack" id="cloudRenameForm"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="rename"><input type="hidden" name="current_path" value="<?=e($current)?>"><input type="hidden" name="target"><input type="hidden" name="is_dir"><label>Redenumește<div class="input-wrap"><?=icon('edit')?><input name="new_name" required></div></label><button class="btn primary wide" type="submit">Salvează numele</button></form>
<div class="cloud-danger-actions">
<form method="post" id="cloudShareForm"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="share"><input type="hidden" name="current_path" value="<?=e($current)?>"><input type="hidden" name="target"><input type="hidden" name="is_dir"><button class="btn ghost wide" type="submit"><?=icon('share')?> Creează link de partajare</button></form>
<form method="post" id="cloudDeleteForm" onsubmit="return confirm('Ștergi definitiv acest element din Digi Storage?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="current_path" value="<?=e($current)?>"><input type="hidden" name="target"><input type="hidden" name="is_dir"><button class="btn ghost wide danger" type="submit"><?=icon('trash')?> Șterge</button></form>
</div></section></div>

<?php endif; ?>
<?php require __DIR__.'/includes/layout_bottom.php'; ?>
