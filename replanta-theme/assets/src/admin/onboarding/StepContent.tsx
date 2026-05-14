import clsx from 'clsx';
import type { OnboardingState } from './Onboarding';

type Props = {
  state: OnboardingState;
  update: (patch: Partial<OnboardingState>) => void;
};

const options = [
  {
    id: 'local' as const,
    title: 'Crear proyecto local',
    desc: 'Carpeta /content en el tema, con git init opcional. Ideal para empezar.',
  },
  {
    id: 'git' as const,
    title: 'Conectar repo Git',
    desc: 'Clona un repositorio existente con tu contenido. GitHub o cualquier URL.',
  },
  {
    id: 'wp-only' as const,
    title: 'Solo WordPress',
    desc: 'Sin archivos: el contenido vive en la base de datos. Más simple, menos poder.',
  },
];

export const StepContent = ({ state, update }: Props): JSX.Element => {
  return (
    <div>
      <h2 className="font-rt-serif text-2xl mb-2">¿Dónde vive el contenido?</h2>
      <p className="text-rt-muted mb-8">
        Replanta puede sincronizar páginas entre archivos versionables y WordPress.
      </p>

      <div className="grid gap-4 md:grid-cols-3">
        {options.map((opt) => (
          <button
            type="button"
            key={opt.id}
            onClick={() => update({ contentMode: opt.id })}
            className={clsx(
              'text-left p-5 rounded-rt-lg border-2 transition-all',
              state.contentMode === opt.id
                ? 'border-rt-primary bg-rt-surface'
                : 'border-rt-border hover:border-rt-fg',
            )}
          >
            <div className="font-medium mb-1">{opt.title}</div>
            <div className="text-sm text-rt-muted">{opt.desc}</div>
          </button>
        ))}
      </div>

      {state.contentMode === 'git' && (
        <div className="mt-6">
          <label className="block text-sm font-medium mb-2">URL del repositorio</label>
          <input
            type="url"
            placeholder="https://github.com/tu-org/tu-content.git"
            value={state.gitUrl ?? ''}
            onChange={(e) => update({ gitUrl: e.target.value })}
            className="w-full px-4 py-2.5 rounded-rt-md border border-rt-border focus:border-rt-primary outline-none"
          />
        </div>
      )}
    </div>
  );
};
