<?php

declare(strict_types=1);

/** @var int $assetVersion */
?>
<div class="cookie-banner" id="cookie-banner" hidden role="dialog" aria-live="polite" aria-label="Уведомление о cookie">
  <div class="cookie-banner__inner">
    <p class="cookie-banner__text">
      Мы используем cookie для работы сайта и сервисов аналитики (Яндекс.Метрика).
      Нажимая «Принять», вы соглашаетесь с их использованием.
    </p>
    <button type="button" class="btn btn--primary btn--sm cookie-banner__accept" id="cookie-banner-accept">
      Принять
    </button>
  </div>
</div>
<script src="<?= htmlspecialchars(\PianoTrainer\AssetVersion::versionedUrl('/assets/js/cookie-consent.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
