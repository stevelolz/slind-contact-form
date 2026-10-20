(function () {
  'use strict';
  if (!window.grecaptcha || !window.slindRecaptcha) return;
  document.querySelectorAll('.slind-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (form.dataset.recaptchaReady === 'true') return;
      event.preventDefault();
      window.grecaptcha.ready(function () {
        window.grecaptcha.execute(window.slindRecaptcha.siteKey, {action: window.slindRecaptcha.action}).then(function (token) {
          form.querySelector('[name="slind_recaptcha_token"]').value = token;
          form.dataset.recaptchaReady = 'true';
          form.submit();
        });
      });
    });
  });
})();
