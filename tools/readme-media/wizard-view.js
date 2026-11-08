// Navigate the real wizard. Hold the first apply request for the installation
// screenshot, then discard it when the page closes; never change fixture content.
async function wizardView(page, view) {
 await page.locator('#cTerms').check();
 await page.locator('#startBtn').click();
 await page.locator('#step1.on').waitFor({timeout:20000});
 if(view==='base')return;
 await page.locator('#nextBtn').click();
 if(view==='plugins')return;
 for(let i=0;i<20;i++){
  if(await page.locator('.step.on .app').count())break;
  await page.locator('#nextBtn').click();
 }
 if(!await page.locator('.step.on .app').count())throw Error('Appearance step was not reached');
 if(view==='appearance')return;
 if(view!=='install')throw Error('Unknown wizard view');
 // Leave the actual request pending, without a fabricated response or HTML.
 await page.route('**/admin-ajax.php',route=>{
  if(new URLSearchParams(route.request().postData()||'').get('action')==='flxk_onboarding_step')return;
  return route.continue();
 });
 for(let i=0;i<8;i++){
  if(await page.locator('#stage').count())break;
  await page.locator('#nextBtn').click();
 }
 await page.locator('#stage').waitFor();
 await page.waitForTimeout(1500);
}
module.exports={wizardView};
