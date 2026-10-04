<?php
/* Smarty version 5.8.4, created on 2026-10-04 04:07:23
  from 'file:index.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.8.4',
  'unifunc' => 'content_6ac1a6cbc79278_38486138',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '2f4299e5dc43aa4d2322ad0afe5a1f5fba5db477' => 
    array (
      0 => 'index.tpl',
      1 => 1791064594,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_6ac1a6cbc79278_38486138 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/var/www/html/landing/templates';
?><!DOCTYPE html>
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
        <li><b><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('total')), ENT_QUOTES, 'UTF-8');?>
</b> <?php if ($_smarty_tpl->getValue('total') == 1) {?>объект<?php } else { ?>объектов<?php }?> в каталоге</li>
        <?php if ($_smarty_tpl->getValue('min_price')) {?><li>от <b><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('min_price')), ENT_QUOTES, 'UTF-8');?>
&nbsp;₽</b></li><?php }?>
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

    <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('items'), 'p');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('p')->value) {
$foreach0DoElse = false;
?>
    <article class="lot" id="lot-<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('p')['id']), ENT_QUOTES, 'UTF-8');?>
">
      <div class="lot__gallery" data-gallery>
        <?php if ($_smarty_tpl->getValue('p')['photos']) {?>
          <img class="lot__main" data-main src="<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('p')['photos'][0]), ENT_QUOTES, 'UTF-8');?>
" alt="Квартира: <?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('p')['address']), ENT_QUOTES, 'UTF-8');?>
" loading="lazy">
          <?php if ($_smarty_tpl->getValue('p')['multi']) {?>
          <div class="lot__thumbs">
            <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('p')['photos'], 'ph');
$_smarty_tpl->getVariable('ph')->iteration = 0;
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('ph')->value) {
$foreach1DoElse = false;
$_smarty_tpl->getVariable('ph')->iteration++;
$foreach1Backup = clone $_smarty_tpl->getVariable('ph');
?>
              <button type="button" data-src="<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('ph')), ENT_QUOTES, 'UTF-8');?>
" aria-label="Фото <?php echo htmlspecialchars((string) ($_smarty_tpl->getVariable('ph')->iteration), ENT_QUOTES, 'UTF-8');?>
"><img src="<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('ph')), ENT_QUOTES, 'UTF-8');?>
" alt="" loading="lazy"></button>
            <?php
$_smarty_tpl->setVariable('ph', $foreach1Backup);
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
          </div>
          <?php }?>
        <?php } else { ?>
          <div class="lot__noimg">Фото скоро появится</div>
        <?php }?>
      </div>

      <div class="lot__body">
        <p class="lot__price"><?php if ($_smarty_tpl->getValue('p')['price']) {
echo htmlspecialchars((string) ($_smarty_tpl->getValue('p')['price']), ENT_QUOTES, 'UTF-8');?>
&nbsp;₽<?php } else { ?>Цена по запросу<?php }?></p>
        <?php if ($_smarty_tpl->getValue('p')['price_m2']) {?><p class="lot__m2"><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('p')['price_m2']), ENT_QUOTES, 'UTF-8');?>
&nbsp;₽ за м²</p><?php }?>
        <h3 class="lot__address"><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('p')['address']), ENT_QUOTES, 'UTF-8');?>
</h3>

        <dl class="specs">
          <div><dt>Площадь</dt><dd><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('p')['area']), ENT_QUOTES, 'UTF-8');?>
&nbsp;м²</dd></div>
          <?php if ($_smarty_tpl->getValue('p')['floor'] !== '') {?><div><dt>Этаж</dt><dd><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('p')['floor']), ENT_QUOTES, 'UTF-8');?>
</dd></div><?php }?>
          <?php if ($_smarty_tpl->getValue('p')['view']) {?><div><dt>Вид из окна</dt><dd><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('p')['view']), ENT_QUOTES, 'UTF-8');?>
</dd></div><?php }?>
          <?php if ($_smarty_tpl->getValue('p')['house']) {?><div><dt>Тип дома</dt><dd><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('p')['house']), ENT_QUOTES, 'UTF-8');?>
</dd></div><?php }?>
          <?php if ($_smarty_tpl->getValue('p')['material']) {?><div><dt>Материал дома</dt><dd><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('p')['material']), ENT_QUOTES, 'UTF-8');?>
</dd></div><?php }?>
          <?php if ($_smarty_tpl->getValue('p')['added']) {?><div><dt>В каталоге с</dt><dd><?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('p')['added']), ENT_QUOTES, 'UTF-8');?>
</dd></div><?php }?>
        </dl>

        <?php if ($_smarty_tpl->getValue('p')['description']) {?>
          <div class="lot__desc"><?php echo $_smarty_tpl->getValue('p')['description'];?>
</div>
        <?php }?>

        <?php if ($_smarty_tpl->getValue('p')['phone_href']) {?>
        <p class="lot__contact">
          <a class="btn btn--small" href="tel:<?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('p')['phone_href']), ENT_QUOTES, 'UTF-8');?>
">Позвонить <?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('p')['phone']), ENT_QUOTES, 'UTF-8');?>
</a>
          <?php if ($_smarty_tpl->getValue('p')['manager']) {?><span>Ваш менеджер: <?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('p')['manager']), ENT_QUOTES, 'UTF-8');?>
</span><?php }?>
        </p>
        <?php }?>
      </div>
    </article>
    <?php
}
if ($foreach0DoElse) {
?>
      <p class="empty">Каталог обновляется — загляните позже.</p>
    <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
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
  <p>© <?php echo htmlspecialchars((string) ($_smarty_tpl->getValue('year')), ENT_QUOTES, 'UTF-8');?>
. Информация на сайте не является публичной офертой.</p>
</footer>

<?php echo '<script'; ?>
 src="/assets/landing.js" defer><?php echo '</script'; ?>
>
</body>
</html>
<?php }
}
