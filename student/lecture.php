<?php
// Student: watch lecture + mark complete
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();
$u = current_user();
if ($u['role'] !== 'student') redirect('../admin/index.php');
$pdo = db();

$course_id = (int)($_GET['course_id'] ?? 0);
$lecture_id = (int)($_GET['lecture_id'] ?? 0);

$st = $pdo->prepare('SELECT * FROM lectures WHERE id = ? AND course_id = ?');
$st->execute([$lecture_id, $course_id]);
$lec = $st->fetch();
if (!$lec) { flash('Lecture nahi mili.', 'error'); redirect('index.php'); }

$st = $pdo->prepare('SELECT 1 FROM enrollments WHERE student_id = ? AND course_id = ?');
$st->execute([$u['id'], $course_id]);
if (!$st->fetch() && !$lec['is_preview']) { flash('Ye lecture locked hai.', 'error'); redirect('course.php?course_id=' . $course_id); }

// v21: batch restriction — is batch ko ye lecture allowed hai?
$my_batch = student_batch($u['id'], $course_id);
$my_batch_id = $my_batch ? (int)$my_batch['id'] : null;
if (!lecture_visible($lec, $my_batch_id)) {
    flash('Yeh content naye batch ke liye hai. Upgrade / Re-enroll to access.', 'error');
    redirect('course.php?course_id=' . $course_id);
}

if (isset($_GET['complete'])) {
    // v54b: 3-min rule — bina dekhe complete nahi (server-side check)
    $wsec = 0;
    try {
        $st = $pdo->prepare('SELECT watched_sec FROM video_progress WHERE student_id = ? AND lecture_id = ?');
        $st->execute([$u['id'], $lecture_id]);
        $wsec = (int)($st->fetchColumn() ?: 0);
    } catch (Exception $ex) {}
    // JS se bheja hua fresh count bhi qabool (is page par abhi dekha)
    $wsec = max($wsec, (int)($_GET['wt'] ?? 0));
    $need = required_watch_sec($lec['duration'] ?? '');
    if (!$done && $wsec < $need) {
        $rem = $need - $wsec;
        flash('Pehle kam az kam ' . gmdate('i:s', $need) . ' dekhein — abhi ' . gmdate('i:s', $rem) . ' baqi hai.', 'error');
        redirect('lecture.php?course_id=' . $course_id . '&lecture_id=' . $lecture_id);
    }
    $pdo->prepare('INSERT IGNORE INTO lecture_progress (student_id, lecture_id) VALUES (?, ?)')->execute([$u['id'], $lecture_id]);
    try { $pdo->prepare('DELETE FROM video_progress WHERE student_id = ? AND lecture_id = ?')->execute([$u['id'], $lecture_id]); }
    catch (Exception $ex) {}
    // next lecture
    $st = $pdo->prepare('SELECT id FROM lectures WHERE course_id = ? AND (sort_order > ? OR (sort_order = ? AND id > ?)) ORDER BY sort_order, id LIMIT 1');
    $st->execute([$course_id, $lec['sort_order'], $lec['sort_order'], $lecture_id]);
    $next = $st->fetchColumn();
    flash('Lecture complete! ');
    redirect($next ? "lecture.php?course_id=$course_id&lecture_id=$next" : "course.php?course_id=$course_id");
}

$st = $pdo->prepare('SELECT 1 FROM lecture_progress WHERE student_id = ? AND lecture_id = ?');
$st->execute([$u['id'], $lecture_id]);
$done = (bool)$st->fetch();

$embed = youtube_embed($lec['youtube_url']);

// is lecture ke resources (files + external links)
$resources = [];
try {
    $st = $pdo->prepare('SELECT * FROM lecture_resources WHERE lecture_id = ? ORDER BY sort_order, id');
    $st->execute([$lecture_id]);
    $resources = $st->fetchAll();
} catch (Exception $ex) { /* table nahi hai to skip */ }
if (!empty($lec['resource_file'])) {
    $resources[] = ['label' => 'Download file', 'file_path' => $lec['resource_file'], 'url' => null];
}
$has_res = count($resources) > 0;

