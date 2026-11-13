// Native UI tests compile against the runner's own Xcode instead of a prebuilt third-party driver.
import {spawnSync} from 'node:child_process';
import {readFileSync, writeFileSync, mkdirSync, existsSync, copyFileSync} from 'node:fs';
import {join} from 'node:path';
import {root} from './config.mjs';
export function captureWithXCTest({id, kind, locale, bundleId, folder, names}) {
  const dir=join(root,'release-out/capture-runner');
  const logs=join(root,'release-out/logs/capture');
  mkdirSync(dir,{recursive:true}); mkdirSync(logs,{recursive:true});
  let source=readFileSync(new URL('./native-capture/CaptureTests.swift.template',import.meta.url),'utf8');
  const tr=locale==='tr-TR';
  const values={BUNDLE_ID:bundleId,DEVICE_PREFIX:`${kind}-`,LANGUAGES:tr?'(tr)':'(en)',LOCALE:locale.replace('-','_'),CONTACTS:['Kişiler','Contacts'],POSTS:['Yazılar','Posts'],MESSAGES:['WhatsApp','Mesajlar','Messages'],MORE:['Menü','Menu']};
  for(const [key,value] of Object.entries(values)) source=source.replace(`__${key}__`,JSON.stringify(value));
  writeFileSync(join(dir,'CaptureTests.swift'),source);
  const spec={name:'FluxStoreCapture',targets:{CaptureTests:{type:'bundle.ui-testing',platform:'iOS',deploymentTarget:'17.0',sources:[{path:'CaptureTests.swift'}],settings:{base:{GENERATE_INFOPLIST_FILE:'YES',PRODUCT_BUNDLE_IDENTIFIER:'com.fluxwp.capture.tests',CODE_SIGNING_ALLOWED:'NO',SWIFT_VERSION:'5.0',TARGETED_DEVICE_FAMILY:'1,2'}}}},schemes:{FluxStoreCapture:{build:{targets:{CaptureTests:'all'}},test:{targets:['CaptureTests']}}}};
  writeFileSync(join(dir,'project.json'),JSON.stringify(spec,null,2));
  const generated=spawnSync('xcodegen',['generate','--spec',join(dir,'project.json')],{cwd:dir,stdio:'inherit'});
  if(generated.status) throw new Error('Cannot generate native UI test project');
  const result=join(logs,`${kind}-${locale}.xcresult`);
  const test=spawnSync('xcodebuild',['test','-project',join(dir,'FluxStoreCapture.xcodeproj'),'-scheme','FluxStoreCapture','-destination',`platform=iOS Simulator,id=${id}`,'-parallel-testing-enabled','NO','-derivedDataPath',join(dir,'DerivedData'),'-resultBundlePath',result,'CODE_SIGNING_ALLOWED=NO'],{cwd:dir,stdio:'inherit',timeout:20*60*1000});
  if(existsSync(result)) {
    const attachments=join(dir,'attachments');
    const exported=spawnSync('xcrun',['xcresulttool','export','attachments','--path',result,'--output-path',attachments],{stdio:'inherit'});
    if(!exported.status) {
      const details=JSON.parse(readFileSync(join(attachments,'manifest.json'),'utf8'));
      const all=details.flatMap(d=>d.attachments);
      for(const name of names) {
        const matches=all.filter(a=>a.suggestedHumanReadableName.startsWith(name));
        if(matches.length===1) copyFileSync(join(attachments,matches[0].exportedFileName),join(folder,`${name}.png`));
      }
    }
  }
  if(test.status!==0) throw new Error(`Native XCTest capture failed (${test.status ?? test.error?.message})`);
}
