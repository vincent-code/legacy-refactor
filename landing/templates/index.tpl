<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Южный Ключ — квартиры на вторичном рынке Кубани</title>
  <meta name="description" content="Проверенные квартиры на вторичном рынке Краснодарского края: фото, вид из окна, материал и тип дома, честная цена за метр.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,700&family=Manrope:wght@400;500;700&display=swap">
  <link rel="stylesheet" href="/assets/landing.css">
</head>
<body>

<header class="top">
  <a class="brand" href="#top">Южный Ключ</a>
  <nav>
    <a href="#catalog">Каталог</a>
    <a href="#why">Почему мы</a>
    <a href="#steps">Как купить</a>
    <a href="#faq">Вопросы</a>
  </nav>
</header>

<main id="top">
  <section class="hero">
    <div class="hero__inner">
      <h1>Квартиры на юге России, о которых известно всё</h1>
      <p class="hero__lead">Вторичное жильё Краснодарского края: реальные фото, вид из окна, материал и тип дома — до первого звонка.</p>
      <ul class="facts">
        <li><b>{$total}</b> {if $total == 1}объект{else}объектов{/if} в каталоге</li>
        {if $min_price}<li>от <b>{$min_price}&nbsp;₽</b></li>{/if}
        <li><b>Юг</b> Краснодар, Анапа и край</li>
      </ul>
      <a class="btn" href="#catalog">Смотреть квартиры</a>
    </div>
    <svg class="hero__wave" viewBox="0 0 1440 80" preserveAspectRatio="none" aria-hidden="true">
      <path d="M0 40 C 180 90, 360 0, 540 38 S 900 90, 1080 40 S 1320 10, 1440 44 V80 H0Z"/>
    </svg>
  </section>

  <section class="catalog" id="catalog">
    <h2>Квартиры в продаже</h2>

    {foreach $items as $p}
    <article class="lot" id="lot-{$p.id}">
      <div class="lot__gallery" data-gallery>
        {if $p.photos}
          <img class="lot__main" data-main src="{$p.photos[0]}" alt="Квартира: {$p.address}" loading="lazy">
          {if $p.multi}
          <div class="lot__thumbs">
            {foreach $p.photos as $ph}
              <button type="button" data-src="{$ph}" aria-label="Фото {$ph@iteration}"><img src="{$ph}" alt="" loading="lazy"></button>
            {/foreach}
          </div>
          {/if}
        {else}
          <div class="lot__noimg">Фото скоро появится</div>
        {/if}
      </div>

      <div class="lot__body">
        <p class="lot__price">{if $p.price}{$p.price}&nbsp;₽{else}Цена по запросу{/if}</p>
        {if $p.price_m2}<p class="lot__m2">{$p.price_m2}&nbsp;₽ за м²</p>{/if}
        <h3 class="lot__address">{$p.address}</h3>

        <dl class="specs">
          <div><dt>Площадь</dt><dd>{$p.area}&nbsp;м²</dd></div>
          {if $p.floor !== ''}<div><dt>Этаж</dt><dd>{$p.floor}</dd></div>{/if}
          {if $p.view}<div><dt>Вид из окна</dt><dd>{$p.view}</dd></div>{/if}
          {if $p.house}<div><dt>Тип дома</dt><dd>{$p.house}</dd></div>{/if}
          {if $p.material}<div><dt>Материал дома</dt><dd>{$p.material}</dd></div>{/if}
          {if $p.added}<div><dt>В каталоге с</dt><dd>{$p.added}</dd></div>{/if}
        </dl>

        {if $p.description}
          <div class="lot__desc">{$p.description nofilter}</div>
        {/if}

        {if $p.phone_href}
        <p class="lot__contact">
          <a class="btn btn--small" href="tel:{$p.phone_href}">Позвонить {$p.phone}</a>
          {if $p.manager}<span>Ваш менеджер: {$p.manager}</span>{/if}
        </p>
        {/if}
      </div>
    </article>
    {foreachelse}
      <p class="empty">Каталог обновляется — загляните позже.</p>
    {/foreach}
  </section>

  <section class="why" id="why">
    <h2>Почему покупают через нас</h2>
    <div class="why__grid">
      <div><h3>Юридическая чистота</h3><p>Перед публикацией проверяем выписку из ЕГРН, историю собственников и обременения.</p></div>
      <div><h3>Всё видно заранее</h3><p>В карточке — вид из окна, материал и тип дома, цена за метр. Меньше сюрпризов на просмотре.</p></div>
      <div><h3>Сопровождение сделки</h3><p>Помогаем с ипотекой, задатком и регистрацией — от первого просмотра до получения ключей.</p></div>
    </div>
  </section>

  <section class="steps" id="steps">
    <h2>Как купить квартиру</h2>
    <ol>
      <li><b>Выбираете объект.</b> Оставляете заявку или звоните менеджеру из карточки.</li>
      <li><b>Смотрите вживую.</b> Организуем просмотр в удобное время, в том числе по видеосвязи.</li>
      <li><b>Проверяем документы.</b> Юрист изучает право собственности и согласия всех сторон.</li>
      <li><b>Подписываем и регистрируем.</b> Деньги — через безопасную ячейку или аккредитив.</li>
    </ol>
  </section>

  <section class="faq" id="faq">
    <h2>Частые вопросы</h2>
    <details><summary>Можно ли купить в ипотеку?</summary><p>Да, работаем с основными банками и помогаем собрать пакет документов для одобрения.</p></details>
    <details><summary>Что входит в стоимость услуг?</summary><p>Комиссия обсуждается до подписания договора и фиксируется письменно — без скрытых платежей.</p></details>
    <details><summary>Можно ли приехать на просмотр из другого города?</summary><p>Да, мы договоримся об удобной дате и встретим вас — или проведём онлайн-показ.</p></details>
  </section>
</main>

<footer class="foot">
  <p>Южный Ключ, Краснодар, info@example.com</p>
  <p>© {$year}. Информация на сайте не является публичной офертой.</p>
</footer>

<script src="/assets/landing.js" defer></script>
</body>
</html>
