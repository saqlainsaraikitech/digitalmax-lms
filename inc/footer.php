    </main>
    <div class="dm-legalbar"><?= legal_footer_links(' | ', '../') ?></div>
  </div>
</div>

<?php
// v29: Mobile app-style bottom bar (sirf students)
$me = current_user();
if ($me && ($me['role'] ?? '') === 'student'):
  $wa_num = preg_replace('/\D+/', '', setting('whatsapp_number', ''));
  $here = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<nav class="dm-bottombar" aria-label="Quick navigation">
  <a href="index.php" class="<?= $here === 'index.php' ? 'active' : '' ?>"><?= icon('home', 22) ?>Home</a>
  <a href="courses.php" class="<?= $here === 'courses.php' ? 'active' : '' ?>"><?= icon('book', 22) ?>Courses</a>
  <a href="announcements.php" class="<?= $here === 'announcements.php' ? 'active' : '' ?>"><?= icon('bell', 22) ?>Notices</a>
  <?php if ($wa_num): ?>
  <a href="https://wa.me/<?= e($wa_num) ?>?text=Assalam-o-Alaikum,%20mujhe%20LMS%20me%20help%20chahiye" target="_blank" rel="noopener"><?= icon('phone', 22) ?>WhatsApp Help</a>
  <?php endif; ?>
</nav>
<?php endif; ?>
<?= (current_user() && current_user()['role'] === 'student') ? whatsapp_float() : '' ?>
<script>
// delete confirmations
document.querySelectorAll('[data-confirm]').forEach(function(el){
  el.addEventListener('click', function(ev){
    if(!confirm(el.getAttribute('data-confirm'))) ev.preventDefault();
  });
});
// ESC se mobile drawer band
document.addEventListener('keydown', function(ev){
  if (ev.key === 'Escape') document.body.classList.remove('dm-open');
});
</script>
</body>
</html>
