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
$pageHeadingTag = (($page['screen'] ?? '') === 'payment') ? 'h1' : 'h2';
?>
<article class="payment-legal">
  <header class="payment-legal__header payment-legal__header--compact">
    <<?= $pageHeadingTag ?> class="payment-legal__title">Оплата подписки PianoBro</<?= $pageHeadingTag ?>>
  </header>

  <section class="payment-legal__section payment-legal__section--pricing" aria-labelledby="payment-services-title">
    <p class="payment-legal__notice" id="payment-status" hidden role="status" aria-live="polite"></p>
    <div class="payment-legal__plans">
      <?php foreach ($plans as $plan): ?>
        <?php
          $isFeatured = !empty($plan['featured']);
          $planId = htmlspecialchars((string) $plan['id'], ENT_QUOTES, 'UTF-8');
          $shortName = htmlspecialchars((string) ($plan['shortName'] ?? $plan['name']), ENT_QUOTES, 'UTF-8');
          $priceRub = (int) $plan['priceRub'];
          $monthlyRub = (int) ($plan['monthlyEquivalentRub'] ?? $priceRub);
          $durationDays = (int) ($plan['durationDays'] ?? 30);
        ?>
        <article class="payment-legal__plan<?= $isFeatured ? ' payment-legal__plan--featured' : '' ?>">
          <div class="payment-legal__plan-top">
            <?php if (!empty($plan['badge'])): ?>
              <span class="payment-legal__badge"><?= htmlspecialchars((string) $plan['badge'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php else: ?>
              <span class="payment-legal__badge payment-legal__badge--spacer" aria-hidden="true"></span>
            <?php endif; ?>
            <h3 class="payment-legal__plan-title"><?= $shortName ?></h3>
            <p class="payment-legal__plan-period"><?= $durationDays ?> дней доступа</p>
            <div class="payment-legal__plan-price-wrap">
              <span class="payment-legal__plan-price"><?= $priceRub ?></span>
              <span class="payment-legal__plan-currency">₽</span>
            </div>
            <p class="payment-legal__plan-note">≈ <?= $monthlyRub ?>&nbsp;₽ в месяц</p>
          </div>
          <div class="payment-legal__plan-body">
            <p class="payment-legal__plan-desc"><?= htmlspecialchars((string) $plan['description'], ENT_QUOTES, 'UTF-8') ?></p>
          </div>
          <div class="payment-legal__plan-foot">
            <button
              type="button"
              class="btn <?= $isFeatured ? 'btn--primary' : 'btn--secondary' ?> payment-legal__buy"
              data-plan-id="<?= $planId ?>"
            >
              <?= htmlspecialchars((string) $plan['buttonLabel'], ENT_QUOTES, 'UTF-8') ?>
            </button>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="payment-legal__intro">
      <p class="payment-legal__lead">
        На этой странице размещены сведения об услугах, фиксированных ценах и порядке получения цифрового доступа
        для приёма платежей через ЮKassa. Условия оказания услуг — в
        <a href="<?= $offerUrl ?>">публичной оферте</a>.
      </p>
      <h2 class="payment-legal__h2" id="payment-services-title">Услуги и цены</h2>
      <p class="payment-legal__p">
        PianoBro — онлайн-сервис (цифровая услуга) для тренировки чтения нот на фортепиано.
        Оплата предоставляет доступ к расширенным функциям тренажёра на сайте
        <a href="<?= $siteUrl ?>">pianobro.ru</a> на фиксированный срок.
      </p>
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
