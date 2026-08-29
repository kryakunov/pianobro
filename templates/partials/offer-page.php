<?php

declare(strict_types=1);

use PianoTrainer\AppUrl;
use PianoTrainer\PricingConfig;

$plans = PricingConfig::plans();
$seller = PricingConfig::seller();
$sellerInn = htmlspecialchars($seller['inn'], ENT_QUOTES, 'UTF-8');
$sellerName = htmlspecialchars($seller['name'], ENT_QUOTES, 'UTF-8');
$sellerEmail = htmlspecialchars($seller['email'], ENT_QUOTES, 'UTF-8');
$siteUrl = htmlspecialchars(AppUrl::canonical('/'), ENT_QUOTES, 'UTF-8');
$paymentUrl = htmlspecialchars(AppUrl::canonical('/payment'), ENT_QUOTES, 'UTF-8');
$offerDate = '28.08.2026';
?>
<article class="payment-legal payment-legal--offer">
  <header class="payment-legal__header">
    <h1 class="payment-legal__title">Публичная оферта</h1>
    <p class="payment-legal__lead payment-legal__meta">Редакция от <?= $offerDate ?></p>
  </header>

  <section class="payment-legal__section payment-legal__offer" aria-labelledby="offer-general-title">
    <h2 class="payment-legal__h2" id="offer-general-title">1. Общие положения</h2>
    <p class="payment-legal__p">
      Настоящий документ является официальным предложением (публичной офертой)
      <?= $sellerName ?>, применяющего специальный налоговый режим «Налог на профессиональный доход»
      (ИНН <?= $sellerInn ?>, далее — «Исполнитель») заключить договор
      на оказание услуг с любым дееспособным физическим лицом (далее — «Пользователь»),
      принявшим условия настоящей оферты.
    </p>
    <p class="payment-legal__p">
      Акцептом оферты считается оплата выбранного тарифа на странице
      <a href="<?= $paymentUrl ?>">pianobro.ru/payment</a>.
    </p>
  </section>

  <section class="payment-legal__section" aria-labelledby="offer-subject-title">
    <h2 class="payment-legal__h2" id="offer-subject-title">2. Предмет договора</h2>
    <p class="payment-legal__p">
      Исполнитель предоставляет Пользователю доступ к онлайн-тренажёру нот PianoBro на сайте pianobro.ru
      в объёме, соответствующем оплаченному тарифу, на ограниченный срок (1 месяц, 3 месяца или 1 год).
    </p>
  </section>

  <section class="payment-legal__section" aria-labelledby="offer-price-title">
    <h2 class="payment-legal__h2" id="offer-price-title">3. Стоимость и порядок оплаты</h2>
    <p class="payment-legal__p">Стоимость услуг указана на <a href="<?= $paymentUrl ?>">странице оплаты</a>:</p>
    <ul class="payment-legal__list">
      <?php foreach ($plans as $plan): ?>
        <li><?= htmlspecialchars((string) $plan['name'], ENT_QUOTES, 'UTF-8') ?> — <?= (int) $plan['priceRub'] ?>&nbsp;₽</li>
      <?php endforeach; ?>
    </ul>
    <p class="payment-legal__p">
      Оплата производится в полном объёме до начала оказания услуги через платёжный сервис ЮKassa.
      Автопродление подписки не подключается.
    </p>
  </section>

  <section class="payment-legal__section" aria-labelledby="offer-delivery-title">
    <h2 class="payment-legal__h2" id="offer-delivery-title">4. Порядок оказания услуги</h2>
    <p class="payment-legal__p">
      Услуга оказывается дистанционно через сеть Интернет. Доступ предоставляется в личном аккаунте Пользователя
      после подтверждения успешной оплаты платёжной системой.
    </p>
  </section>

  <section class="payment-legal__section" aria-labelledby="offer-rights-title">
    <h2 class="payment-legal__h2" id="offer-rights-title">5. Права и обязанности сторон</h2>
    <p class="payment-legal__p">
      Исполнитель обязуется обеспечить доступ к оплаченному функционалу в течение оплаченного периода,
      за исключением периодов технических работ. Пользователь обязуется не передавать доступ третьим лицам
      и использовать сервис в соответствии с законодательством РФ.
    </p>
  </section>

  <section class="payment-legal__section" aria-labelledby="offer-refund-title">
    <h2 class="payment-legal__h2" id="offer-refund-title">6. Возврат средств</h2>
    <p class="payment-legal__p">
      В соответствии с законодательством РФ, услуга относится к цифровому контенту/дистанционному сервису,
      предоставляемому с согласия Пользователя. Исполнитель применяет налог на профессиональный доход (НПД).
      Если доступ не был предоставлен по вине Исполнителя,
      Пользователь вправе обратиться на <?= $sellerEmail ?> для рассмотрения вопроса о возврате.
      В иных случаях возврат после активации доступа не производится.
    </p>
  </section>

  <section class="payment-legal__section" aria-labelledby="offer-privacy-title">
    <h2 class="payment-legal__h2" id="offer-privacy-title">7. Персональные данные</h2>
    <p class="payment-legal__p">
      При регистрации и оплате обрабатываются e-mail, имя и технические данные, необходимые для оказания услуги
      и проведения платежа. Данные не передаются третьим лицам, за исключением платёжного провайдера ЮKassa
      в объёме, необходимом для проведения оплаты.
    </p>
  </section>

  <section class="payment-legal__section" aria-labelledby="offer-contacts-title">
    <h2 class="payment-legal__h2" id="offer-contacts-title">8. Реквизиты и контакты Исполнителя</h2>
    <ul class="payment-legal__list payment-legal__contacts">
      <li>Исполнитель: <?= $sellerName ?></li>
      <li>ИНН: <?= $sellerInn ?></li>
      <li>Сайт: <a href="<?= $siteUrl ?>">pianobro.ru</a></li>
      <li>E-mail: <a href="mailto:<?= $sellerEmail ?>"><?= $sellerEmail ?></a></li>
    </ul>
  </section>

  <p class="payment-legal__p">
    <a href="<?= $paymentUrl ?>">← Вернуться к оплате подписки</a>
  </p>
</article>
