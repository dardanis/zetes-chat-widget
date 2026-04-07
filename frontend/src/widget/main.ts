import { createCustomElement } from '@angular/elements';
import { createApplication } from '@angular/platform-browser';
import { provideHttpClient } from '@angular/common/http';
import { ZetesChatComponent } from './components/zetes-chat.component';

(async () => {
  const app = await createApplication({
    providers: [provideHttpClient()],
  });

  const chatElement = createCustomElement(ZetesChatComponent, {
    injector: app.injector,
  });

  if (!customElements.get('zetes-chat')) {
    customElements.define('zetes-chat', chatElement);
  }
})();

