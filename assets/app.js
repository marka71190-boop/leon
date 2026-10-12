// Leon_pro — небольшие интерактивные элементы
(function () {
  // Степпер количества
  document.querySelectorAll('[data-stepper]').forEach(function (box) {
    var step = parseInt(box.dataset.step, 10) || 1;
    var pack = parseInt(box.dataset.pack, 10) || 0;
    var input = box.querySelector('input');
    var pieces = box.parentNode.querySelector('[data-pieces]');
    function showPieces() {
      if (pieces && pack) pieces.textContent = '= ' + ((parseInt(input.value, 10) || 0) * pack).toLocaleString('ru-RU') + ' шт.';
    }
    function set(v) {
      v = Math.max(step, Math.round(v / step) * step);
      input.value = v;
      showPieces();
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }
    input.addEventListener('input', showPieces);
    box.querySelector('[data-dec]').addEventListener('click', function () { set((parseInt(input.value, 10) || 0) - step); });
    box.querySelector('[data-inc]').addEventListener('click', function () { set((parseInt(input.value, 10) || 0) + step); });
    input.addEventListener('blur', function () { set(parseInt(input.value, 10) || step); });
  });

  // Галерея фото товара: клик по миниатюре меняет большое фото, стрелки ← → листают
  document.querySelectorAll('[data-gallery]').forEach(function (g) {
    var main = g.querySelector('[data-gallery-main]');
    var btns = Array.prototype.slice.call(g.querySelectorAll('.thumb-btn'));
    if (!main || btns.length < 2) return;
    function show(i) {
      i = (i + btns.length) % btns.length;
      main.src = btns[i].dataset.src;
      btns.forEach(function (b, k) { b.classList.toggle('on', k === i); if (k === i) b.setAttribute('aria-current', 'true'); else b.removeAttribute('aria-current'); });
      return i;
    }
    var cur = 0;
    btns.forEach(function (b, k) { b.addEventListener('click', function () { cur = show(k); }); });
    g.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowRight') { cur = show(cur + 1); btns[cur].focus(); }
      if (e.key === 'ArrowLeft') { cur = show(cur - 1); btns[cur].focus(); }
    });
    // Свайп по большому фото на телефоне
    var x0 = null;
    main.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
    main.addEventListener('touchend', function (e) {
      if (x0 === null) return;
      var dx = e.changedTouches[0].clientX - x0;
      if (Math.abs(dx) > 40) cur = show(cur + (dx < 0 ? 1 : -1));
      x0 = null;
    });
  });

  // Автосохранение количества в корзине
  document.querySelectorAll('[data-autosubmit]').forEach(function (form) {
    var t;
    form.addEventListener('change', function () {
      clearTimeout(t);
      t = setTimeout(function () { form.submit(); }, 500);
    });
  });

  // Фильтры каталога свёрнуты на телефоне
  var filters = document.querySelector('[data-filters]');
  if (filters && window.innerWidth < 760) filters.removeAttribute('open');

  // Мобильное меню
  var burger = document.querySelector('[data-burger]');
  var nav = document.querySelector('[data-nav]');
  if (burger && nav) {
    burger.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // Вкладки
  document.querySelectorAll('[data-tabs]').forEach(function (wrap) {
    var tabs = wrap.querySelectorAll('[role="tab"]');
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabs.forEach(function (t) {
          var on = t === tab;
          t.setAttribute('aria-selected', on ? 'true' : 'false');
          document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
        });
      });
    });
  });

  // Кнопка отправки заявки: активна при выполнении условий
  var checkout = document.querySelector('[data-checkout]');
  if (checkout) {
    var btn = document.querySelector('[data-submit]');
    var enough = checkout.dataset.enough === '1';
    var boxes = checkout.querySelectorAll('input[type="checkbox"][required]');
    var hint = document.querySelector('[data-hint]');
    function upd() {
      var all = Array.prototype.every.call(boxes, function (b) { return b.checked; });
      btn.disabled = !(enough && all);
      if (hint && enough) hint.textContent = all ? 'Онлайн-оплаты нет: после заявки менеджер согласует стоимость и доставку.' : 'Отметьте согласие на обработку данных и с офертой.';
    }
    boxes.forEach(function (b) { b.addEventListener('change', upd); });
    upd();
  }

  // Подтверждение удаления
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('submit', function (e) {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });
})();
