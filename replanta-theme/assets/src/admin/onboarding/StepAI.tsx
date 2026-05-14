import clsx from 'clsx';
import type { OnboardingState } from './Onboarding';

type Props = {
  state: OnboardingState;
  update: (patch: Partial<OnboardingState>) => void;
};

const providers = [
  { id: 'anthropic' as const, name: 'Anthropic Claude', desc: 'Recomendado. Mejor para HTML/MDX.', model: 'claude-sonnet-4' },
  { id: 'openai' as const, name: 'OpenAI', desc: 'GPT-4 / GPT-5 como alternativa.', model: 'gpt-5' },
];

export const StepAI = ({ state, update }: Props): JSX.Element => {
  return (
    <div>
      <h2 className="font-rt-serif text-2xl mb-2">Conecta tu IA</h2>
      <p className="text-rt-muted mb-8">La clave se guarda cifrada en tu WP. Nunca se expone al frontend.</p>

      <section className="mb-6">
        <div className="grid gap-3 md:grid-cols-2">
          {providers.map((p) => (
            <button
              type="button"
              key={p.id}
              onClick={() => update({ aiProvider: p.id })}
              className={clsx(
                'p-4 rounded-rt-md border-2 text-left',
                state.aiProvider === p.id ? 'border-rt-primary bg-rt-surface' : 'border-rt-border',
              )}
            >
              <div className="font-medium">{p.name}</div>
              <div className="text-sm text-rt-muted">{p.desc}</div>
              <div className="mt-2 text-xs font-mono text-rt-muted">{p.model}</div>
            </button>
          ))}
        </div>
      </section>

      <section>
        <label className="block text-sm font-medium mb-2">
          API Key{' '}
          {state.aiProvider === 'anthropic' && (
            <a
              href="https://console.anthropic.com/settings/keys"
              target="_blank"
              rel="noopener noreferrer"
              className="text-rt-primary text-xs ml-2"
            >
              obtener →
            </a>
          )}
        </label>
        <input
          type="password"
          placeholder={state.aiProvider === 'anthropic' ? 'sk-ant-...' : 'sk-...'}
          value={state.aiKey}
          onChange={(e) => update({ aiKey: e.target.value })}
          className="w-full px-4 py-2.5 rounded-rt-md border border-rt-border focus:border-rt-primary outline-none font-mono text-sm"
        />
        <p className="text-xs text-rt-muted mt-2">
          La conexión se valida al guardar. Si falla, podrás volver a este paso.
        </p>
      </section>
    </div>
  );
};
