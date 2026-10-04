// Галерея: клик по миниатюре подменяет основное фото в своей карточке
document.querySelectorAll('[data-gallery]').forEach(function (g) {
  var main = g.querySelector('[data-main]');
  var thumbs = g.querySelectorAll('button[data-src]');
  if (!main || !thumbs.length) { return; }
  thumbs[0].setAttribute('aria-current', 'true');
  thumbs.forEach(function (b) {
    b.addEventListener('click', function () {
      main.src = b.getAttribute('data-src');
      thumbs.forEach(function (x) { x.removeAttribute('aria-current'); });
      b.setAttribute('aria-current', 'true');
    });
  });
});
