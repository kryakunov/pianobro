<?php

declare(strict_types=1);

use PianoTrainer\AppUrl;

/** @var array<string, mixed> $paymentData */
$paymentData = is_array($page['paymentPage'] ?? null) ? $page['paymentPage'] : [];
$plans = is_array($paymentData['plans'] ?? null) ? $paymentData['plans'] : [];
$features = is_array($paymentData['features'] ?? null) ? $paymentData['features'] : [];
$seller = is_array($paymentData['seller'] ?? null) ? $paymentData['seller'] : [];
$sellerInn = htmlspecialchars((string) ($seller['inn'] ?? '662844166191'), ENT_QUOTES, 'UTF-8');
$sellerName = htmlspecialchars((string) ($seller['name'] ?? 'Самозанятый Крякунов Андрей Сергеевич'), ENT_QUOTES, 'UTF-8');
$sellerEmail = htmlspecialchars((string) ($seller['email'] ?? 'support@pianobro.ru'), ENT_QUOTES, 'UTF-8');
$siteUrl = htmlspecialchars(AppUrl::canonical('/'), ENT_QUOTES, 'UTF-8');
$offerDate = '28.08.2026';
?>
<article class="payment-legal">
  <header class="payment-legal__header">
    <h1 class="payment-legal__title">Оплата подписки PianoBro</h1>
    <p class="payment-legal__lead">
      На этой странице размещены сведения об услугах, фиксированных ценах, порядке получения цифрового доступа
      и условиях публичной оферты для приёма платежей через ЮKassa.
    </p>
  </header>

  <section class="payment-legal__section" aria-labelledby="payment-services-title">
    <h2 class="payment-legal__h2" id="payment-services-title">Услуги и цены</h2>
    <p class="payment-legal__p">
      PianoBro — онлайн-сервис (цифровая услуга) для тренировки чтения нот на фортепиано.
      Оплата предоставляет доступ к расширенным функциям тренажёра на сайте
      <a href="<?= $siteUrl ?>">pianobro.ru</a> на фиксированный срок.
    </p>
    <div class="payment-legal__plans">
      <?php foreach ($plans as $plan): ?>
        <article class="payment-legal__plan<?= !empty($plan['featured']) ? ' payment-legal__plan--featured' : '' ?>">
          <?php if (!empty($plan['badge'])): ?>
            <span class="payment-legal__badge"><?= htmlspecialchars((string) $plan['badge'], ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
          <h3 class="payment-legal__plan-title"><?= htmlspecialchars((string) ($plan['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
          <p class="payment-legal__plan-price"><?= (int) ($plan['priceRub'] ?? 0) ?>&nbsp;₽</p>
          <p class="payment-legal__plan-desc"><?= htmlspecialchars((string) ($plan['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
          <?php if (($plan['id'] ?? '') === 'yearly'): ?>
            <p class="payment-legal__plan-note">≈<?= (int) ($plan['monthlyEquivalentRub'] ?? 0) ?>&nbsp;₽ в месяц</p>
          <?php endif; ?>
          <button type="button" class="btn <?= !empty($plan['featured']) ? 'btn--primary' : 'btn--secondary' ?> payment-legal__buy" data-plan-id="<?= htmlspecialchars((string) ($plan['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars((string) ($plan['buttonLabel'] ?? 'Оплатить'), ENT_QUOTES, 'UTF-8') ?>
          </button>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="payment-legal__section" aria-labelledby="payment-includes-title">
    <h2 class="payment-legal__h2" id="payment-includes-title">Что входит в подписку</h2>
    <ul class="payment-legal__list">
      <?php foreach ($features as $feature): ?>
        <li><?= htmlspecialchars((string) $feature, ENT_QUOTES, 'UTF-8') ?></li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="payment-legal__section" aria-labelledby="payment-delivery-title">
    <h2 class="payment-legal__h2" id="payment-delivery-title">Получение услуги после оплаты</h2>
    <p class="payment-legal__p">PianoBro оказывает <strong>цифровую услугу</strong> — доступ к функциям онлайн-тренажёра. Физическая доставка не требуется.</p>
    <ol class="payment-legal__list payment-legal__list--ordered">
      <li>Выберите тариф и нажмите кнопку оплаты на этой странице.</li>
      <li>Авторизуйтесь на сайте или зарегистрируйте аккаунт (e-mail и пароль).</li>
      <li>Оплатите заказ банковской картой через защищённую страницу ЮKassa.</li>
      <li>После успешной оплаты подписка активируется автоматически в вашем аккаунте — обычно в течение нескольких минут.</li>
      <li>Войдите на сайт под тем же e-mail и пользуйтесь персональными тренировками, статистикой и безлимитными занятиями на оплаченный период.</li>
    </ol>
    <p class="payment-legal__p">
      Если доступ не активировался в течение 24 часов, напишите на
      <a href="mailto:<?= $sellerEmail ?>"><?= $sellerEmail ?></a> с указанием e-mail аккаунта и даты оплаты.
    </p>
  </section>

  <section class="payment-legal__section" aria-labelledby="payment-methods-title">
    <h2 class="payment-legal__h2" id="payment-methods-title">Способы оплаты</h2>
    <p class="payment-legal__p">
      Оплата принимается через сервис ЮKassa (ООО «ЮMoney», лицензия ЦБ РФ).
      Доступны банковские карты Visa, Mastercard, Мир и другие способы, доступные на странице оплаты ЮKassa.
      Все цены указаны в рублях РФ и включают применимые налоги исполнителя.
    </p>
  </section>

  <section class="payment-legal__section payment-legal__offer" aria-labelledby="payment-offer-title">
    <h2 class="payment-legal__h2" id="payment-offer-title">Публичная оферта</h2>
    <p class="payment-legal__p payment-legal__meta">Редакция от <?= $offerDate ?></p>

    <h3 class="payment-legal__h3">1. Общие положения</h3>
    <p class="payment-legal__p">
      Настоящий документ является официальным предложением (публичной офертой)
      <?= $sellerName ?>, применяющий специальный налоговый режим «Налог на профессиональный доход» (ИНН <?= $sellerInn ?>, далее — «Исполнитель») заключить договор
      на оказание услуг с любым дееспособным физическим лицом (далее — «Пользователь»),
      принявшим условия настоящей оферты.
    </p>
    <p class="payment-legal__p">
      Акцептом оферты считается оплата выбранного тарифа на странице
      <a href="<?= htmlspecialchars(AppUrl::canonical('/payment'), ENT_QUOTES, 'UTF-8') ?>">pianobro.ru/payment</a>.
    </p>

    <h3 class="payment-legal__h3">2. Предмет договора</h3>
    <p class="payment-legal__p">
      Исполнитель предоставляет Пользователю доступ к онлайн-тренажёру нот PianoBro на сайте pianobro.ru
      в объёме, соответствующем оплаченному тарифу, на ограниченный срок (1 месяц, 3 месяца или 1 год).
    </p>

    <h3 class="payment-legal__h3">3. Стоимость и порядок оплаты</h3>
    <p class="payment-legal__p">Стоимость услуг указана на странице оплаты:</p>
    <ul class="payment-legal__list">
      <?php foreach ($plans as $plan): ?>
        <li><?= htmlspecialchars((string) ($plan['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?> — <?= (int) ($plan['priceRub'] ?? 0) ?>&nbsp;₽</li>
      <?php endforeach; ?>
    </ul>
    <p class="payment-legal__p">
      Оплата производится в полном объёме до начала оказания услуги через платёжный сервис ЮKassa.
      Автопродление подписки не подключается.
    </p>

    <h3 class="payment-legal__h3">4. Порядок оказания услуги</h3>
    <p class="payment-legal__p">
      Услуга оказывается дистанционно через сеть Интернет. Доступ предоставляется в личном аккаунте Пользователя
      после подтверждения успешной оплаты платёжной системой.
    </p>

    <h3 class="payment-legal__h3">5. Права и обязанности сторон</h3>
    <p class="payment-legal__p">
      Исполнитель обязуется обеспечить доступ к оплаченному функционалу в течение оплаченного периода,
      за исключением периодов технических работ. Пользователь обязуется не передавать доступ третьим лицам
      и использовать сервис в соответствии с законодательством РФ.
    </p>

    <h3 class="payment-legal__h3">6. Возврат средств</h3>
    <p class="payment-legal__p">
      В соответствии с законодательством РФ, услуга относится к цифровому контенту/дистанционному сервису,
      предоставляемому с согласия Пользователя. Исполнитель применяет налог на профессиональный доход (НПД).
      Если доступ не был предоставлен по вине Исполнителя,
      Пользователь вправе обратиться на <?= $sellerEmail ?> для рассмотрения вопроса о возврате.
      В иных случаях возврат после активации доступа не производится.
    </p>

    <h3 class="payment-legal__h3">7. Персональные данные</h3>
    <p class="payment-legal__p">
      При регистрации и оплате обрабатываются e-mail, имя и технические данные, необходимые для оказания услуги
      и проведения платежа. Данные не передаются третьим лицам, за исключением платёжного провайдера ЮKassa
      в объёме, необходимом для проведения оплаты.
    </p>

    <h3 class="payment-legal__h3">8. Реквизиты и контакты Исполнителя</h3>
    <ul class="payment-legal__list payment-legal__contacts">
      <li>Исполнитель: <?= $sellerName ?></li>
      <li>ИНН: <?= $sellerInn ?></li>
      <li>Сайт: <a href="<?= $siteUrl ?>">pianobro.ru</a></li>
      <li>E-mail: <a href="mailto:<?= $sellerEmail ?>"><?= $sellerEmail ?></a></li>
    </ul>
  </section>

  <p class="payment-legal__notice" id="payment-status" hidden></p>
</article>
