<?php
session_start();
require_once 'config/database.php';
require_once 'classes/MediaContent.php';
require_once 'classes/CoinsSystem.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$content_id = (int)($_GET['id'] ?? 0);

if($content_id <= 0) {
    header('Location: marketplace.php');
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $mediaContent = new MediaContent($db);
    $coinsSystem = new CoinsSystem($db);

    $content = $mediaContent->getContent($content_id, $_SESSION['user_id']);

    if(!$content) {
        header('Location: marketplace.php');
        exit();
    }

    $content['files'] = $content['files'] ?? [];
    $content['view_count'] = $content['view_count'] ?? 0;
    $content['total_likes'] = $content['total_likes'] ?? 0;
    $content['total_purchases'] = $content['total_purchases'] ?? 0;
    $content['user_liked'] = $content['user_liked'] ?? 0;
    $content['user_has_access'] = $content['user_has_access'] ?? 0;

    $is_owner = $content['creator_id'] == $_SESSION['user_id'];
    $has_access = $content['user_has_access'] || $is_owner || ($content['is_free'] ?? false);
    $user_balance = $coinsSystem->getBalance($_SESSION['user_id']);

    if($has_access && !$is_owner) {
        $query = "UPDATE media_content SET view_count = view_count + 1 WHERE id = :content_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':content_id', $content_id);
        $stmt->execute();
    }
} catch(Exception $e) {
    error_log('View content error: ' . $e->getMessage());
    header('Location: marketplace.php');
    exit();
}

include 'views/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.min.css" rel="stylesheet">

