/**
 * Globals injected by wp_localize_script (RT_ADMIN).
 */
export type RTLanguage = { slug: string; name: string };

export type RTAdminGlobals = {
  restUrl: string;
  nonce: string;
  themeUrl: string;
  languages: RTLanguage[];
  siteUrl: string;
  version: string;
};

declare global {
  interface Window {
    RT_ADMIN: RTAdminGlobals;
  }
}

export {};
