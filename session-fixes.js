(function () {
  'use strict';

  // Keep the session headers present even when callers pass a Headers object.
  // This prevents dependent school/class/student requests from being treated as
  // anonymous by Apache/PHP deployments.
  const nativeFetch = window.fetch.bind(window);
  window.fetch = function (input, init) {
    init = init || {};
    const token = localStorage.getItem('wlc_token');
    if (token && !(input instanceof Request && input.url.includes('/api/login'))) {
      const headers = new Headers(init.headers || (input instanceof Request ? input.headers : undefined));
      headers.set('Authorization', 'Bearer ' + token);
      headers.set('x-wlc-token', token);
      headers.set('x-user-role', localStorage.getItem('wlc_role') || '');
      init.headers = headers;
    }
    return nativeFetch(input, init);
  };

  // A certificate must remain printable as one complete A4 landscape sheet.
  // Remove any accidental horizontal overflow before the print dialog opens.
  window.addEventListener('beforeprint', function () {
    document.documentElement.classList.add('wlc-printing');
    document.body.classList.add('wlc-printing');
  });
  window.addEventListener('afterprint', function () {
    document.documentElement.classList.remove('wlc-printing');
    document.body.classList.remove('wlc-printing');
  });
})();
