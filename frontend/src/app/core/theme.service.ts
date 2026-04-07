import { DOCUMENT } from '@angular/common';
import { Injectable, inject, signal } from '@angular/core';

type Theme = 'dark' | 'light';

@Injectable({ providedIn: 'root' })
export class ThemeService {
  private static readonly storageKey = 'zetes-theme';

  private readonly document = inject(DOCUMENT);
  protected readonly currentTheme = signal<Theme>('dark');

  readonly theme = this.currentTheme.asReadonly();

  init(): void {
    const saved = this.readStoredTheme();
    const resolved = saved ?? this.resolveSystemTheme();
    this.currentTheme.set(resolved);
    this.applyTheme(resolved);
  }

  setTheme(theme: Theme): void {
    this.currentTheme.set(theme);
    this.applyTheme(theme);

    if (typeof window !== 'undefined') {
      window.localStorage.setItem(ThemeService.storageKey, theme);
    }
  }

  toggleTheme(): void {
    this.setTheme(this.currentTheme() === 'dark' ? 'light' : 'dark');
  }

  private applyTheme(theme: Theme): void {
    const root = this.document.documentElement;
    root.classList.toggle('light', theme === 'light');
  }

  private readStoredTheme(): Theme | null {
    if (typeof window === 'undefined') {
      return null;
    }

    const value = window.localStorage.getItem(ThemeService.storageKey);
    return value === 'light' || value === 'dark' ? value : null;
  }

  private resolveSystemTheme(): Theme {
    if (typeof window === 'undefined' || !window.matchMedia) {
      return 'dark';
    }

    return window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
  }
}

