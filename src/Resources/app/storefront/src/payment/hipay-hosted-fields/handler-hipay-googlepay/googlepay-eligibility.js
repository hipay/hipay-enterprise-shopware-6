/**
 * Helpers shared by the Google Pay plugins
 */
export function hideMethod(el) {
  const method = el.closest('.payment-method') || el.closest('.hipay-hosted-fields-wrapper');
  if (method) {
    method.style.display = 'none';
  }
}

export function markChecked(el) {
  el.setAttribute('data-hipay-googlepay-checked', '');
}