// resume position (database se — har device par kaam karega)
$resume_sec = 0;
$watched_init = 0;
try {
    $st = $pdo->prepare('SELECT seconds, watched_sec FROM video_progress WHERE student_id = ? AND lecture_id = ?');
    $st->execute([$u['id'], $lecture_id]);
    if ($r = $st->fetch()) { $resume_sec = (int)$r['seconds']; $watched_init = (int)($r['watched_sec'] ?? 0); }
} catch (Exception $ex) {}
// v54b: complete ke liye lazmi watch time (3 min ya chhoti video par 90%)
$required_sec = required_watch_sec($lec['duration'] ?? '');

// v29: prev/next lecture (same course order)
$st = $pdo->prepare('SELECT id FROM lectures WHERE course_id = ? AND (sort_order < ? OR (sort_order = ? AND id < ?)) ORDER BY sort_order DESC, id DESC LIMIT 1');
$st->execute([$course_id, $lec['sort_order'], $lec['sort_order'], $lecture_id]);
$prev_id = $st->fetchColumn() ?: null;
$st = $pdo->prepare('SELECT id FROM lectures WHERE course_id = ? AND (sort_order > ? OR (sort_order = ? AND id > ?)) ORDER BY sort_order, id LIMIT 1');
$st->execute([$course_id, $lec['sort_order'], $lec['sort_order'], $lecture_id]);
$next_id = $st->fetchColumn() ?: null;

$page_title = $lec['title'];
require __DIR__ . '/../inc/header.php';
?>

<div class="mb-4 flex flex-wrap items-center gap-3 justify-between">
  <a href="course.php?course_id=<?= $course_id ?>" class="text-sm underline text-gray-500">← Back to course</a>
  <div class="flex items-center gap-3 flex-wrap">
    <?php
    // v23: Batch Community button (sirf enrolled, link set ho, batch active ho)
    $vlink = community_link_for($course_id, $my_batch_id);
    $vended = $my_batch && batch_status($my_batch) === 'completed';
    if ($vlink && !$vended) echo vip_community_btn($vlink, $my_batch['batch_name'] ?? null);
    ?>
    <?php if ($my_batch): ?><?= batch_badge($my_batch) ?><?php endif; ?>
  </div>
</div>