<style>
:root{--bg-dark:#0a0a0f;--bg-card:#1a1a2e;--border:#2d2d44;--blue:#4267f5;--text:#fff;--gray:#a0a0b0;--green:#10b981;--orange:#f59e0b;--red:#ef4444}
*{margin:0;padding:0;box-sizing:border-box}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg-dark);color:var(--text)}
.container{max-width:1200px;margin:0 auto;padding:2rem 1.5rem}.card{background:var(--bg-card);border:2px solid var(--border);border-radius:20px;padding:2rem;margin-bottom:2rem}
.media-container{background:var(--bg-card);border:2px solid var(--border);border-radius:20px;overflow:hidden;margin-bottom:2rem;position:relative}
.media-item{width:100%;max-height:80vh;object-fit:contain;background:#000}.locked-overlay{position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.9);backdrop-filter:blur(20px);display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:10}
.unlock-card{background:var(--bg-card);border:2px solid var(--border);border-radius:20px;padding:3rem;text-align:center;max-width:500px;margin:2rem}
.price-display{font-size:4rem;font-weight:800;background:linear-gradient(135deg,#fbbf24,#f59e0b);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin:1rem 0}
.media-gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;padding:1rem}.gallery-item{cursor:pointer;border-radius:10px;overflow:hidden;border:2px solid var(--border);transition:all .3s}
.gallery-item:hover{transform:scale(1.05);border-color:var(--blue)}.gallery-item img{width:100%;height:200px;object-fit:cover}
.action-buttons{display:flex;gap:1rem;justify-content:center;margin-top:1.5rem;flex-wrap:wrap}.btn{padding:.75rem 1.5rem;border-radius:10px;border:none;font-weight:600;cursor:pointer;transition:all .3s;text-decoration:none;display:inline-block}
.btn-primary{background:var(--blue);color:#fff}.btn-secondary{background:var(--bg-card);color:var(--text);border:2px solid var(--border)}.btn:hover{transform:translateY(-2px)}
.stats{display:flex;gap:2rem;justify-content:center;margin:1.5rem 0;flex-wrap:wrap}.stat{display:flex;align-items:center;gap:.5rem;color:var(--gray)}
@media(max-width:768px){.unlock-card{padding:2rem 1rem}.price-display{font-size:3rem}}
</style>

<div class="container">
<div class="card"><div style="display:flex;justify-content:space-between;align-items:start;gap:2rem;flex-wrap:wrap"><div style="flex:1"><div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem"><a href="profile-enhanced.php?id=<?php echo $content['creator_id'];?>" style="text-decoration:none"><div style="width:50px;height:50px;border-radius:50%;background:linear-gradient(135deg,var(--blue),#1d9bf0);display:flex;align-items:center;justify-content:center;font-size:1.5rem">👤</div></a><div><a href="profile-enhanced.php?id=<?php echo $content['creator_id'];?>" style="text-decoration:none"><strong style="color:var(--text);font-size:1.1rem"><?php echo htmlspecialchars($content['creator_name']??'Unknown');?></strong></a><div style="color:var(--gray);font-size:.9rem"><?php echo date('M j, Y',strtotime($content['created_at']??'now'));?></div></div></div>
<h1 style="font-size:2rem;margin-bottom:1rem"><?php echo htmlspecialchars($content['title']??'Untitled');?></h1>
<?php if($content['description']??false):?><p style="color:var(--gray);line-height:1.8"><?php echo nl2br(htmlspecialchars($content['description']));?></p><?php endif;?>
<div class="stats"><div class="stat"><span style="font-size:1.2rem">👁️</span><span><?php echo number_format($content['view_count']);?> views</span></div><div class="stat"><span style="font-size:1.2rem">❤️</span><span><?php echo number_format($content['total_likes']);?> likes</span></div><div class="stat"><span style="font-size:1.2rem">🛍️</span><span><?php echo number_format($content['total_purchases']);?> purchases</span></div></div></div>
<div style="text-align:center"><?php if($content['is_free']??false):?><div style="background:var(--green);color:#fff;padding:1rem 2rem;border-radius:20px;font-weight:700;font-size:1.2rem">🎉 FREE</div><?php else:?><div style="background:linear-gradient(135deg,rgba(251,191,36,.2),rgba(245,158,11,.1));border:2px solid #fbbf24;padding:1rem 2rem;border-radius:20px"><div style="color:var(--gray);font-size:.9rem;margin-bottom:.25rem">Price</div><div style="font-size:2rem;font-weight:800;color:#fbbf24">💰 <?php echo number_format($content['price']??0);?></div><div style="color:var(--gray);font-size:.9rem">coins</div></div><?php endif;?>
<?php if($has_access):?><div style="margin-top:1rem;color:var(--green);font-weight:600">✓ You have access</div><?php endif;?></div></div></div>

<div class="media-container">
<?php if($has_access):?>
<?php if(count($content['files'])==1):?><?php $file=$content['files'][0];$file_type=$file['file_type']??'';?>
<?php if(!empty($file_type)&&strpos($file_type,'image')!==false):?><img src="<?php echo htmlspecialchars($file['file_path']);?>" class="media-item" alt="Content"><?php else:?><video controls class="media-item"><source src="<?php echo htmlspecialchars($file['file_path']);?>" type="<?php echo htmlspecialchars($file_type);?>"></video><?php endif;?>
<?php elseif(count($content['files'])>0):?><div id="mainMedia" style="min-height:500px;display:flex;align-items:center;justify-content:center;background:#000"><?php $first=$content['files'][0];$first_type=$first['file_type']??'';?>
<?php if(!empty($first_type)&&strpos($first_type,'image')!==false):?><img id="mainImage" src="<?php echo htmlspecialchars($first['file_path']);?>" class="media-item" alt="Content"><?php else:?><video id="mainVideo" controls class="media-item"><source src="<?php echo htmlspecialchars($first['file_path']);?>" type="<?php echo htmlspecialchars($first_type);?>"></video><?php endif;?></div>
<div class="media-gallery"><?php foreach($content['files'] as $index=>$file):$media_type=$file['file_type']??'';?><div class="gallery-item" onclick="showMedia(<?php echo $index;?>)"><?php if(!empty($media_type)&&strpos($media_type,'image')!==false):?><img src="<?php echo htmlspecialchars($file['file_path']);?>" alt="Thumbnail"><?php else:?><div style="height:200px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#4267f5,#1d9bf0);font-size:3rem">🎥</div><?php endif;?></div><?php endforeach;?></div>
<?php endif;?>
<?php else:?>
<?php if($content['thumbnail']??false):?><img src="<?php echo htmlspecialchars($content['thumbnail']);?>" class="media-item" style="filter:blur(30px)" alt="Preview"><?php else:?><div style="height:500px;background:linear-gradient(135deg,#4267f5,#1d9bf0)"></div><?php endif;?>
<div class="locked-overlay"><div class="unlock-card"><div style="font-size:5rem;margin-bottom:1rem">🔒</div><h2 style="margin-bottom:1rem">Unlock This Content</h2><div class="price-display"><?php echo number_format($content['price']??0);?> coins</div>
<div style="color:var(--gray);margin-bottom:2rem">Your balance: <strong style="color:var(--text)"><?php echo number_format($user_balance);?> coins</strong></div>
<?php if($user_balance>=($content['price']??0)):?><button onclick="purchaseContent(<?php echo $content_id;?>)" class="btn btn-primary" style="font-size:1.2rem;padding:1rem 2rem;margin-bottom:1rem;width:100%">🔓 Unlock for <?php echo number_format($content['price']??0);?> coins</button><?php else:?><div style="background:rgba(239,68,68,.2);border:2px solid #ef4444;padding:1rem;border-radius:10px;margin-bottom:1rem;color:#ef4444">⚠️ Insufficient coins</div><a href="buy-coins.php" class="btn btn-primary" style="font-size:1.2rem;padding:1rem 2rem;margin-bottom:1rem;width:100%">💰 Buy More Coins</a><?php endif;?>
<a href="messages-compose.php?to=<?php echo $content['creator_id'];?>" class="btn btn-secondary" style="width:100%">💬 Message Creator</a></div></div>
<?php endif;?></div>

<?php if($has_access):?><div class="card"><div class="action-buttons"><button onclick="likeContent(<?php echo $content_id;?>)" id="likeBtn" class="btn btn-secondary" style="<?php echo $content['user_liked']?'background:var(--red);color:#fff':'';?>"><?php echo $content['user_liked']?'❤️':'🤍';?> <?php echo $content['user_liked']?'Liked':'Like';?></button>
<a href="profile-enhanced.php?id=<?php echo $content['creator_id'];?>" class="btn btn-secondary">👤 View Creator</a><a href="messages-compose.php?to=<?php echo $content['creator_id'];?>" class="btn btn-secondary">💬 Message Creator</a><button onclick="showTipModal()" class="btn btn-secondary">💰 Send Tip</button></div></div><?php endif;?></div>

<div id="tipModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.85);z-index:9999;align-items:center;justify-content:center"><div class="card" style="max-width:500px;width:90%;margin:2rem"><h3 style="margin-bottom:1.5rem">💰 Send a Tip</h3><div style="margin-bottom:1rem"><label style="display:block;margin-bottom:.5rem;font-weight:600">Amount (coins)</label><input type="number" id="tipAmount" style="width:100%;padding:.75rem;background:rgba(255,255,255,.05);border:2px solid var(--border);border-radius:10px;color:var(--text)" min="10" placeholder="Enter amount..."></div><div style="margin-bottom:1rem"><label style="display:block;margin-bottom:.5rem;font-weight:600">Message (optional)</label><textarea id="tipMessage" style="width:100%;padding:.75rem;background:rgba(255,255,255,.05);border:2px solid var(--border);border-radius:10px;color:var(--text)" rows="3"></textarea></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem"><button onclick="closeTipModal()" class="btn btn-secondary" style="width:100%">Cancel</button><button onclick="sendTip()" class="btn btn-primary" style="width:100%">Send Tip</button></div></div></div>

<script>
const mediaFiles=<?php echo json_encode($content['files']);?>;
function showMedia(i){const m=document.getElementById('mainMedia'),f=mediaFiles[i];m.innerHTML=f.file_type&&f.file_type.includes('image')?`<img id="mainImage" src="${f.file_path}" class="media-item" alt="Content">`:`<video id="mainVideo" controls class="media-item"><source src="${f.file_path}" type="${f.file_type||'video/mp4'}"></video>`}
function purchaseContent(id){if(!confirm('Unlock this content for <?php echo number_format($content['price']??0);?> coins?'))return;fetch('/api/purchase-content.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({content_id:id})}).then(r=>r.json()).then(data=>{if(data.success){alert('✅ Content unlocked!');location.reload()}else alert('❌ '+(data.error||'Purchase failed'))})}
function likeContent(id){const btn=document.getElementById('likeBtn'),isLiked=btn.textContent.includes('Liked');fetch('/api/like-content.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({content_id:id,action:isLiked?'unlike':'like'})}).then(r=>r.json()).then(data=>{if(data.success){if(isLiked){btn.innerHTML='🤍 Like';btn.style.background='';btn.style.color=''}else{btn.innerHTML='❤️ Liked';btn.style.background='var(--red)';btn.style.color='#fff'}}})}
function showTipModal(){document.getElementById('tipModal').style.display='flex'}function closeTipModal(){document.getElementById('tipModal').style.display='none'}
function sendTip(){const amount=document.getElementById('tipAmount').value,message=document.getElementById('tipMessage').value;if(!amount||amount<10){alert('Minimum tip is 10 coins');return}fetch('/api/send-tip.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({to_user_id:<?php echo $content['creator_id'];?>,content_id:<?php echo $content_id;?>,amount:amount,message:message})}).then(r=>r.json()).then(data=>{if(data.success){alert('✅ Tip sent successfully!');closeTipModal()}else alert('❌ '+(data.error||'Failed to send tip'))})}
document.getElementById('tipModal')?.addEventListener('click',function(e){if(e.target===this)closeTipModal()});
</script>

<?php include 'views/footer.php';?>