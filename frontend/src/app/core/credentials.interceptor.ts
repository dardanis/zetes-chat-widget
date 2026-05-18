import { HttpInterceptorFn } from '@angular/common/http';

export const credentialsInterceptor: HttpInterceptorFn = (req, next) => {
  const shouldAttachCredentials = req.url.startsWith('/api') || req.url.startsWith('/sanctum');
  const isMutatingRequest = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(req.method.toUpperCase());

  let nextRequest = shouldAttachCredentials ? req.clone({ withCredentials: true }) : req;

  // Add CSRF header explicitly for mutating calls to avoid browser/XSRF interceptor edge cases.
  // Prefer the project cookie, but fall back to Laravel's default cookie name.
  if (isMutatingRequest && shouldAttachCredentials) {
    const xsrfToken = getCookieValue('ZETES-XSRF-TOKEN') ?? getCookieValue('XSRF-TOKEN');

    if (xsrfToken) {
      nextRequest = nextRequest.clone({
        setHeaders: {
          'X-XSRF-TOKEN': decodeURIComponent(xsrfToken),
        },
      });
    }
  }

  return next(nextRequest);
};

function getCookieValue(name: string): string | null {
  if (typeof document === 'undefined' || !document.cookie) {
    return null;
  }

  const prefix = `${name}=`;
  const cookies = document.cookie
    .split(';')
    .map((part) => part.trim())
    .filter((part) => part.startsWith(prefix));

  if (cookies.length === 0) {
    return null;
  }

  // When duplicate cookie names exist, the right-most value is typically the newest one.
  return cookies[cookies.length - 1].slice(prefix.length);
}