<div class="dm-card dm-card-pad mb-4">

  <!-- Tabs: Video | Resources -->
  <div class="dm-tabs" role="tablist">
    <button type="button" id="tabVideo" onclick="lmsTab('video')" class="dm-tab on" role="tab"><?= icon('play',16) ?> Video</button>
    <?php if ($has_res): ?>
    <button type="button" id="tabRes" onclick="lmsTab('res')" class="dm-tab" role="tab"><?= icon('paperclip',16) ?> Resources <span class="num" style="color:var(--dm-muted)"><?= count($resources) ?></span></button>
    <?php endif; ?>
  </div>

  <div id="paneVideo" class="pt-4">
  <?php if ($embed):
    $wm_text = $u['name'] . ($u['phone'] ? ' · ' . $u['phone'] : '');
    $embed_api = $embed . '&enablejsapi=1';
  ?>
    <!-- v41: Cinema frame — premium light player -->
    <div class="cinema">
      <div class="cinema-marquee">
        <span class="cm-protected"><?= icon('shield', 12) ?> Protected</span>
        <span class="cm-kicker">Now playing · Lecture <?= (int)$lec['sort_order'] ?></span>
        <span class="cm-title"><?= e($lec['title']) ?><?php if (is_new_lecture($lec)) echo new_badge(); ?></span>
      </div>
      <div class="cinema-screen">
    <div id="playerWrap" class="relative w-full select-none" style="padding-bottom:56.25%; background:#0b1526; overflow:hidden" oncontextmenu="return false">
      <iframe id="ytplayer" class="absolute inset-0 w-full h-full" src="<?= e($embed_api) ?>" frameborder="0"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>

      <!-- v40 Anti-leak shields (native controls=1 = reliable play/seek/speed):
           top strip = title + Share/Watch-Later buttons dead;
           bottom-right = "Watch on YouTube" button dead. Baqi player native. -->
      <div class="yt-shield-top" oncontextmenu="return false" ondragstart="return false" onselectstart="return false"></div>
      <div class="yt-shield-bottomfull" oncontextmenu="return false" ondragstart="return false" onselectstart="return false"></div>

      <!-- v42: Custom endscreen — video end hote hi YouTube endscreen cover (API available ho to) -->
      <div id="ytEndscreen" class="yt-endscreen" style="display:none">
        <div>
          <div class="ye-kicker">Lecture complete</div>
          <div class="ye-title"><?= e($lec['title']) ?></div>
          <div class="ye-btns">
            <?php if ($next_id): ?>
              <a class="ye-btn" href="lecture.php?course_id=<?= $course_id ?>&lecture_id=<?= (int)$next_id ?>">Next lecture <?= icon('arrow-right', 16) ?></a>
            <?php else: ?>
              <a class="ye-btn" href="course.php?course_id=<?= $course_id ?>">Back to course <?= icon('arrow-right', 16) ?></a>
            <?php endif; ?>
            <button type="button" class="ye-replay" onclick="document.getElementById('ytEndscreen').style.display='none'"><?= icon('rewind', 16) ?> Replay</button>
          </div>
        </div>
      </div>

      <!-- Dynamic floating watermark: student ka naam + phone -->
      <div id="wm" class="absolute font-semibold whitespace-nowrap"
           style="z-index:10; pointer-events:none; user-select:none; -webkit-user-select:none;
                  color:#fff; opacity:0.18; font-size:15px; text-shadow:0 1px 4px rgba(0,0,0,.7);
                  transition:left 1.2s ease, top 1.2s ease; left:16px; top:70px;"><?= e($wm_text) ?></div>
    </div><!-- /playerWrap -->
      </div><!-- /cinema-screen -->
      <div class="cinema-actions">
        <span class="ca-meta">
          <?= icon('play', 14) ?> Lecture <?= (int)$lec['sort_order'] ?>
          <?php if ($lec['duration']): ?><span class="ca-dot">·</span><?= icon('clock', 14) ?> <?= e($lec['duration']) ?><?php endif; ?>
        </span>
        <span style="display:inline-flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <span id="completeArea">
          <?php if ($done): ?>
            <span class="ca-done"><?= icon('check-circle', 16) ?> Completed</span>
          <?php else: ?>
            <button type="button" class="ca-btn" id="manualCompleteBtn" onclick="tryManualComplete()"><?= icon('check', 16) ?> Mark complete</button>
          <?php endif; ?>
        </span>
        <button type="button" class="ca-fs" onclick="cinemaFullscreen()" aria-label="Fullscreen"><?= icon('maximize', 16) ?> Fullscreen</button>
        </span>
      </div>
    </div><!-- /cinema -->
    <script>
    (function(){
      var wrap = document.getElementById('playerWrap');
      var wm = document.getElementById('wm');
      function moveWm(){
        var maxX = Math.max(0, wrap.clientWidth - wm.offsetWidth - 16);
        var maxY = Math.max(0, wrap.clientHeight - wm.offsetHeight - 16);
        wm.style.left = (8 + Math.random() * maxX) + 'px';
        wm.style.top  = (8 + Math.random() * maxY) + 'px';
        setTimeout(moveWm, 10000 + Math.random() * 5000); // har 10-15 sec nayi position
      }
      setTimeout(moveWm, 9000 + Math.random() * 4000);
    })();
    </script>
    <script src="https://www.youtube.com/iframe_api"></script>
    <script>
    /* v40: Native-first player — play/seek/speed 100% YouTube native (controls=1).
       Ye script SIRF progress tracking ke liye hai (Resume Learning + auto-complete).
       Agar API load na ho to bhi video fully kaam karta hai. */
    var YT_PLAYER = null, YT_READY = false, pollTimer = null;
    var RESUME_SEC = <?= (int)$resume_sec ?>;
    var LEC_ID = <?= (int)$lecture_id ?>, CRS_ID = <?= (int)$course_id ?>;
    var autoDone = <?= $done ? 'true' : 'false' ?>;
    var lastSaved = -1;
    /* v54b: 3-min rule — sirf natural playback count hoga (seek nahi) */
    var REQUIRED_SEC = <?= (int)$required_sec ?>;
    var watchedSec = <?= (int)$watched_init ?>;
    var lastPlayT = null;

    function onYouTubeIframeAPIReady(){
      try {
        YT_PLAYER = new YT.Player('ytplayer', {
          events: { 'onReady': onPReady, 'onStateChange': onPState }
        });
      } catch(e){}
    }
    function onPReady(ev){
      YT_READY = true;
      if (RESUME_SEC > 5) { try { ev.target.seekTo(RESUME_SEC, true); } catch(e){} }
      startPoll();
    }
    function onPState(ev){
      var es = document.getElementById('ytEndscreen');
      if (ev.data === YT.PlayerState.PAUSED) { savePos(); stopPoll(); }
      else if (ev.data === YT.PlayerState.PLAYING) { if (es) es.style.display = 'none'; startPoll(); }
      else if (ev.data === YT.PlayerState.ENDED) { savePos(); doComplete(); if (es) es.style.display = 'flex'; }
    }
    function curSec(){ try { return Math.floor(YT_PLAYER.getCurrentTime()); } catch(e){ return 0; } }
    function getDur(){ try { return YT_PLAYER.getDuration() || 0; } catch(e){ return 0; } }
    var CSRF_TOKEN = '<?= csrf_token() ?>';
    function ajax(data){
      var fd = new FormData();
      for (var k in data) fd.append(k, data[k]);
      fd.append('_csrf', CSRF_TOKEN);
      return fetch('progress_ajax.php', { method:'POST', body:fd, keepalive:true }).then(function(r){ return r.json(); }).catch(function(){ return {ok:false}; });
    }
    function savePos(sendWatched){
      if (!YT_READY) return;
      var s = curSec();
      if (s < 5 || s === lastSaved) { if (sendWatched) ajax({ action:'save', lecture_id:LEC_ID, course_id:CRS_ID, seconds:s, watched:Math.floor(watchedSec) }); return; }
      lastSaved = s;
      ajax({ action:'save', lecture_id:LEC_ID, course_id:CRS_ID, seconds:s, watched:Math.floor(watchedSec) });
    }
    function fmtT(sec){ sec = Math.max(0, Math.floor(sec)); var m = Math.floor(sec/60), s = sec%60; return (m<10?'0':'')+m+':'+(s<10?'0':'')+s; }
    function updateWatchUI(){
      var el = document.getElementById('watchCount');
      if (el && !autoDone) {
        var rem = REQUIRED_SEC - Math.floor(watchedSec);
        el.textContent = rem > 0 ? '(' + fmtT(watchedSec) + ' / ' + fmtT(REQUIRED_SEC) + ')' : '';
      } else if (el) el.textContent = '';
    }
    /* v54b: button se complete — 3 min dekhe baghair nahi */
    function tryManualComplete(){
      if (autoDone) return;
      updateWatched();
      if (Math.floor(watchedSec) >= REQUIRED_SEC) {
        autoDone = true;
        ajax({ action:'complete', lecture_id:LEC_ID, course_id:CRS_ID }).then(function(j){
          if (j && j.ok) window.location.href = 'lecture.php?course_id=' + CRS_ID + '&lecture_id=' + LEC_ID + '&complete=1&wt=' + Math.floor(watchedSec);
          else window.location.href = 'lecture.php?course_id=' + CRS_ID + '&lecture_id=' + LEC_ID + '&complete=1&wt=' + Math.floor(watchedSec);
        });
      } else {
        var rem = REQUIRED_SEC - Math.floor(watchedSec);
        alert('Pehle kam az kam ' + fmtT(REQUIRED_SEC) + ' dekhein — abhi ' + fmtT(rem) + ' baqi hai.');
      }
    }
    /* v54b: natural playback ka hisab (seek ko count nahi karna) */
    function updateWatched(){
      if (!YT_READY) return;
      try {
        var st = YT_PLAYER.getPlayerState();
        var cur = YT_PLAYER.getCurrentTime();
        if (st === 1) { // PLAYING
          if (lastPlayT !== null) {
            var delta = cur - lastPlayT;
            if (delta > 0 && delta <= 2.5) watchedSec += delta; // seek (bari jump) count nahi
          }
          lastPlayT = cur;
        } else lastPlayT = null;
      } catch(e){}
    }
    function doComplete(){
      if (autoDone) return;
      updateWatched();
      if (Math.floor(watchedSec) < REQUIRED_SEC) return; // v54b: 3-min se pehle auto nahi
      autoDone = true;
      ajax({ action:'complete', lecture_id:LEC_ID, course_id:CRS_ID, watched:Math.floor(watchedSec) }).then(function(j){
        if (j && j.ok) {
          var el = document.getElementById('completeArea');
          if (el) el.innerHTML = '<span class="ca-done"><?= icon('check-circle', 14) ?> Completed</span>';
          updateWatchUI();
        } else autoDone = false; // server ne roka — dobara try hoga
      });
    }
    function startPoll(){
      stopPoll();
      updateWatchUI();
      pollTimer = setInterval(function(){
        if (!YT_READY) return;
        try {
          updateWatched(); // v54b: natural playback count
          updateWatchUI();
          // v54b: 3-min (ya required) dekhne par auto-complete — 90% wala rule khatam
          if (!autoDone && Math.floor(watchedSec) >= REQUIRED_SEC) { doComplete(); return; }
          if (Math.abs(curSec() - lastSaved) > 12) savePos();
        } catch(e){}
      }, 1000);
    }
    function stopPoll(){ if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }
    window.addEventListener('pagehide', savePos);
    document.addEventListener('visibilitychange', function(){ if (document.hidden) savePos(); });
    /* v47: Custom fullscreen — wrapper par (shields sath rehte hain) */
    function cinemaFullscreen(){
      var w = document.getElementById('playerWrap');
      try {
        if (document.fullscreenElement) document.exitFullscreen();
        else if (w.requestFullscreen) w.requestFullscreen();
        else if (w.webkitRequestFullscreen) w.webkitRequestFullscreen();
      } catch(e){}
    }
    </script>

    <div class="pl-nav">
      <?php if ($prev_id): ?>
        <a href="lecture.php?course_id=<?= $course_id ?>&lecture_id=<?= (int)$prev_id ?>"><?= icon('chevron-left', 18) ?> Previous Lecture</a>
      <?php else: ?>
        <a class="dis"><?= icon('chevron-left', 18) ?> Previous Lecture</a>
      <?php endif; ?>
      <?php if ($next_id): ?>
        <a class="next" href="lecture.php?course_id=<?= $course_id ?>&lecture_id=<?= (int)$next_id ?>">Next Lecture <?= icon('chevron-right', 18) ?></a>
      <?php else: ?>
        <a class="next" href="course.php?course_id=<?= $course_id ?>">Finish — Back to Course <?= icon('chevron-right', 18) ?></a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="bg-red-50 text-red-600 p-4 rounded text-sm">Video link me masla hai — admin ko inform karein.</div>
  <?php endif; ?>
  </div><!-- /paneVideo -->

  <?php if ($has_res): ?>
  <div id="paneRes" class="hidden pt-4">
    <div class="space-y-2">
      <?php foreach ($resources as $r):
        $is_file = !empty($r['file_path']);
        $href = $is_file ? '../' . ltrim($r['file_path'], '/') : $r['url'];
        $label = trim($r['label'] ?? '') !== '' ? $r['label'] : ($is_file ? 'Download file' : 'Open link');
      ?>
      <a href="<?= e($href) ?>" <?= $is_file ? 'download' : 'target="_blank" rel="noopener"' ?>
         class="dm-res-row">
        <span class="t"><?= $is_file ? icon('file-text',16) : icon('link',16) ?> <?= e($label) ?></span>
        <span class="cr-go"><?= $is_file ? 'Download' : 'Open' ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <script>
  function lmsTab(which){
    var isVideo = (which === 'video');
    document.getElementById('paneVideo').classList.toggle('hidden', !isVideo);
    var pr = document.getElementById('paneRes');
    if (pr) pr.classList.toggle('hidden', isVideo);
    var tv = document.getElementById('tabVideo'), tr = document.getElementById('tabRes');
    tv.classList.toggle('on', isVideo);
    if (tr) tr.classList.toggle('on', !isVideo);
  }
  </script>
  <?php endif; ?>

  <div class="mt-4 flex gap-3 flex-wrap items-center" id="completeArea">
    <?php if ($done): ?>
      <span class="ca-done"><?= watch_ring(100, 18, 'Completed') ?> Completed</span>
    <?php else: ?>
      <span class="dm-watch-note"><?= icon('clock',16) ?> <b class="num"><?= gmdate('i:s', $required_sec) ?></b> dekhne par lecture complete ho jayega <span id="watchCount" class="num"></span></span>
      <button type="button" onclick="tryManualComplete()" class="dm-link-quiet">Mark manually</button>
    <?php endif; ?>
  </div>

  <?php if (trim($lec['notes'] ?? '')): ?>
    <div class="mt-6"><h3 class="dm-sub-h">Notes</h3>
    <div class="dm-notes"><?= nl2br(e($lec['notes'])) ?></div></div>
  <?php endif; ?>
</div>

<?= wa_rules_modal() ?>
<?php require __DIR__ . '/../inc/footer.php'; ?>
