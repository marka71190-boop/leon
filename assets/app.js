// Leon_pro — небольшие интерактивные элементы
(function () {
  // Степпер количества
  document.querySelectorAll('[data-stepper]').forEach(function (box) {
    var step = parseInt(box.dataset.step, 10) || 1;
    var input = box.querySelector('input');
    function set(v) {
      v = Math.max(step, Math.round(v / step) * step);
      input.value = v;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }
    box.querySelector('[data-dec]').addEventListener('click', function () { set((parseInt(input.value, 10) || 0) - step); });
    box.querySelector('[data-inc]').addEventListener('click', function () { set((parseInt(input.value, 10) || 0) + step); });
    input.addEventListener('blur', function () { set(parseInt(input.value, 10) || step); });
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
