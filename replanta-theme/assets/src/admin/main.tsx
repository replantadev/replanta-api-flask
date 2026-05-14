/**
 * Replanta Admin entry — mounts the React app inside #replanta-ai-root.
 */
import { createRoot } from '@wordpress/element';
import './admin.css';
import { App } from './App';

const mount = (): void => {
  const el = document.getElementById('replanta-ai-root');
  if (!el) return;
  const root = createRoot(el);
  root.render(<App />);
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mount);
} else {
  mount();
}
