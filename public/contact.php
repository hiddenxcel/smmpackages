<?php
require_once __DIR__ . '/includes/bootstrap.php';

$waUrl    = $config['links']['whatsapp_url']  ?? '#';
$tgUrl    = $config['links']['telegram_url']  ?? '';
$supEmail = $config['links']['support_email'] ?? 'support@smmpackages.com';

$sent = false;
$errors = [];
$old = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();

    $old['name']    = trim($_POST['name']    ?? '');
    $old['email']   = trim($_POST['email']   ?? '');
    $old['subject'] = trim($_POST['subject'] ?? '');
    $old['message'] = trim($_POST['message'] ?? '');

    if ($old['name'] === '')                                        $errors['name']    = true;
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))         $errors['email']   = true;
    if (mb_strlen($old['message']) < 5)                           $errors['message'] = true;

    if (!$errors) {
        // Best-effort delivery; the page never leaks mail failures to the visitor.
        $subject = 'Contact form: ' . ($old['subject'] !== '' ? $old['subject'] : 'New message');
        $body    = "Name: {$old['name']}\nEmail: {$old['email']}\n\n{$old['message']}";
        $headers = 'From: ' . $supEmail . "\r\n"
                 . 'Reply-To: ' . $old['email'] . "\r\n"
                 . 'Content-Type: text/plain; charset=UTF-8';
        @mail($supEmail, $subject, $body, $headers);

        $sent = true;
        $old  = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
    }
}

$pageTitle = Lang::t('contact_title');
$activeNav = 'contact';
require __DIR__ . '/includes/layout_header.php';

$methods = [
    ['icon' => 'fa-brands fa-whatsapp', 'accent' => false, 'title' => 'WhatsApp',                 'body' => 'contact_wa_body',    'href' => $waUrl,                   'show' => $waUrl !== '#' && $waUrl !== ''],
    ['icon' => 'fa-solid fa-envelope',  'accent' => true,  'title' => 'Email',                    'body' => 'contact_email_body', 'href' => 'mailto:' . $supEmail,    'show' => true, 'meta' => $supEmail],
    ['icon' => 'fa-brands fa-telegram', 'accent' => false, 'title' => Lang::t('contact_tg_title'), 'body' => 'contact_tg_body',    'href' => $tgUrl,                   'show' => $tgUrl !== ''],
    ['icon' => 'fa-solid fa-clock',     'accent' => true,  'title' => Lang::t('contact_hours_title'), 'body' => 'contact_hours_body', 'href' => null,                'show' => true],
];
?>

<!-- CONTACT HERO -->
<header class="hero" style="padding-bottom:40px">
  <div class="hero-orb"></div>
  <div class="container" style="max-width:760px;text-align:center;position:relative;z-index:1">
    <span class="eyebrow fade-up"><i class="fa-solid fa-comments"></i> <?php e('contact_eyebrow'); ?></span>
    <h1 class="fade-up delay-1"><?php e('contact_title'); ?></h1>
    <p class="sub fade-up delay-2" style="margin-left:auto;margin-right:auto"><?php e('contact_sub'); ?></p>
  </div>
</header>

