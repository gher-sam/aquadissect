const databaseName = 'aquadissect-notebook-drafts';
const conflict = () => new Error('This draft changed in another tab. Download your entries, then reload to use the saved copy.');

function openDatabase() {
  return new Promise((resolve, reject) => {
    if (!globalThis.indexedDB) { reject(new Error('Device storage is unavailable.')); return; }
    const request = indexedDB.open(databaseName, 1);
    request.onupgradeneeded = () => request.result.createObjectStore('drafts', { keyPath: 'scope' });
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
    request.onblocked = () => reject(new Error('Close other AquaDissect tabs and try again.'));
  });
}

export async function readNotebookDraft(scope) {
  const db = await openDatabase();
  try {
    return await new Promise((resolve, reject) => {
      const request = db.transaction('drafts').objectStore('drafts').get(scope);
      request.onsuccess = () => resolve(request.result || null);
      request.onerror = () => reject(request.error);
    });
  } finally { db.close(); }
}

export async function writeNotebookDraft(scope, fields, expectedRevision, remove = false) {
  const db = await openDatabase();
  try {
    return await new Promise((resolve, reject) => {
      const tx = db.transaction('drafts', 'readwrite');
      const store = tx.objectStore('drafts');
      let result = null;
      let failure;
      const request = store.get(scope);
      request.onsuccess = () => {
        if ((request.result?.revision || null) !== expectedRevision) { failure = conflict(); tx.abort(); return; }
        if (remove) store.delete(scope);
        else {
          result = { scope, fields, revision: crypto.randomUUID(), savedAt: new Date().toISOString(), schemaVersion: 1 };
          store.put(result);
        }
      };
      tx.oncomplete = () => resolve(result);
      tx.onerror = tx.onabort = () => reject(failure || tx.error || new Error('The draft could not be saved.'));
    });
  } finally { db.close(); }
}
