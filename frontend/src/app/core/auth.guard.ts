import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from './auth.service';

export const authGuard: CanActivateFn = async () => {
  const auth = inject(AuthService);
  const router = inject(Router);

  // Wait for bootstrap to complete
  await new Promise<void>((resolve) => {
    const checkAuth = () => {
      if (!auth.isBootstrapping()) {
        resolve();
      } else {
        // Check again in 10ms
        setTimeout(checkAuth, 10);
      }
    };
    checkAuth();
  });

  return auth.isAuthenticated() ? true : router.createUrlTree(['/login']);
};

export const guestGuard: CanActivateFn = async () => {
  const auth = inject(AuthService);
  const router = inject(Router);

  // Wait for bootstrap to complete
  await new Promise<void>((resolve) => {
    const checkAuth = () => {
      if (!auth.isBootstrapping()) {
        resolve();
      } else {
        // Check again in 10ms
        setTimeout(checkAuth, 10);
      }
    };
    checkAuth();
  });

  return auth.isAuthenticated() ? router.createUrlTree(['/dashboard']) : true;
};

