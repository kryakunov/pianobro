<?php

declare(strict_types=1);

use PianoTrainer\Env;

$metrikaId = Env::get('YANDEX_METRIKA_ID', '110624464');
if ($metrikaId === '') {
  return;
}
?>
<!-- Yandex.Metrika counter -->
<script type="text/javascript">
  window.__METRIKA_ID__ = <?= (int) $metrikaId ?>;
  (function(m,e,t,r,i,k,a){
    m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
    m[i].l=1*new Date();
    for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
    k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
  })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=<?= htmlspecialchars($metrikaId, ENT_QUOTES, 'UTF-8') ?>', 'ym');

  ym(<?= (int) $metrikaId ?>, 'init', {
    defer: true,
    clickmap: true,
    trackLinks: true,
    accurateTrackBounce: true,
    webvisor: true,
    triggerEvent: true
  });
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/<?= (int) $metrikaId ?>" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->
