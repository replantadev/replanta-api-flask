import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState, useCallback } from '@wordpress/element';

interface PageItem {
  path: string;
  lang: string;
  title: string;
  slug: string;
  modified: number;
  imported?: boolean;
  review_needed?: boolean;
}

interface StylePack {
  id: string;
  name: string;
  palette: Record<string, string>;
  fonts: { serif: string; sans: string };
  density: string;
  radius: string;
}

interface ElementorPost {
  id: number;
  title: string;
  type: string;
  status: string;
  slug: string;
}

type Tab = 'composer' | 'style' | 'import';

export const Composer = (): JSX.Element => {
  const [tab, setTab] = useState<Tab>('composer');
  const [pages, setPages] = useState<PageItem[]>([]);
  const [selected, setSelected] = useState<PageItem | null>(null);
  const [pageContent, setPageContent] = useState<{ frontmatter: Record<string, unknown>; body: string; raw: string } | null>(null);
  const [prompt, setPrompt] = useState('');
  const [lang, setLang] = useState<string>(window.RT_ADMIN?.languages?.[0]?.slug ?? 'es');
  const [busy, setBusy] = useState(false);
  const [status, setStatus] = useState<string>('');
  const [packs, setPacks] = useState<StylePack[]>([]);
  const [activePack, setActivePack] = useState<string>('');
  const [elementorPosts, setElementorPosts] = useState<ElementorPost[]>([]);
  const [installed, setInstalled] = useState<boolean | null>(null);
  const [installing, setInstalling] = useState(false);
  const [seedDemo, setSeedDemo] = useState(true);

  const loadInstall = useCallback(async () => {
    const data = await apiFetch<{ installed: boolean; version: string }>({ path: 'replanta/v1/install' });
    setInstalled(data.installed);
  }, []);

  const runInstall = async (): Promise<void> => {
    setInstalling(true);
    setStatus('Instalando…');
    try {
      const res = await apiFetch<{ ok: boolean; seeded: string[]; sync: { created: number } }>({
        path: 'replanta/v1/install',
        method: 'POST',
        data: { lang, seed_demo: seedDemo },
      });
      if (res.ok) {
        setStatus(`Instalado (${res.seeded.length} archivos demo, ${res.sync.created} páginas)`);
        setInstalled(true);
        await loadPages();
      }
    } catch (e) {
      setStatus(`Error: ${(e as Error).message}`);
    } finally {
      setInstalling(false);
    }
  };

  const loadPages = useCallback(async () => {
    const data = await apiFetch<PageItem[]>({ path: 'replanta/v1/pages' });
    setPages(data);
  }, []);

  const loadPacks = useCallback(async () => {
    const data = await apiFetch<{ packs: StylePack[]; active: string }>({ path: 'replanta/v1/style-packs' });
    setPacks(data.packs);
    setActivePack(data.active);
  }, []);

  const loadElementor = useCallback(async () => {
    const data = await apiFetch<ElementorPost[]>({ path: 'replanta/v1/import/elementor/list' });
    setElementorPosts(data);
  }, []);

  useEffect(() => { void loadPages(); void loadPacks(); void loadInstall(); }, [loadPages, loadPacks, loadInstall]);
  useEffect(() => { if (tab === 'import') void loadElementor(); }, [tab, loadElementor]);

  const openPage = async (p: PageItem): Promise<void> => {
    setSelected(p);
    setStatus('Cargando…');
    const data = await apiFetch<{ frontmatter: Record<string, unknown>; body: string; raw: string }>({
      path: `replanta/v1/pages/${encodeURIComponent(p.path)}`,
    });
    setPageContent(data);
    setStatus('');
  };

  const generatePage = async (): Promise<void> => {
    if (!prompt.trim()) return;
    setBusy(true);
    setStatus('Generando…');
    try {
      const res = await apiFetch<{ ok: boolean; path?: string; error?: string }>({
        path: 'replanta/v1/generate/page',
        method: 'POST',
        data: { prompt, lang },
      });
      if (res.ok) {
        setStatus(`Creada: ${res.path}`);
        setPrompt('');
        await loadPages();
      } else {
        setStatus(`Error: ${res.error ?? 'desconocido'}`);
      }
    } catch (e) {
      setStatus(`Error: ${(e as Error).message}`);
    } finally {
      setBusy(false);
    }
  };

  const translatePage = async (target: string): Promise<void> => {
    if (!selected) return;
    setBusy(true);
    setStatus(`Traduciendo a ${target}…`);
    try {
      const res = await apiFetch<{ ok: boolean; path?: string }>({
        path: 'replanta/v1/translate',
        method: 'POST',
        data: { path: selected.path, target },
      });
      setStatus(res.ok ? `Traducción creada: ${res.path}` : 'Error');
      await loadPages();
    } finally {
      setBusy(false);
    }
  };

  const savePage = async (): Promise<void> => {
    if (!selected || !pageContent) return;
    setBusy(true);
    setStatus('Guardando…');
    try {
      await apiFetch({
        path: `replanta/v1/pages/${encodeURIComponent(selected.path)}`,
        method: 'PUT',
        data: pageContent,
      });
      setStatus('Guardado');
      await loadPages();
    } finally { setBusy(false); }
  };

  const deletePage = async (): Promise<void> => {
    if (!selected) return;
    if (!window.confirm(`¿Eliminar ${selected.path}?`)) return;
    await apiFetch({
      path: `replanta/v1/pages/${encodeURIComponent(selected.path)}`,
      method: 'DELETE',
    });
    setSelected(null);
    setPageContent(null);
    await loadPages();
  };

  const applyPack = async (id: string): Promise<void> => {
    setBusy(true);
    await apiFetch({ path: 'replanta/v1/style-packs', method: 'POST', data: { id } });
    setActivePack(id);
    setBusy(false);
  };

  const importElementor = async (postId: number): Promise<void> => {
    setBusy(true);
    setStatus('Importando…');
    try {
      const res = await apiFetch<{ ok: boolean; path?: string; review_needed?: boolean }>({
        path: 'replanta/v1/import/elementor',
        method: 'POST',
        data: { post_id: postId, lang },
      });
      setStatus(res.ok ? `Importado: ${res.path}${res.review_needed ? ' (revisar)' : ''}` : 'Error');
      await loadPages();
    } finally { setBusy(false); }
  };

  const langs = window.RT_ADMIN?.languages ?? [{ slug: 'es', name: 'Español' }];
  const grouped = pages.reduce<Record<string, PageItem[]>>((acc, p) => {
    (acc[p.lang] ||= []).push(p);
    return acc;
  }, {});

  return (
    <div className="rt-shell min-h-screen bg-rt-bg flex flex-col">
      {installed === false && (
        <div className="bg-gradient-to-br from-rt-primary/5 to-rt-accent/5 border-b border-rt-border p-8">
          <div className="max-w-2xl mx-auto text-center">
            <h2 className="font-rt-serif text-3xl mb-2">Bienvenido a Replanta AI</h2>
            <p className="text-rt-muted mb-6">
              En un clic creamos las carpetas de contenido, instalamos páginas demo (Inicio + Sobre nosotros) y dejamos todo listo para que generes con IA.
            </p>
            <div className="flex items-center justify-center gap-4 mb-4">
              <label className="flex items-center gap-2 text-sm">
                <span className="text-rt-muted">Idioma inicial:</span>
                <select
                  value={lang}
                  onChange={(e) => setLang(e.target.value)}
                  className="border border-rt-border rounded-rt-md px-2 py-1"
                >
                  {langs.map((l) => <option key={l.slug} value={l.slug}>{l.name}</option>)}
                </select>
              </label>
              <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={seedDemo} onChange={(e) => setSeedDemo(e.target.checked)} />
                Crear contenido demo
              </label>
            </div>
            <button
              type="button"
              disabled={installing}
              onClick={() => void runInstall()}
              className="px-8 py-3 rounded-rt-md bg-rt-primary text-rt-primary-fg text-base font-medium disabled:opacity-50 shadow-lg hover:shadow-xl transition"
            >
              {installing ? 'Instalando…' : 'Instalar Replanta AI'}
            </button>
            <div className="text-xs text-rt-muted mt-4">
              También puedes hacerlo manualmente con <code>wp replanta sync</code>
            </div>
          </div>
        </div>
      )}

      <header className="border-b border-rt-border bg-white px-6 py-3 flex items-center gap-4">
        <h1 className="font-rt-serif text-xl">Replanta AI</h1>
        <nav className="flex gap-1 ml-4">
          {(['composer', 'style', 'import'] as Tab[]).map((t) => (
            <button
              key={t}
              type="button"
              onClick={() => setTab(t)}
              className={`px-3 py-1.5 rounded-rt-md text-sm ${
                tab === t ? 'bg-rt-primary text-rt-primary-fg' : 'text-rt-fg hover:bg-rt-surface'
              }`}
            >
              {t === 'composer' ? 'Composer' : t === 'style' ? 'Estilo' : 'Importar'}
            </button>
          ))}
        </nav>
        <div className="ml-auto flex items-center gap-2">
          <select
            value={lang}
            onChange={(e) => setLang(e.target.value)}
            className="text-sm border border-rt-border rounded-rt-md px-2 py-1"
          >
            {langs.map((l) => (
              <option key={l.slug} value={l.slug}>{l.name}</option>
            ))}
          </select>
          <span className="text-xs text-rt-muted">{busy ? '⏳' : status || 'listo'}</span>
        </div>
      </header>

      {tab === 'composer' && (
        <div className="grid grid-cols-[260px_1fr_320px] flex-1">
          <aside className="border-r border-rt-border bg-white p-4 overflow-y-auto">
            <div className="flex justify-between items-center mb-3">
              <div className="text-xs uppercase tracking-widest text-rt-muted">Páginas</div>
              <button
                type="button"
                className="text-xs text-rt-primary hover:underline"
                onClick={() => { void apiFetch({ path: 'replanta/v1/sync', method: 'POST' }).then(() => loadPages()); }}
              >Sync</button>
            </div>
            {Object.keys(grouped).length === 0 && (
              <div className="text-sm text-rt-muted">Sin páginas. Genera la primera ↗</div>
            )}
            {Object.entries(grouped).map(([l, items]) => (
              <div key={l} className="mb-4">
                <div className="text-[11px] uppercase font-semibold text-rt-muted mb-1">{l}</div>
                {items.map((p) => (
                  <button
                    key={p.path}
                    type="button"
                    onClick={() => void openPage(p)}
                    className={`w-full text-left px-2 py-1.5 rounded text-sm ${
                      selected?.path === p.path ? 'bg-rt-primary text-rt-primary-fg' : 'hover:bg-rt-surface'
                    }`}
                  >
                    <div className="truncate">{p.title || p.slug}</div>
                    {p.review_needed && <span className="text-[10px] text-rt-accent">revisar</span>}
                  </button>
                ))}
              </div>
            ))}
          </aside>

          <section className="p-8 overflow-y-auto">
            <div className="max-w-3xl mx-auto">
              {!selected ? (
                <>
                  <h2 className="font-rt-serif text-3xl mb-2">Composer</h2>
                  <p className="text-rt-muted mb-6">
                    Describe lo que quieres. Ej: <em>"Landing para auditoría de carbono"</em>.
                  </p>
                </>
              ) : (
                <div className="flex justify-between items-baseline mb-4">
                  <h2 className="font-rt-serif text-2xl">{selected.title || selected.slug}</h2>
                  <div className="flex gap-2">
                    <button type="button" className="text-sm text-rt-muted hover:text-rt-fg" onClick={() => { setSelected(null); setPageContent(null); }}>Cerrar</button>
                    <button type="button" className="text-sm text-red-600 hover:underline" onClick={() => void deletePage()}>Eliminar</button>
                  </div>
                </div>
              )}

              {!selected && (
                <div className="rounded-rt-lg border border-rt-border bg-white p-4">
                  <textarea
                    value={prompt}
                    onChange={(e) => setPrompt(e.target.value)}
                    onKeyDown={(e) => { if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) void generatePage(); }}
                    placeholder="Describe la página o sección…"
                    className="w-full h-32 outline-none resize-none text-base"
                  />
                  <div className="flex justify-between items-center mt-3 pt-3 border-t border-rt-border">
                    <div className="text-xs text-rt-muted">Idioma: <strong>{lang}</strong></div>
                    <button
                      type="button"
                      disabled={busy || !prompt.trim()}
                      onClick={() => void generatePage()}
                      className="px-4 py-2 rounded-rt-md bg-rt-primary text-rt-primary-fg text-sm font-medium disabled:opacity-50"
                    >
                      Generar (⌘↵)
                    </button>
                  </div>
                </div>
              )}

              {selected && pageContent && (
                <div className="space-y-4">
                  <details className="rounded-rt-lg border border-rt-border bg-white p-3" open>
                    <summary className="cursor-pointer text-sm font-medium">Frontmatter</summary>
                    <textarea
                      value={JSON.stringify(pageContent.frontmatter, null, 2)}
                      onChange={(e) => {
                        try {
                          setPageContent({ ...pageContent, frontmatter: JSON.parse(e.target.value) });
                        } catch { /* ignore parse mid-edit */ }
                      }}
                      className="w-full font-mono text-xs h-40 mt-2 outline-none resize-none"
                    />
                  </details>
                  <div className="rounded-rt-lg border border-rt-border bg-white p-3">
                    <div className="text-sm font-medium mb-2">Body MDX</div>
                    <textarea
                      value={pageContent.body}
                      onChange={(e) => setPageContent({ ...pageContent, body: e.target.value })}
                      className="w-full font-mono text-sm h-96 outline-none resize-none"
                    />
                  </div>
                  <div className="flex gap-2">
                    <button type="button" disabled={busy} onClick={() => void savePage()}
                      className="px-4 py-2 rounded-rt-md bg-rt-primary text-rt-primary-fg text-sm font-medium disabled:opacity-50">
                      Guardar
                    </button>
                  </div>
                </div>
              )}
            </div>
          </section>

          <aside className="border-l border-rt-border bg-white p-4 overflow-y-auto">
            <div className="text-xs uppercase tracking-widest text-rt-muted mb-3">Inspector</div>
            {!selected && <div className="text-sm text-rt-muted">Selecciona una página.</div>}
            {selected && (
              <div className="space-y-4">
                <div>
                  <div className="text-xs text-rt-muted mb-1">Idioma</div>
                  <div className="text-sm">{selected.lang}</div>
                </div>
                <div>
                  <div className="text-xs text-rt-muted mb-1">Slug</div>
                  <div className="text-sm font-mono">{selected.slug}</div>
                </div>
                <div>
                  <div className="text-xs text-rt-muted mb-2">Traducir a</div>
                  <div className="flex flex-wrap gap-1">
                    {langs.filter((l) => l.slug !== selected.lang).map((l) => (
                      <button
                        key={l.slug}
                        type="button"
                        disabled={busy}
                        onClick={() => void translatePage(l.slug)}
                        className="text-xs px-2 py-1 rounded bg-rt-surface hover:bg-rt-border disabled:opacity-50"
                      >
                        {l.name}
                      </button>
                    ))}
                  </div>
                </div>
              </div>
            )}
          </aside>
        </div>
      )}

      {tab === 'style' && (
        <section className="p-8 max-w-4xl mx-auto w-full">
          <h2 className="font-rt-serif text-2xl mb-4">Style Packs</h2>
          <p className="text-rt-muted mb-6">Aplica un preset visual completo (paleta + tipografía + densidad + radio).</p>
          <div className="grid grid-cols-3 gap-4">
            {packs.map((p) => (
              <button
                key={p.id}
                type="button"
                onClick={() => void applyPack(p.id)}
                className={`text-left p-4 rounded-rt-lg border-2 transition ${
                  activePack === p.id ? 'border-rt-primary' : 'border-rt-border hover:border-rt-muted'
                }`}
                style={{ background: p.palette.bg, color: p.palette.fg }}
              >
                <div className="font-medium mb-2">{p.name}</div>
                <div className="flex gap-1 mb-2">
                  {Object.values(p.palette).slice(0, 5).map((c, i) => (
                    <div key={i} className="w-6 h-6 rounded" style={{ background: c }} />
                  ))}
                </div>
                <div className="text-xs opacity-70">{p.fonts.serif} · {p.density} · {p.radius}</div>
                {activePack === p.id && <div className="text-xs mt-2 font-medium">Activo</div>}
              </button>
            ))}
          </div>
        </section>
      )}

      {tab === 'import' && (
        <section className="p-8 max-w-4xl mx-auto w-full">
          <h2 className="font-rt-serif text-2xl mb-4">Importar Elementor</h2>
          <p className="text-rt-muted mb-6">Convierte páginas Elementor a MDX. Requerirá revisión.</p>
          {elementorPosts.length === 0 ? (
            <div className="text-sm text-rt-muted">No hay páginas Elementor.</div>
          ) : (
            <div className="rounded-rt-lg border border-rt-border bg-white divide-y divide-rt-border">
              {elementorPosts.map((p) => (
                <div key={p.id} className="flex items-center justify-between p-3">
                  <div>
                    <div className="font-medium text-sm">{p.title}</div>
                    <div className="text-xs text-rt-muted">{p.type} · {p.status} · /{p.slug}</div>
                  </div>
                  <button
                    type="button"
                    disabled={busy}
                    onClick={() => void importElementor(p.id)}
                    className="px-3 py-1.5 text-sm rounded-rt-md bg-rt-primary text-rt-primary-fg disabled:opacity-50"
                  >
                    Importar a {lang}
                  </button>
                </div>
              ))}
            </div>
          )}
        </section>
      )}
    </div>
  );
};
