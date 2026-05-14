import clsx from 'clsx';
import type { OnboardingState } from './Onboarding';

type Props = {
  state: OnboardingState;
  update: (patch: Partial<OnboardingState>) => void;
};

const palettes = [
  { id: 'forest', name: 'Forest', colors: ['#FBFAF6', '#1F6F45', '#E8B84C', '#0E1A14'] },
  { id: 'solar', name: 'Solar', colors: ['#FFF8EC', '#D97706', '#0E1A14', '#7C2D12'] },
  { id: 'ocean', name: 'Ocean', colors: ['#F0F7FB', '#0369A1', '#0EA5E9', '#082F49'] },
  { id: 'mono', name: 'Mono', colors: ['#FFFFFF', '#000000', '#737373', '#E5E5E5'] },
  { id: 'berry', name: 'Berry', colors: ['#FDF4FF', '#A21CAF', '#F472B6', '#1F0A1A'] },
  { id: 'custom', name: 'Custom', colors: ['#FBFAF6', '#1F6F45', '#E8B84C', '#0E1A14'] },
];

const fontPairs = [
  { id: 'editorial', name: 'Editorial', sample: 'Fraunces + Inter' },
  { id: 'tech', name: 'Tech', sample: 'Geist + Geist Mono' },
  { id: 'eco', name: 'Eco', sample: 'DM Serif + DM Sans' },
  { id: 'brutalist', name: 'Brutalist', sample: 'Space Grotesk + JetBrains' },
  { id: 'classic', name: 'Classic', sample: 'Playfair + Source Sans' },
];

export const StepIdentity = ({ state, update }: Props): JSX.Element => {
  return (
    <div>
      <h2 className="font-rt-serif text-2xl mb-2">Identidad visual</h2>
      <p className="text-rt-muted mb-8">Empieza con un Style Pack y afínalo después.</p>

      <section className="mb-8">
        <h3 className="text-sm font-medium uppercase tracking-wider text-rt-muted mb-3">Paleta</h3>
        <div className="grid gap-3 grid-cols-3 md:grid-cols-6">
          {palettes.map((p) => (
            <button
              type="button"
              key={p.id}
              onClick={() => update({ palette: p.id })}
              className={clsx(
                'p-3 rounded-rt-md border-2 transition',
                state.palette === p.id ? 'border-rt-primary' : 'border-rt-border',
              )}
            >
              <div className="flex gap-1 mb-2">
                {p.colors.map((c) => (
                  <div key={c} className="w-4 h-4 rounded-full border border-rt-border" style={{ backgroundColor: c }} />
                ))}
              </div>
              <div className="text-xs font-medium">{p.name}</div>
            </button>
          ))}
        </div>
      </section>

      <section className="mb-8">
        <h3 className="text-sm font-medium uppercase tracking-wider text-rt-muted mb-3">Tipografía</h3>
        <div className="grid gap-3 md:grid-cols-5">
          {fontPairs.map((f) => (
            <button
              type="button"
              key={f.id}
              onClick={() => update({ fontPair: f.id })}
              className={clsx(
                'p-3 rounded-rt-md border-2 text-left',
                state.fontPair === f.id ? 'border-rt-primary' : 'border-rt-border',
              )}
            >
              <div className="text-sm font-medium">{f.name}</div>
              <div className="text-xs text-rt-muted">{f.sample}</div>
            </button>
          ))}
        </div>
      </section>

      <section className="grid gap-6 md:grid-cols-2">
        <div>
          <h3 className="text-sm font-medium uppercase tracking-wider text-rt-muted mb-3">Densidad</h3>
          <div className="flex gap-2">
            {(['compact', 'normal', 'airy'] as const).map((d) => (
              <button
                type="button"
                key={d}
                onClick={() => update({ density: d })}
                className={clsx(
                  'flex-1 py-2 rounded-rt-md border text-sm capitalize',
                  state.density === d ? 'border-rt-primary bg-rt-surface' : 'border-rt-border',
                )}
              >
                {d}
              </button>
            ))}
          </div>
        </div>
        <div>
          <h3 className="text-sm font-medium uppercase tracking-wider text-rt-muted mb-3">Radios</h3>
          <div className="flex gap-2">
            {(['sharp', 'soft'] as const).map((r) => (
              <button
                type="button"
                key={r}
                onClick={() => update({ radius: r })}
                className={clsx(
                  'flex-1 py-2 rounded-rt-md border text-sm capitalize',
                  state.radius === r ? 'border-rt-primary bg-rt-surface' : 'border-rt-border',
                )}
              >
                {r}
              </button>
            ))}
          </div>
        </div>
      </section>
    </div>
  );
};
