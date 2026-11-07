import { expect, it } from 'vitest';
import schema from '../demo/ai-settings-schema.json';
import en from '../i18n/en.json';
import tr from '../i18n/tr.json';

it('has localized labels for every server-defined AI setting, section and option',()=>{
 for(const locale of [en,tr])for(const [key,field] of Object.entries(schema.fields)){
  expect((locale.aiSettings.fields as Record<string,string>)[key],`Missing setting label: ${key}`).toBeTruthy();
  expect((locale.aiSettings.sections as Record<string,string>)[field.section],`Missing section: ${field.section}`).toBeTruthy();
  if('options' in field)for(const option of field.options)expect((locale.aiSettings.options as Record<string,string>)[option],`Missing option: ${option}`).toBeTruthy();
 }
});
