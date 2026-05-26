import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Observable, catchError, map, of, switchMap, tap } from 'rxjs';

export interface User {
  id: number;
  name: string;
  email: string;
}

interface AuthResponse {
  user: User;
}

interface LoginPayload {
  email: string;
  password: string;
  remember: boolean;
}

interface RegisterPayload {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly storageKey = 'zetes.auth.user';
  private readonly http = inject(HttpClient);
  private readonly userSignal = signal<User | null>(this.readStoredUser());
  private readonly bootstrappingSignal = signal(true);

  readonly user = this.userSignal.asReadonly();
  readonly isAuthenticated = computed(() => this.userSignal() !== null);
  readonly isBootstrapping = this.bootstrappingSignal.asReadonly();

  bootstrap(): Observable<void> {
    this.bootstrappingSignal.set(true);

    return this.refreshCsrf().pipe(
      switchMap(() => this.http.get<User>('/api/user')),
      tap((user) => {
        this.userSignal.set(user);
        this.writeStoredUser(user);
      }),
      map(() => undefined),
      catchError(() => {
        this.userSignal.set(null);
        this.clearStoredUser();
        return of(undefined);
      }),
      tap(() => this.bootstrappingSignal.set(false))
    );
  }

  refreshCsrf(): Observable<void> {
    return this.ensureCsrfCookie().pipe(map(() => undefined));
  }

  login(payload: LoginPayload): Observable<User> {
    return this.ensureCsrfCookie().pipe(
      switchMap(() => this.http.post<AuthResponse>('/api/login', payload)),
      map((response) => response.user),
      tap((user) => {
        this.userSignal.set(user);
        this.writeStoredUser(user);
      })
    );
  }

  // COMMENTED: registration disabled
  /*
  register(payload: RegisterPayload): Observable<User> {
    return this.ensureCsrfCookie().pipe(
      switchMap(() => this.http.post<AuthResponse>('/api/register', payload)),
      map((response) => response.user),
      tap((user) => {
        this.userSignal.set(user);
        this.writeStoredUser(user);
      })
    );
  }
  */

  logout(): Observable<void> {
    return this.ensureCsrfCookie().pipe(
      switchMap(() => this.http.post('/api/logout', {})),
      map(() => undefined),
      tap(() => {
        this.userSignal.set(null);
        this.clearStoredUser();
      })
    );
  }

  private ensureCsrfCookie(): Observable<unknown> {
    return this.http.get('/sanctum/csrf-cookie', { responseType: 'text' });
  }

  private readStoredUser(): User | null {
    if (typeof localStorage === 'undefined') {
      return null;
    }

    try {
      const raw = localStorage.getItem(this.storageKey);

      return raw ? JSON.parse(raw) as User : null;
    } catch {
      return null;
    }
  }

  private writeStoredUser(user: User): void {
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem(this.storageKey, JSON.stringify(user));
    }
  }

  private clearStoredUser(): void {
    if (typeof localStorage !== 'undefined') {
      localStorage.removeItem(this.storageKey);
    }
  }
}

