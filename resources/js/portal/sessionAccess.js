export const offlineContextKey = 'aquadissect.offline-context.v2';
export const pendingSignOutKey = 'aquadissect.sign-out-pending.v1';
export const signOutCookie = 'aquadissect_logout_pending';
let pendingInMemory = false;
function browserStorage() { try { return globalThis.localStorage; } catch { return null; } }

export function signOutPending(storage = browserStorage(), cookie = globalThis.document?.cookie || '') {
  try { return pendingInMemory || storage.getItem(pendingSignOutKey) === '1' || cookie.split(';').some(item => item.trim() === `${signOutCookie}=1`); }
  catch { return pendingInMemory || cookie.split(';').some(item => item.trim() === `${signOutCookie}=1`); }
}

export function clearLearnerContext(bootstrap) {
  Object.assign(bootstrap, { user: null, species: [], classes: [], learningModules: [], pendingUsers: [], lessonResult: null, tutorials: { guides: [], progress: [] } });
}

export function beginLocalSignOut(storage = browserStorage(), doc = globalThis.document, base = globalThis.window?.AQUADISSECT_BASE_URL || '') {
  pendingInMemory = true;
  // This cookie requests logout only. It never authenticates an account.
  try { if (doc) doc.cookie = `${signOutCookie}=1; Path=${base || ''}/; SameSite=Strict${globalThis.location?.protocol === 'https:' ? '; Secure' : ''}`; } catch { /* Local access still closes if cookies are disabled. */ }
  try { storage.setItem(pendingSignOutKey, '1'); } catch { /* The in-memory and cookie guards still block access. */ }
  try { storage.removeItem(offlineContextKey); } catch { /* The pending guard prevents restoring an old context. */ }
}

export function confirmServerSignOut(storage = browserStorage(), doc = globalThis.document, base = globalThis.window?.AQUADISSECT_BASE_URL || '') {
  pendingInMemory = false;
  try { storage.removeItem(pendingSignOutKey); storage.removeItem(offlineContextKey); } catch { /* No browser storage is required for online sign-in. */ }
  try { if (doc) doc.cookie = `${signOutCookie}=; Max-Age=0; Path=${base || ''}/; SameSite=Strict${globalThis.location?.protocol === 'https:' ? '; Secure' : ''}`; } catch { /* The server also expires its logout-intent cookie. */ }
}

export function prepareSessionContext(bootstrap, storage = browserStorage()) {
  if (!signOutPending(storage)) return;
  if (!bootstrap.offlineShell && !bootstrap.user) confirmServerSignOut(storage);
  else clearLearnerContext(bootstrap);
}
