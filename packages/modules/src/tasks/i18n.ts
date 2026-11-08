// Canonical keys are English; aliases accept older task-engine payloads.
import { normalizeLocale } from '@fluxwp/core';
import aliases from './aliases.json';
import en from './en.json';
import tr from './tr.json';
import de from './de.json';
import es from './es.json';
import fr from './fr.json';
import pt_br from './pt-br.json';
import ja from './ja.json';
import zh from './zh.json';
import ar from './ar.json';
import ru from './ru.json';
const dicts: Record<string, Record<string,string>> = {"en":en,"tr":tr,"de":de,"es":es,"fr":fr,"pt-br":pt_br,"ja":ja,"zh":zh,"ar":ar,"ru":ru};
let lang = 'tr';
export function setYtgLang(value: string) { lang = normalizeLocale(value); }
export function flxgLocale() { return lang === 'pt-br' ? 'pt-BR' : lang === 'zh' ? 'zh-CN' : lang; }
export function tt(source: string, vars?: Record<string, string | number>) {
 const key = (aliases as Record<string,string>)[source] || source;
 let out = dicts[lang]?.[key] || dicts.en?.[key] || key;
 if (vars) for (const [k,v] of Object.entries(vars)) out=out.split('{'+k+'}').join(String(v));
 return out;
}
