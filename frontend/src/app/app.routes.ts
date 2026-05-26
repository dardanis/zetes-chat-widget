import { Routes } from '@angular/router';
import { authGuard, guestGuard } from './core/auth.guard';
import { AppShellComponent } from './layouts/app-shell.component';
import { ProjectLayoutComponent } from './layouts/project-layout.component';
import { DashboardPageComponent } from './pages/dashboard-page.component';
import { LoginPageComponent } from './pages/login-page.component';
import { ProjectChatPageComponent } from './pages/project-chat-page.component';
import { ProjectDocumentsPageComponent } from './pages/project-documents-page.component';
import { ProjectOverviewPageComponent } from './pages/project-overview-page.component';
import { ProjectsPageComponent } from './pages/projects-page.component';
// COMMENTED: registration disabled
// import { RegisterPageComponent } from './pages/register-page.component';
import { SettingsPageComponent } from './pages/settings-page.component';
import { TenantsPageComponent } from './pages/tenants-page.component';

export const routes: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'app/dashboard' },
  { path: 'login', component: LoginPageComponent, canActivate: [guestGuard] },
  // COMMENTED: registration disabled
  // { path: 'register', component: RegisterPageComponent, canActivate: [guestGuard] },

  {
    path: 'app',
    component: AppShellComponent,
    canActivate: [authGuard],
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'dashboard' },
      { path: 'dashboard', component: DashboardPageComponent },
      { path: 'tenants', component: TenantsPageComponent },
      { path: 'projects', component: ProjectsPageComponent },
      {
        path: 'projects/:projectId',
        component: ProjectLayoutComponent,
        children: [
          { path: '', pathMatch: 'full', redirectTo: 'overview' },
          { path: 'overview', component: ProjectOverviewPageComponent },
          { path: 'documents', component: ProjectDocumentsPageComponent },
          { path: 'chat', component: ProjectChatPageComponent },
        ],
      },
      { path: 'settings', component: SettingsPageComponent },
    ],
  },

  { path: 'dashboard', pathMatch: 'full', redirectTo: 'app/dashboard' },
  { path: 'projects/:projectId', pathMatch: 'full', redirectTo: 'app/projects/:projectId/overview' },
  { path: '**', redirectTo: 'app/dashboard' },
];
