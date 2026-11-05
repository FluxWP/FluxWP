import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { createHash } from 'node:crypto';
import { root } from './config.mjs';
import { nativeInputHash } from './native-inputs.mjs';
const dir=join(root,'release-out/store-assets');
const parts=readdirSync(dir).filter(f=>/^manifest-(iphone|ipad)-(tr-TR|en-US)\.json$/.test(f));
if(parts.length!==4) throw new Error('All four native device/locale captures must succeed');
const files=[];
const hash=nativeInputHash();
const captureCommit=process.env.CAPTURE_COMMIT || process.env.GITHUB_SHA;
const nativeSources=new Set();
for(const part of parts) {
 const m=JSON.parse(readFileSync(join(dir,part),'utf8'));
 if(m.native!==true||m.brand!==process.env.FLUX_BRAND||m.commit!==captureCommit||m.nativeInputHash!==hash||m.files.length!==6) throw new Error('Native capture manifest mismatch');
 nativeSources.add(m.sourceCommit);
 for(const f of m.files) {
  if(!/^ios\/(tr|en-US)\/(iphone|ipad)-[a-z0-9-]+\.png$/.test(f.path)) throw new Error('Invalid native screenshot path');
  const b=readFileSync(join(dir,f.path));
  if(createHash('sha256').update(b).digest('hex')!==f.sha256 || b.readUInt32BE(16)!==f.width || b.readUInt32BE(20)!==f.height) throw new Error('Native screenshot hash/dimensions mismatch');
  files.push(f);
 }
}
if(new Set(files.map(f=>f.path)).size!==24) throw new Error('Duplicate/missing native screenshots');
if(nativeSources.size!==1 || !/^[a-f0-9]{40}$/.test([...nativeSources][0] || '')) throw new Error('Native build provenance mismatch');
writeFileSync(join(dir,'manifest.json'),JSON.stringify({native:true,brand:process.env.FLUX_BRAND,commit:process.env.GITHUB_SHA,captureCommit,sourceCaptureRunId:process.env.CAPTURE_RUN_ID || process.env.GITHUB_RUN_ID,nativeInputHash:hash,nativeBuildCommit:[...nativeSources][0],files},null,2));
console.log('Verified 24 real native screenshots across iPhone/iPad and Turkish/English.');
