const STORAGE_KEY = 'piano-cookie-consent';

function hasCookieConsent() {
  try {
    return localStorage.getItem(STORAGE_KEY) === 'accepted';
  } catch {
    return false;
  }
}

function saveCookieConsent() {
  try {
    localStorage.setItem(STORAGE_KEY, 'accepted');
  } catch {
    // ignore quota / private mode
  }
}

function hideBanner(banner) {
  banner.hidden = true;
  banner.classList.remove('cookie-banner--visible');
}

function showBanner(banner) {
  banner.hidden = false;
  requestAnimationFrame(() => {
    banner.classList.add('cookie-banner--visible');
  });
}

function initCookieConsent() {
  const banner = document.getElementById('cookie-banner');
  const acceptBtn = document.getElementById('cookie-banner-accept');

  if (hasCookieConsent()) {
    if (banner) hideBanner(banner);
    return;
  }

  if (!banner || !acceptBtn) return;

  showBanner(banner);

  acceptBtn.addEventListener('click', () => {
    saveCookieConsent();
    hideBanner(banner);
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initCookieConsent);
} else {
  initCookieConsent();
}
