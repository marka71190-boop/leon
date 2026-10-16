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

  // Карусель фото и видео товара: стрелки, свайп, миниатюры, клавиши ← →
  document.querySelectorAll('[data-carousel]').forEach(function (car) {
    var track = car.querySelector('[data-track]');
    var slides = Array.prototype.slice.call(car.querySelectorAll('[data-slide]'));
    var thumbs = Array.prototype.slice.call(document.querySelectorAll('[data-thumb]'));
    var prev = car.querySelector('[data-prev]');
    var next = car.querySelector('[data-next]');
    var count = car.querySelector('[data-count]');
    var cur = 0;
    if (slides.length < 2) return;

    function stopOthers(i) {
      slides.forEach(function (s, k) {
        if (k === i) return;
        var v = s.querySelector('video');
        if (v && !v.paused) v.pause();
        var f = s.querySelector('iframe');
        if (f && f.getAttribute('src')) f.setAttribute('src', f.getAttribute('src')); // перезагрузка останавливает плеер
      });
      var fr = slides[i].querySelector('iframe[data-src]');
      if (fr && !fr.getAttribute('src')) fr.setAttribute('src', fr.dataset.src);
    }
    function mark(i) {
      if (i === cur) return;
      cur = i;
      stopOthers(i);
      if (count) count.textContent = (i + 1) + ' / ' + slides.length;
      if (prev) prev.disabled = i === 0;
      if (next) next.disabled = i === slides.length - 1;
      thumbs.forEach(function (t, k) {
        t.classList.toggle('on', k === i);
        if (k === i) { t.setAttribute('aria-current', 'true'); t.scrollIntoView({ block: 'nearest', inline: 'nearest' }); }
        else t.removeAttribute('aria-current');
      });
    }
    function go(i) {
      i = Math.max(0, Math.min(slides.length - 1, i));
      track.scrollTo({ left: i * track.clientWidth });
      mark(i);
    }
    if (prev) { prev.disabled = true; prev.addEventListener('click', function () { go(cur - 1); }); }
    if (next) next.addEventListener('click', function () { go(cur + 1); });
    thumbs.forEach(function (t) { t.addEventListener('click', function () { go(parseInt(t.dataset.thumb, 10)); }); });
    var timer;
    track.addEventListener('scroll', function () {
      clearTimeout(timer);
      timer = setTimeout(function () { mark(Math.round(track.scrollLeft / track.clientWidth)); }, 80);
    }, { passive: true });
    car.addEventListener('keydown', function (e) {
      if (e.target.tagName === 'VIDEO') return;
      if (e.key === 'ArrowRight') { e.preventDefault(); go(cur + 1); }
      if (e.key === 'ArrowLeft') { e.preventDefault(); go(cur - 1); }
    });
    window.addEventListener('resize', function () { track.scrollTo({ left: cur * track.clientWidth, behavior: 'auto' }); });
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
