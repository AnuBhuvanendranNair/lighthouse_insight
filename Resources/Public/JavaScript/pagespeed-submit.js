document.querySelectorAll('[data-formengine-input-name]').forEach((visibleInput) => {
  const hiddenInput = document.querySelector('[name="' + visibleInput.dataset.formengineInputName + '"]');
  if (!(hiddenInput instanceof HTMLInputElement)) {
    return;
  }

  ['input', 'change'].forEach((eventName) => {
    visibleInput.addEventListener(eventName, () => {
      hiddenInput.value = visibleInput.value;
    });
  });
});

document.querySelectorAll('[data-pagespeed-form]').forEach((form) => {
  form.addEventListener('submit', () => {
    const button = form.querySelector('[data-pagespeed-submit]');
    const overlay = document.querySelector('[data-pagespeed-overlay]');

    if (button instanceof HTMLButtonElement) {
      button.disabled = true;
    }

    if (overlay instanceof HTMLElement) {
      overlay.hidden = false;
    }
  });
});
