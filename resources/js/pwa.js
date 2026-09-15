import { registerOfflineWorker } from './offlinePackages.js';

function isStandalone() {
  return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

function isIos() {
  return /iphone|ipad|ipod/i.test(navigator.userAgent);
}

export async function setupProgressiveWebApp({ installButton, notify }) {
  if (!installButton) return null;

  let installPrompt = null;
  const setInstallButton = (visible, label = 'Install app') => {
    installButton.hidden = !visible || isStandalone();
    installButton.querySelector('span').textContent = label;
  };

  setInstallButton(isIos(), 'Add to Home Screen');
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    installPrompt = event;
    setInstallButton(true);
  });
  window.addEventListener('appinstalled', () => {
    installPrompt = null;
    setInstallButton(false);
    notify?.('AquaDissect was installed successfully.');
  });

  installButton.addEventListener('click', async () => {
    if (!installPrompt) {
      if (isIos()) notify?.('In Safari, choose Share, then Add to Home Screen.');
      else notify?.('Installation becomes available after the browser finishes preparing the app.');
      return;
    }
    await installPrompt.prompt();
    const choice = await installPrompt.userChoice;
    installPrompt = null;
    setInstallButton(false);
    if (choice.outcome !== 'accepted') notify?.('AquaDissect was not installed. You can try again from the browser menu.');
  });

  const registration = await registerOfflineWorker();
  if (!registration) return null;
  registration.update().catch(() => undefined);
  if (registration.waiting) notify?.('An AquaDissect update is ready and will be used when the app is reopened.');
  registration.addEventListener('updatefound', () => {
    const worker = registration.installing;
    worker?.addEventListener('statechange', () => {
      if (worker.state === 'installed' && navigator.serviceWorker.controller) {
        notify?.('A newer AquaDissect version is ready for your next visit.');
      }
    });
  });

  return registration;
}
