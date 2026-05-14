import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Onboarding } from './onboarding/Onboarding';
import { Composer } from './composer/Composer';
import './globals';

type Settings = {
  onboarding_done?: boolean;
  [key: string]: unknown;
};

export const App = (): JSX.Element => {
  const [loading, setLoading] = useState(true);
  const [done, setDone] = useState(false);

  useEffect(() => {
    apiFetch.use(apiFetch.createNonceMiddleware(window.RT_ADMIN.nonce));
    apiFetch.use(apiFetch.createRootURLMiddleware(window.RT_ADMIN.restUrl));

    (async () => {
      try {
        const settings = await apiFetch<Settings>({ path: 'settings' });
        setDone(Boolean(settings.onboarding_done));
      } catch {
        setDone(false);
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  if (loading) {
    return (
      <div className="rt-shell flex items-center justify-center min-h-[60vh]">
        <div className="text-rt-muted">Cargando Replanta AI…</div>
      </div>
    );
  }

  return done ? <Composer /> : <Onboarding onComplete={() => setDone(true)} />;
};
