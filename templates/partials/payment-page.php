<?php

declare(strict_types=1);

use PianoTrainer\AppUrl;
use PianoTrainer\PricingConfig;

$plans = PricingConfig::plans();
$features = PricingConfig::subscriptionFeatures();
$seller = PricingConfig::seller();
$sellerEmail = htmlspecialchars($seller['email'], ENT_QUOTES, 'UTF-8');
$siteUrl = htmlspecialchars(AppUrl::canonical('/'), ENT_QUOTES, 'UTF-8');
$offerUrl = htmlspecialchars(AppUrl::canonical('/oferta'), ENT_QUOTES, 'UTF-8');
?>
<article class="payment-legal">
  <header class="payment-legal__header">
    <h1 class="payment-legal__title">Оплата подписки PianoBro</h1>
    <p class="payment-legal__lead">
      На этой странице размещены сведения об услугах, фиксированных ценах и порядке получения цифрового доступа
      для приёма платежей через ЮKassa. Условия оказания услуг — в
      <a href="<?= $offerUrl ?>">публичной оферте</a>.
    </p>
  </header>

  <section class="payment-legal__section" aria-labelledby="payment-services-title">
    <h2 class="payment-legal__h2" id="payment-services-title">Услуги и цены</h2>
    <p class="payment-legal__p">
      PianoBro — онлайн-сервис (цифровая услуга) для тренировки чтения нот на фортепиано.
      Оплата предоставляет доступ к расширенным функциям тренажёра на сайте
      <a href="<?= $siteUrl ?>">pianobro.ru</a> на фиксированный срок.
    </p>
    <p class="payment-legal__notice" id="payment-status" hidden role="status" aria-live="polite"></p>
    <div class="payment-legal__plans">
      <?php foreach ($plans as $plan): ?>
        <article class="payment-legal__plan<?= !empty($plan['featured']) ? ' payment-legal__plan--featured' : '' ?>">
          <?php if (!empty($plan['badge'])): ?>
            <span class="payment-legal__badge"><?= htmlspecialchars((string) $plan['badge'], ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
          <h3 class="payment-legal__plan-title"><?= htmlspecialchars((string) $plan['name'], ENT_QUOTES, 'UTF-8') ?></h3>
          <p class="payment-legal__plan-price"><?= (int) $plan['priceRub'] ?>&nbsp;₽</p>
          <p class="payment-legal__plan-desc"><?= htmlspecialchars((string) $plan['description'], ENT_QUOTES, 'UTF-8') ?></p>
          <?php if (($plan['id'] ?? '') === 'yearly'): ?>
            <p class="payment-legal__plan-note">≈<?= (int) $plan['monthlyEquivalentRub'] ?>&nbsp;₽ в месяц</p>
          <?php endif; ?>
          <button type="button" class="btn <?= !empty($plan['featured']) ? 'btn--primary' : 'btn--secondary' ?> payment-legal__buy" data-plan-id="<?= htmlspecialchars((string) $plan['id'], ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars((string) $plan['buttonLabel'], ENT_QUOTES, 'UTF-8') ?>
          </button>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="payment-legal__section" aria-labelledby="payment-includes-title">
    <h2 class="payment-legal__h2" id="payment-includes-title">Что входит в подписку</h2>
    <ul class="payment-legal__list">
      <?php foreach ($features as $feature): ?>
        <li><?= htmlspecialchars($feature, ENT_QUOTES, 'UTF-8') ?></li>
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
      Доступны банковские карты Visa, Mastercard, «Мир» и другие способы, доступные на странице оплаты ЮKassa.
      Все цены указаны в рублях РФ. Исполнитель применяет налог на профессиональный доход (НПД).
    </p>
  </section>

  <section class="payment-legal__section" aria-labelledby="payment-faq-title">
    <h2 class="payment-legal__h2" id="payment-faq-title">Частые вопросы</h2>
    <details class="payment-faq__item">
      <summary>Подходит ли PianoBro для начинающих?</summary>
      <p>Да. Можно начать с простых упражнений, даже если вы только знакомитесь с нотами.</p>
    </details>
    <details class="payment-faq__item">
      <summary>Подойдёт ли ребёнку?</summary>
      <p>Да, если ребёнок уже начал изучать ноты или занимается музыкой. Тренировки короткие и понятные.</p>
    </details>
    <details class="payment-faq__item">
      <summary>Заменяет ли PianoBro преподавателя?</summary>
      <p>Нет. PianoBro тренирует конкретный навык — быстро узнавать ноты и связывать их с клавиатурой. Его можно использовать самостоятельно или вместе с уроками.</p>
    </details>
    <details class="payment-faq__item">
      <summary>Зачем платить, если есть бесплатные тренажёры?</summary>
      <p>Бесплатные тренажёры обычно дают случайные задания. PianoBro запоминает, какие ноты вы путаете, и строит тренировку под ваши слабые места.</p>
    </details>
    <details class="payment-faq__item">
      <summary>Можно ли отменить подписку?</summary>
      <p>Оплата разовая за выбранный период (1 месяц, 3 месяца или год). Автопродление не подключается — по окончании срока доступ к платным функциям завершится, продлить можно в любой момент на этой странице.</p>
    </details>
  </section>

</article>