<!-- CONTACT METHODS + FORM -->
<section class="section" style="padding-top:20px">
  <div class="container">
    <div class="contact-layout">

      <!-- Left: reach-us cards -->
      <div class="contact-methods">
        <?php $d = 0; foreach ($methods as $m): if (!$m['show']) continue; $delay = $d++ ? ' delay-' . min($d - 1, 3) : ''; ?>
          <?php $tag = $m['href'] ? 'a' : 'div'; ?>
          <<?= $tag ?> class="card contact-card fade-up<?= $delay ?>"<?php if ($m['href']): ?> href="<?= htmlspecialchars($m['href']) ?>"<?php if (strpos($m['href'], 'http') === 0): ?> target="_blank" rel="noopener"<?php endif; ?><?php endif; ?>>
            <div class="card-icon<?= $m['accent'] ? ' accent' : '' ?>"><i class="<?= $m['icon'] ?>"></i></div>
            <div class="contact-card-body">
              <h3><?= htmlspecialchars($m['title']) ?></h3>
              <p><?php e($m['body']); ?></p>
              <?php if (!empty($m['meta'])): ?><span class="contact-meta"><?= htmlspecialchars($m['meta']) ?></span><?php endif; ?>
            </div>
            <?php if ($m['href']): ?><i class="fa-solid fa-arrow-right contact-arrow"></i><?php endif; ?>
          </<?= $tag ?>>
        <?php endforeach; ?>
      </div>

      <!-- Right: message form -->
      <div class="card contact-form-card fade-up delay-1">
        <h3 class="contact-form-title"><?php e('contact_form_title'); ?></h3>
        <p class="contact-form-sub"><?php e('contact_form_sub'); ?></p>

        <?php if ($sent): ?>
          <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <span><?php e('contact_ok'); ?></span></div>
        <?php elseif ($errors): ?>
          <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <span><?php e('contact_err'); ?></span></div>
        <?php endif; ?>

        <form method="post" novalidate>
          <?= Csrf::field() ?>
          <div class="grid grid-2" style="gap:0 16px">
            <div class="form-group">
              <label for="c-name"><?php e('contact_f_name'); ?></label>
              <input class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>" id="c-name" name="name"
                     value="<?= htmlspecialchars($old['name']) ?>" placeholder="<?= htmlspecialchars(Lang::t('contact_f_name_ph')) ?>" required>
            </div>
            <div class="form-group">
              <label for="c-email"><?php e('contact_f_email'); ?></label>
              <input class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>" id="c-email" name="email" type="email"
                     value="<?= htmlspecialchars($old['email']) ?>" placeholder="<?= htmlspecialchars(Lang::t('contact_f_email_ph')) ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label for="c-subject"><?php e('contact_f_subject'); ?></label>
            <input class="form-control" id="c-subject" name="subject"
                   value="<?= htmlspecialchars($old['subject']) ?>" placeholder="<?= htmlspecialchars(Lang::t('contact_f_subj_ph')) ?>">
          </div>
          <div class="form-group">
            <label for="c-message"><?php e('contact_f_message'); ?></label>
            <textarea class="form-control<?= isset($errors['message']) ? ' is-invalid' : '' ?>" id="c-message" name="message" rows="5"
                      placeholder="<?= htmlspecialchars(Lang::t('contact_f_msg_ph')) ?>" required><?= htmlspecialchars($old['message']) ?></textarea>
          </div>
          <button class="btn btn-primary btn-block btn-lg" type="submit">
            <i class="fa-solid fa-paper-plane"></i> <?php e('contact_f_send'); ?>
          </button>
          <p class="form-hint" style="text-align:center;margin-top:12px"><?php e('contact_f_note'); ?></p>
        </form>
      </div>

    </div>
  </div>
</section>

<!-- MINI FAQ -->
<section class="section section-alt">
  <div class="container" style="max-width:760px">
    <h2 class="section-title fade-up"><?php e('faq_title'); ?></h2>
    <div style="margin-top:32px">
      <?php for ($i = 1; $i <= 4; $i++): ?>
      <details class="faq-item fade-up" <?= $i === 1 ? 'open' : '' ?>>
        <summary><?php e("faq_q{$i}"); ?></summary>
        <div class="faq-body"><?php e("faq_a{$i}"); ?></div>
      </details>
      <?php endfor; ?>
    </div>
    <div style="text-align:center;margin-top:32px">
      <a class="btn btn-outline" href="faq.php"><?php e('nav_faq'); ?> <i class="fa-solid fa-arrow-right"></i></a>
    </div>
  </div>
</section>

<!-- FINAL CTA -->
<section class="section">
  <div class="container">
    <div class="cta-band fade-up">
      <h2><?php e('cta_final_title'); ?></h2>
      <p><?php e('cta_final_sub'); ?></p>
      <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;position:relative;z-index:1">
        <a class="btn btn-accent btn-lg" href="register.php"><i class="fa-solid fa-rocket"></i> <?php e('cta_start_free'); ?></a>
        <?php if ($waUrl !== '#' && $waUrl !== ''): ?>
        <a class="btn btn-ghost btn-lg" href="<?= htmlspecialchars($waUrl) ?>"><i class="fa-brands fa-whatsapp"></i> <?php e('cta_whatsapp'); ?></a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
