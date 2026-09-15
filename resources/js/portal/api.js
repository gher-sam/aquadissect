const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
const baseUrl = String(window.AQUADISSECT_BASE_URL || '').replace(/\/$/, '');

export function appUrl(path = '') {
  const normalizedPath = path === '' || path.startsWith('/') ? path : `/${path}`;

  return `${baseUrl}${normalizedPath}` || '/';
}

export async function request(path, { method = 'GET', body } = {}) {
  const isFormData = body instanceof FormData;
  const response = await fetch(appUrl(path), {
    method,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
      'X-CSRF-TOKEN': csrfToken,
    },
    body: body === undefined ? undefined : (isFormData ? body : JSON.stringify(body)),
  });
  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    const validationMessage = Object.values(data.errors || {}).flat()[0];
    throw new Error(validationMessage || data.message || 'The request could not be completed.');
  }

  return data;
}
