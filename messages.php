<?php
require __DIR__.'/includes/bootstrap.php';
require_login();require_member($pdo);
$member=current_member($pdo);$memberId=(int)$member['id'];
mark_messages_read($pdo,$memberId);
$members=family_members($pdo);
$rows=$pdo->query("SELECT m.*,fm.name sender_name,fm.initial,fm.avatar_color FROM family_messages m JOIN family_members fm ON fm.id=m.sender_member_id ORDER BY m.id DESC LIMIT 60")->fetchAll();
$rows=array_reverse($rows);
$reactionMap=[];$myReaction=[];$readMap=[];
if($rows){$ids=array_map(fn($r)=>(int)$r['id'],$rows);$marks=implode(',',array_fill(0,count($ids),'?'));
  $q=$pdo->prepare("SELECT message_id,reaction,COUNT(*) total FROM family_message_reactions WHERE message_id IN ($marks) GROUP BY message_id,reaction");$q->execute($ids);foreach($q as $r)$reactionMap[(int)$r['message_id']][]=$r;
  $q=$pdo->prepare("SELECT message_id,reaction FROM family_message_reactions WHERE member_id=? AND message_id IN ($marks)");$q->execute(array_merge([$memberId],$ids));foreach($q as $r)$myReaction[(int)$r['message_id']]=$r['reaction'];
  $q=$pdo->prepare("SELECT message_id,COUNT(*) total FROM family_message_reads WHERE member_id<>? AND message_id IN ($marks) GROUP BY message_id");$q->execute(array_merge([$memberId],$ids));foreach($q as $r)$readMap[(int)$r['message_id']]=(int)$r['total'];
}
$lastId=$rows?(int)end($rows)['id']:0;
$pageTitle='Mesaje';$active='messages';$backUrl=null;require __DIR__.'/includes/layout_top.php';
?>
<section class="chat-shell" data-chat-last-id="<?=$lastId?>">
  <div class="chat-intro"><div class="chat-people"><?php foreach($members as $m):?><span style="--avatar:<?=e($m['avatar_color'])?>"><?=e($m['initial'])?></span><?php endforeach;?></div><div><b>Chatul familiei</b><small>Mesaje, poze și reacții — doar pentru voi.</small></div></div>
  <div class="chat-messages" id="chatMessages">
  <?php if(!$rows):?><div class="chat-empty">Niciun mesaj încă. Trimite primul mesaj 👋</div><?php endif;?>
  <?php foreach($rows as $r):$own=(int)$r['sender_member_id']===$memberId;?>
    <article class="chat-message <?=$own?'own':'other'?>" data-message-id="<?=$r['id']?>">
      <?php if(!$own):?><span class="chat-avatar" style="--avatar:<?=e($r['avatar_color'])?>"><?=e($r['initial'])?></span><?php endif;?>
      <div class="chat-bubble-wrap">
        <?php if(!$own):?><small class="chat-sender"><?=e($r['sender_name'])?></small><?php endif;?>
        <div class="chat-bubble">
          <?php if(!empty($r['image_path'])):?><button class="chat-photo" type="button" data-chat-photo="/message-media.php?id=<?=$r['id']?>" aria-label="Deschide poza"><img src="/message-media.php?id=<?=$r['id']?>" loading="lazy" decoding="async" alt="Poză trimisă"></button><?php endif;?>
          <?php if(trim((string)$r['body'])!==''):?><p><?=nl2br(e($r['body']))?></p><?php endif;?>
        </div>
        <div class="chat-meta"><span><?=e(date('H:i',strtotime($r['created_at'])))?></span><?php if($own):?><span class="chat-read-state <?=($readMap[(int)$r['id']]??0)>0?'is-read':''?>" title="<?=($readMap[(int)$r['id']]??0)>0?'Văzut':'Trimis'?>">✓</span><?php endif;?></div>
        <div class="chat-reactions" data-reactions-for="<?=$r['id']?>">
          <?php foreach(($reactionMap[(int)$r['id']]??[]) as $rr):?><button type="button" class="reaction-pill <?=($myReaction[(int)$r['id']]??'')===$rr['reaction']?'mine':''?>" data-react-message="<?=$r['id']?>" data-reaction="<?=e($rr['reaction'])?>"><?=e($rr['reaction'])?> <span><?=$rr['total']?></span></button><?php endforeach;?>
          <?php if(!$own):?><button type="button" class="reaction-add" data-reaction-menu="<?=$r['id']?>" aria-label="Adaugă reacție" title="Reacționează">☺</button>
          <div class="reaction-menu" data-reaction-menu-panel="<?=$r['id']?>" hidden><?php foreach(['❤️','👍','😂','😍','😮','🙏'] as $emoji):?><button type="button" data-react-message="<?=$r['id']?>" data-reaction="<?=$emoji?>"><?=$emoji?></button><?php endforeach;?></div><?php endif;?>
        </div>
      </div>
    </article>
  <?php endforeach;?>
  </div>
  <button class="chat-new-pill" id="chatNewPill" type="button" hidden>Mesaje noi</button>
  <form class="chat-composer" id="chatComposer" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
    <label class="chat-photo-btn" aria-label="Trimite o poză"><?=icon('image')?><input type="file" name="image" id="chatImage" accept="image/*" hidden></label>
    <div class="chat-input-wrap"><textarea name="body" id="chatBody" rows="1" maxlength="4000" placeholder="Scrie un mesaj…"></textarea><div class="chat-file-preview" id="chatFilePreview" hidden></div></div>
    <button class="chat-send-btn" type="submit" aria-label="Trimite"><?=icon('send')?></button>
  </form>
</section>
<div class="chat-lightbox" id="chatLightbox" aria-hidden="true">
  <button class="chat-lightbox__backdrop" type="button" data-close-chat-lightbox aria-label="Închide poza"></button>
  <div class="chat-lightbox__panel" role="dialog" aria-modal="true" aria-label="Previzualizare poză">
    <button class="chat-lightbox__close" type="button" data-close-chat-lightbox aria-label="Închide">×</button>
    <div class="chat-lightbox__viewport"><img id="chatLightboxImage" alt="Poză din conversație"></div>
  </div>
</div>
<?php require __DIR__.'/includes/layout_bottom.php';?>
