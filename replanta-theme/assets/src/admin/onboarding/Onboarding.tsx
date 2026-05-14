import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { StepContent } from './StepContent';
import { StepIdentity } from './StepIdentity';
import { StepAI } from './StepAI';
import clsx from 'clsx';

export type OnboardingState = {
  contentMode: 'local' | 'git' | 'wp-only' | null;
  gitUrl?: string;
  stylePack: 'replanta-eco' | 'tech-studio' | 'editorial-mag' | 'custom';
  palette: string;
  fontPair: string;
  density: 'compact' | 'normal' | 'airy';
  radius: 'sharp' | 'soft';
  aiProvider: 'anthropic' | 'openai';
  aiKey: string;
};

const initial: OnboardingState = {
  contentMode: null,
  stylePack: 'replanta-eco',
  palette: 'forest',
  fontPair: 'eco',
  density: 'normal',
  radius: 'soft',
  aiProvider: 'anthropic',
  aiKey: '',
};

const steps = ['Contenido', 'Identidad', 'IA'] as const;

export const Onboarding = ({ onComplete }: { onComplete: () => void }): JSX.Element => {
  const [state, setState] = useState<OnboardingState>(initial);
  const [step, setStep] = useState(0);
  const [saving, setSaving] = useState(false);

  const update = (patch: Partial<OnboardingState>): void => setState((s) => ({ ...s, ...patch }));

  const next = async (): Promise<void> => {
    if (step < steps.length - 1) {
      setStep(step + 1);
      return;
    }
    setSaving(true);
    try {
      await apiFetch({
        path: 'onboarding',
        method: 'POST',
        data: state,
      });
      onComplete();
    } finally {
      setSaving(false);
    }
  };

  const canAdvance = (): boolean => {
    if (step === 0) return state.contentMode !== null;
    if (step === 2) return state.aiKey.length > 10;
    return true;
  };

  return (
    <div className="rt-shell min-h-screen bg-rt-bg">
      <div className="max-w-4xl mx-auto px-6 py-12">
        <header className="flex items-center justify-between mb-10">
          <div>
            <div className="text-xs uppercase tracking-widest text-rt-muted">Replanta AI · Setup</div>
            <h1 className="font-rt-serif text-4xl mt-1">Bienvenido</h1>
          </div>
          <ol className="flex gap-2">
            {steps.map((label, i) => (
              <li
                key={label}
                className={clsx(
                  'px-3 py-1 rounded-rt-pill text-xs',
                  i === step
                    ? 'bg-rt-primary text-rt-primary-fg'
                    : i < step
                      ? 'bg-rt-surface text-rt-fg'
                      : 'bg-transparent text-rt-muted border border-rt-border',
                )}
              >
                {i + 1}. {label}
              </li>
            ))}
          </ol>
        </header>

        <main className="bg-white border border-rt-border rounded-rt-lg p-8 shadow-sm">
          {step === 0 && <StepContent state={state} update={update} />}
          {step === 1 && <StepIdentity state={state} update={update} />}
          {step === 2 && <StepAI state={state} update={update} />}
        </main>

        <footer className="flex justify-between items-center mt-6">
          <button
            type="button"
            className="text-rt-muted hover:text-rt-fg disabled:opacity-30"
            onClick={() => setStep(Math.max(0, step - 1))}
            disabled={step === 0 || saving}
          >
            ← Atrás
          </button>
          <button
            type="button"
            className="px-5 py-2.5 rounded-rt-md bg-rt-primary text-rt-primary-fg font-medium disabled:opacity-50"
            onClick={() => void next()}
            disabled={!canAdvance() || saving}
          >
            {saving ? 'Guardando…' : step === steps.length - 1 ? 'Empezar a crear' : 'Continuar →'}
          </button>
        </footer>
      </div>
    </div>
  );
};
