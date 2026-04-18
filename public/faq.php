<?php
declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../app/helpers/auth.php';

$u = current_user();
layout_header(__('faq_title'), $u);
?>

<section class="card" style="padding: 40px; margin-bottom: 30px;">
    <div style="text-align: center; margin-bottom: 40px;">
        <h1 style="font-size: 2.5rem; font-weight: 900; margin-bottom: 10px;">❓ <?= __('faq_title') ?></h1>
        <p class="muted"><?= __('faq_subtitle') ?></p>
    </div>

    <div class="faq-container" style="display: flex; flex-direction: column; gap: 20px;">
        <div class="faq-item card" style="background: var(--surface2); border: 1px solid var(--line); padding: 20px;">
            <h3 style="margin: 0 0 10px; color: var(--accent);">🚀 <?= __('faq_q1') ?></h3>
            <p class="muted" style="margin: 0;"><?= __('faq_a1') ?></p>
        </div>
        <div class="faq-item card" style="background: var(--surface2); border: 1px solid var(--line); padding: 20px;">
            <h3 style="margin: 0 0 10px; color: var(--accent);">💳 <?= __('faq_q2') ?></h3>
            <p class="muted" style="margin: 0;"><?= __('faq_a2') ?></p>
        </div>
        <div class="faq-item card" style="background: var(--surface2); border: 1px solid var(--line); padding: 20px;">
            <h3 style="margin: 0 0 10px; color: var(--accent);">🛡️ <?= __('faq_q3') ?></h3>
            <p class="muted" style="margin: 0;"><?= __('faq_a3') ?></p>
        </div>
        <div class="faq-item card" style="background: var(--surface2); border: 1px solid var(--line); padding: 20px;">
            <h3 style="margin: 0 0 10px; color: var(--accent);">👨‍💻 <?= __('faq_q4') ?></h3>
            <p class="muted" style="margin: 0;"><?= __('faq_a4') ?></p>
        </div>
    </div>
</section>

<div style="text-align: center; margin-bottom: 50px;">
    <a href="index.php" class="btn btn-primary"><?= __('btn_back_home') ?></a>
</div>

<?php layout_footer(); ?>
