import{g as e,m as t}from"./flux-host-BqVCsq0s.js";var n=new Set([`UNAUTHENTICATED`,`FORBIDDEN`,`NOT_FOUND`,`INVALID_INPUT`,`CONFLICT`,`RATE_LIMITED`,`INTERNAL`,`PERSISTED_QUERY_NOT_FOUND`,`QUERY_NOT_ALLOWED`,`NETWORK`,`BAD_RESPONSE`]),r={401:`UNAUTHENTICATED`,403:`FORBIDDEN`,404:`NOT_FOUND`,429:`RATE_LIMITED`},i=class extends Error{constructor(t,i,a,o){let s=a[0];super(s?.message||o||`GraphQL request failed`),e(this,`code`,void 0),e(this,`field`,void 0),e(this,`errors`,void 0),e(this,`status`,void 0),e(this,`operation`,void 0),this.name=`FlxGraphQLError`,this.operation=t,this.status=i,this.errors=a;let c=s?.extensions?.code;this.code=c&&n.has(c)?c:o??r[i]??`INTERNAL`,this.field=typeof s?.extensions?.field==`string`?s.extensions.field:void 0}},a=e=>e instanceof i,o=`../graphql`,s=2e3,c=10,l=new WeakMap,ee=e=>Array.isArray(e)&&e.every(e=>typeof e==`object`&&!!e&&typeof e.message==`string`);function u(e){let n=e.persisted!==!1,r=e.batch!==!1;async function a(t,n,r,i){let a=n&&typeof n==`object`&&Object.keys(n).length?n:void 0,c={raw:!0,throwOnError:!1,signal:i.signal,headers:{"X-Flux-GraphQL-Operation":t.name,...i.headers}};if(t.kind===`query`&&!r){let n={queryId:t.id,operationName:t.name,variables:a?JSON.stringify(a):void 0};if(Object.values(n).reduce((e,t)=>e+(t?encodeURIComponent(t).length+16:0),0)<s)return e.api(o,{...c,method:`GET`,query:n})}let l=r?{query:t.document,operationName:t.name,variables:a}:{queryId:t.id,operationName:t.name,variables:a};return e.api(o,{...c,method:`POST`,body:l})}async function u(t){let n=t.filter(e=>!e.ro.signal?.aborted||(e.reject(e.ro.signal.reason??Error(`aborted`)),!1));if(n.length){if(n.length===1){let e=n[0];a(e.op,e.variables,!1,e.ro).then(e.resolve,e.reject)}else for(let t=0;t<n.length;t+=c){let r=n.slice(t,t+c),i=r.map(e=>{let t=e.variables,n=t&&typeof t==`object`&&Object.keys(t).length?t:void 0;return{queryId:e.op.id,operationName:e.op.name,variables:n}});e.api(o,{raw:!0,throwOnError:!1,method:`POST`,body:i,headers:{"X-Flux-GraphQL-Operation":r.map(e=>e.op.name).join(`,`)}}).then(e=>{let t=e.data;if(!Array.isArray(t)||t.length!==r.length)for(let e of r)a(e.op,e.variables,!1,e.ro).then(e.resolve,e.reject);else r.forEach((n,r)=>n.resolve({...e,data:t[r]}))},e=>{for(let t of r)t.reject(e)})}}}function d(t,n,i){if(!r||t.kind!==`query`||i.headers)return a(t,n,!1,i);let o=l.get(e.api);o||(o={items:[],timer:null},l.set(e.api,o));let s=o;return new Promise((e,r)=>{s.items.push({op:t,variables:n,ro:i,resolve:e,reject:r}),s.timer||(s.timer=setTimeout(()=>{let e=s.items;s.items=[],s.timer=null,u(e)},0))})}async function f(e,...r){let o=r[0],s=r[1]??{},c;try{c=n?await d(e,o,s):await a(e,o,!0,s),n&&c.data?.errors?.some(e=>e.extensions?.code===`PERSISTED_QUERY_NOT_FOUND`)&&(c=await a(e,o,!0,s))}catch(n){throw n instanceof t?new i(e.name,n.status,[{message:n.message}],n.code===`invalid_json`?`BAD_RESPONSE`:`NETWORK`):n}let l=c.data,u=ee(l?.errors)?l.errors:[],f=l?.data;if(typeof f!=`object`||!f)throw new i(e.name,c.status,u.length?u:[{message:`Empty GraphQL response`}],u.length?void 0:`BAD_RESPONSE`);if(u.length){let t=new Set(u.map(e=>e.path?.[0]).filter(e=>typeof e==`string`));if([...t].some(e=>f[e]===null)||!t.size)throw new i(e.name,c.status,u)}return f}return{request:f}}var d={id:`1ca49b34f117b7ac552c929bde0588899c760959aa9b79609f629786e0b10362`,name:`FlxAdminCapsRestore`,kind:`mutation`,plugin:`flx-admin`,document:`mutation FlxAdminCapsRestore($input: FlxAdminCapsRestoreInput!) {
  flxAdminCapsRestore(input: $input) {
    caps {
      ...FlxAdminCapsFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAdminCapsFields on FlxAdminCaps {
  roles {
    key
    label
    userCount
    isAdministrator
    granted
  }
  groups {
    key
    label
    capabilities {
      key
      label
    }
  }
  backupAt {
    iso
    label
  }
}`},f={id:`e13320fd86a143dffd7f3d635d5c1d71e5fb878e8704d8d973d71573220d6105`,name:`FlxAdminCapsSave`,kind:`mutation`,plugin:`flx-admin`,document:`mutation FlxAdminCapsSave($input: FlxAdminCapsSaveInput!) {
  flxAdminCapsSave(input: $input) {
    caps {
      ...FlxAdminCapsFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAdminCapsFields on FlxAdminCaps {
  roles {
    key
    label
    userCount
    isAdministrator
    granted
  }
  groups {
    key
    label
    capabilities {
      key
      label
    }
  }
  backupAt {
    iso
    label
  }
}`},te={id:`0466b867177e171b259ec3667c8becb5d880f4519688510e3af1bbb8e6a7a427`,name:`FlxAdminCapsScreen`,kind:`query`,plugin:`flx-admin`,document:`query FlxAdminCapsScreen {
  flxAdminCaps {
    ...FlxAdminCapsFields
  }
}

fragment FlxAdminCapsFields on FlxAdminCaps {
  roles {
    key
    label
    userCount
    isAdministrator
    granted
  }
  groups {
    key
    label
    capabilities {
      key
      label
    }
  }
  backupAt {
    iso
    label
  }
}`},p={id:`b8757243aa99ee7e2a5a102a2badaeb0fa69f51a6e630fba153831919da6e3d5`,name:`FlxAdminMenuLayoutsSave`,kind:`mutation`,plugin:`flx-admin`,document:`mutation FlxAdminMenuLayoutsSave($input: FlxAdminMenuLayoutsSaveInput!) {
  flxAdminMenuLayoutsSave(input: $input) {
    layouts {
      ...FlxAdminMenuLayoutFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAdminMenuEntryFields on FlxAdminMenuEntry {
  type
  label
  collapsed
  slug
  hidden
  blocked
  subHidden
  platformsOff
}

fragment FlxAdminMenuLayoutFields on FlxAdminMenuLayout {
  role
  custom
  entries {
    ...FlxAdminMenuEntryFields
  }
  bottom
  access
}`},m={id:`6abf2fee616f4018f122022f2fe89daeeae06431b8ef159b3aa0c5e88a85d27d`,name:`FlxAdminMenuScreen`,kind:`query`,plugin:`flx-admin`,document:`query FlxAdminMenuScreen {
  flxAdminMenuEditor {
    catalog {
      slug
      label
      capability
      icon
      iconSvg
      url
      submenu {
        slug
        label
        capability
      }
    }
    roles {
      key
      label
      userCount
    }
    layouts {
      ...FlxAdminMenuLayoutFields
    }
    defaults {
      entries {
        ...FlxAdminMenuEntryFields
      }
      bottom
    }
    platforms {
      key
      label
    }
    unblockable
  }
}

fragment FlxAdminMenuEntryFields on FlxAdminMenuEntry {
  type
  label
  collapsed
  slug
  hidden
  blocked
  subHidden
  platformsOff
}

fragment FlxAdminMenuLayoutFields on FlxAdminMenuLayout {
  role
  custom
  entries {
    ...FlxAdminMenuEntryFields
  }
  bottom
  access
}`},h={id:`c0e62c44430bcb441296f9b3f7f887fbebcb206f051781dcdec9d4ee6fab3265`,name:`FlxAdminSettingsScreen`,kind:`query`,plugin:`flx-admin`,document:`query FlxAdminSettingsScreen {
  flxAdminSettings {
    ...FlxAdminSettingsFields
  }
  flxAdminSettingsConfig {
    ...FlxAdminSettingsConfigFields
  }
}

fragment FlxAdminColorsFields on FlxAdminColors {
  bg
  side
  surface
  text
}

fragment FlxAdminSettingsConfigFields on FlxAdminSettingsConfig {
  siteName
  defaultAccent
  colorDefaults {
    light {
      ...FlxAdminColorsFields
    }
    dark {
      ...FlxAdminColorsFields
    }
  }
  dashStatChoices {
    key
    label
  }
  pwaPossible
  pwaIconUrl
  pwaDefaultName
  pwaDefaultShort
  systemUrl
}

fragment FlxAdminSettingsFields on FlxAdminSettings {
  enabled
  brand
  subtitle
  logo {
    id
    name
    thumb
    url
  }
  logoUrl
  accent
  mode
  darkExclude
  lineIcons
  logoStyle
  bottomBar
  appRail
  appSwitcher
  fastNav
  palette
  login
  heartbeat
  theme {
    radius
    font
    fontCustom
    fontSize
    density
    light {
      ...FlxAdminColorsFields
    }
    dark {
      ...FlxAdminColorsFields
    }
  }
  dashHeader
  dashStats
  quickLinks
  pwa
  pwaName
  pwaShort
}`},g={id:`c262d266ac9488a61594ff3814564ee4fa1f1acde705b4fed210d59eca7046bc`,name:`FlxAdminSettingsUpdate`,kind:`mutation`,plugin:`flx-admin`,document:`mutation FlxAdminSettingsUpdate($input: FlxAdminSettingsUpdateInput!) {
  flxAdminSettingsUpdate(input: $input) {
    settings {
      ...FlxAdminSettingsFields
    }
    config {
      ...FlxAdminSettingsConfigFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAdminColorsFields on FlxAdminColors {
  bg
  side
  surface
  text
}

fragment FlxAdminSettingsConfigFields on FlxAdminSettingsConfig {
  siteName
  defaultAccent
  colorDefaults {
    light {
      ...FlxAdminColorsFields
    }
    dark {
      ...FlxAdminColorsFields
    }
  }
  dashStatChoices {
    key
    label
  }
  pwaPossible
  pwaIconUrl
  pwaDefaultName
  pwaDefaultShort
  systemUrl
}

fragment FlxAdminSettingsFields on FlxAdminSettings {
  enabled
  brand
  subtitle
  logo {
    id
    name
    thumb
    url
  }
  logoUrl
  accent
  mode
  darkExclude
  lineIcons
  logoStyle
  bottomBar
  appRail
  appSwitcher
  fastNav
  palette
  login
  heartbeat
  theme {
    radius
    font
    fontCustom
    fontSize
    density
    light {
      ...FlxAdminColorsFields
    }
    dark {
      ...FlxAdminColorsFields
    }
  }
  dashHeader
  dashStats
  quickLinks
  pwa
  pwaName
  pwaShort
}`},_={id:`15576c276018086831114da1ff501c31ce16011c5c4d42a30db693cc098ad75b`,name:`FlxAdsAudienceCreate`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsAudienceCreate($input: FlxAdsAudienceCreateInput!) {
  flxAdsAudienceCreate(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},v={id:`8e00b2b06e97bddea533211028e4625fbe614cdeff5df60b79721a1bf3a447f8`,name:`FlxAdsAudienceDelete`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsAudienceDelete($input: FlxAdsAudienceDeleteInput!) {
  flxAdsAudienceDelete(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},y={id:`e331debbfac30bc1a1933d4c25decd870765d7d48be844c20f11584aa18854c4`,name:`FlxAdsAudienceLookalike`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsAudienceLookalike($input: FlxAdsAudienceLookalikeInput!) {
  flxAdsAudienceLookalike(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},b={id:`37c074f16556d30dd0de810739258178dbf8ebd7621a828ae9f001843a4b600c`,name:`FlxAdsAudiences`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsAudiences {
  flxAdsAudiences {
    ...FlxAdsAudiencesPage
  }
}

fragment FlxAdsAudience on FlxAdsAudience {
  id
  name
  kind
  metaId
  groupLabel
  purpose
  purposeLabel
  count
  syncedAt {
    iso
    label
    relative
  }
  error
  added
  removed
  canSync
  holdout
}

fragment FlxAdsAudienceSource on FlxAdsAudienceSource {
  key
  label
  people
}

fragment FlxAdsAudiencesPage on FlxAdsAudiencesPage {
  crmReady
  metaReady
  audiences {
    ...FlxAdsAudience
  }
  sources {
    ...FlxAdsAudienceSource
  }
  purposes {
    ...FlxAdsOption
  }
  customAudiences {
    ...FlxAdsOption
  }
}

fragment FlxAdsOption on FlxAdsOption {
  key
  label
}`},x={id:`ff4ef120d9023d952ce2d8437b3aa7f42ba9445c11452521e7a9f657ed37cdf0`,name:`FlxAdsAudienceSync`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsAudienceSync($input: FlxAdsAudienceSyncInput!) {
  flxAdsAudienceSync(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},S={id:`703996eb7a33f7182b5e7467dd8a5c6b40efe23f63ceda425587a57cbcf9f643`,name:`FlxAdsCampaign`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsCampaign($id: String!, $range: String) {
  flxAdsCampaign(id: $id, range: $range) {
    ...FlxAdsCampaignDetail
  }
}

fragment FlxAdsAdRow on FlxAdsAdRow {
  id
  name
  effective
  statusLabel
  statusTone
  thumb
  title
  canPause
  active
  spend {
    amount
    currency
    formatted
  }
  impressions
  clicks
  ctr
}

fragment FlxAdsAdsetRow on FlxAdsAdsetRow {
  id
  name
  effective
  statusLabel
  statusTone
  budgetLabel
  who
  canPause
  active
  dailyBudget
  spend {
    amount
    currency
    formatted
  }
  impressions
  clicks
  ctr
  ads {
    ...FlxAdsAdRow
  }
}

fragment FlxAdsBreakdownRow on FlxAdsBreakdownRow {
  label
  spend {
    amount
    currency
    formatted
  }
  clicks
  share
}

fragment FlxAdsCampaignDetail on FlxAdsCampaignDetail {
  range
  error
  currency
  id
  name
  effective
  statusLabel
  statusTone
  summary
  canPause
  active
  dailyBudget
  adsets {
    ...FlxAdsAdsetRow
  }
  ageGender {
    ...FlxAdsBreakdownRow
  }
  placement {
    ...FlxAdsBreakdownRow
  }
}`},C={id:`7ecd311b8a7e72f9192857c757cd218bc834ec7ffc3d6a498f99fde98c124c54`,name:`FlxAdsCampaignBudget`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsCampaignBudget($input: FlxAdsCampaignBudgetInput!) {
  flxAdsCampaignBudget(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},w={id:`53a30b5fbd31c8b762b44748f6ff3bc66fd2501fceece1d2d19f4bfefc14296a`,name:`FlxAdsCampaigns`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsCampaigns($range: String) {
  flxAdsCampaigns(range: $range) {
    ...FlxAdsCampaignList
  }
}

fragment FlxAdsCampaignList on FlxAdsCampaignList {
  range
  ranges {
    ...FlxAdsOption
  }
  error
  currency
  since
  until
  sinceLabel
  untilLabel
  totals {
    ...FlxAdsCampaignTotals
  }
  rows {
    ...FlxAdsCampaignRow
  }
}

fragment FlxAdsCampaignRow on FlxAdsCampaignRow {
  id
  name
  effective
  statusLabel
  statusTone
  objective
  objectiveLabel
  budgetLabel
  canPause
  active
  dailyBudget
  spend {
    amount
    currency
    formatted
  }
  impressions
  clicks
  ctr
  payments
  income {
    amount
    currency
    formatted
  }
  roas
  roasLevel
}

fragment FlxAdsCampaignTotals on FlxAdsCampaignTotals {
  spend {
    amount
    currency
    formatted
  }
  income {
    amount
    currency
    formatted
  }
  payments
  roas
  active
  count
}

fragment FlxAdsOption on FlxAdsOption {
  key
  label
}`},T={id:`f4fc0945c371238c1d09e1e55c05d2104f3c6ac94ba5ad3df1ebb799eb5ae1bd`,name:`FlxAdsCampaignStatus`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsCampaignStatus($input: FlxAdsCampaignStatusInput!) {
  flxAdsCampaignStatus(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},E={id:`9366271e73db1168ecb85a047953c4758cbe2dd3a4c91b3cd7fdcbd2f3f79e18`,name:`FlxAdsChangeHistory`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsChangeHistory($limit: Int) {
  flxAdsChangeHistory(limit: $limit) {
    ...FlxAdsChange
  }
}

fragment FlxAdsChange on FlxAdsChange {
  at {
    iso
    label
    relative
  }
  name
  change
  who
  channel
}`},D={id:`c862bf2e2dfad06e84f85be950d8696400d6faa25ba3480c36283bcface3e422`,name:`FlxAdsConnectionTest`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsConnectionTest($input: FlxAdsConnectionTestInput!) {
  flxAdsConnectionTest(input: $input) {
    test {
      ...FlxAdsTest
    }
    settings {
      ...FlxAdsSettings
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAdsPlatformState on FlxAdsPlatformState {
  key
  label
  on
  ready
  canTest
  test {
    ...FlxAdsTest
  }
}

fragment FlxAdsSecret on FlxAdsSecret {
  set
  mask
  fromConfig
}

fragment FlxAdsSettings on FlxAdsSettings {
  metaOn
  metaPixel
  metaToken {
    ...FlxAdsSecret
  }
  metaTest
  metaAccount
  metaPage
  metaAdsToken {
    ...FlxAdsSecret
  }
  tiktokOn
  tiktokPixel
  tiktokToken {
    ...FlxAdsSecret
  }
  tiktokTest
  ga4On
  ga4Id
  ga4ThemeId
  ga4Secret {
    ...FlxAdsSecret
  }
  consent
  pii
  browser
  windowDays
  optRoas
  optMinSpend
  platforms {
    ...FlxAdsPlatformState
  }
}

fragment FlxAdsTest on FlxAdsTest {
  ok
  message
  at {
    iso
    label
    relative
  }
}`},O={id:`683c3cf442655c7a16dcb6d4f80e8e34076f2d19cc410f7bf1b53f473be0c5d6`,name:`FlxAdsContext`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsContext {
  flxAdsContext {
    ...FlxAdsContext
  }
}

fragment FlxAdsContext on FlxAdsContext {
  canManage
  metaReady
  aiReady
  crmReady
  openSuggestions
  baseCurrency
  version
  tabs
}`},k={id:`6c177b16c9b3bc709cd8b09885247c84de6832111cc6cf5bf2c628028e13a325`,name:`FlxAdsDraft`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsDraft($id: String!) {
  flxAdsDraft(id: $id) {
    ...FlxAdsDraft
  }
}

fragment FlxAdsDraft on FlxAdsDraft {
  id
  status
  name
  objective
  link
  dailyBudget
  start
  end
  adsets {
    ...FlxAdsDraftAdset
  }
  ads {
    ...FlxAdsDraftAd
  }
  notes
  brief
  sent
  error
  sendSummary
}

fragment FlxAdsDraftAd on FlxAdsDraftAd {
  name
  text
  headline
  description
  cta
  image
}

fragment FlxAdsDraftAdset on FlxAdsDraftAdset {
  name
  ageMin
  ageMax
  gender
  countries
  audiences
  exclude
  share
}`},A={id:`c997b63c709ba8710ac23ba497313e41cd5eee794dfc2325e7a260ec4c663148`,name:`FlxAdsDraftDelete`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsDraftDelete($input: FlxAdsDraftDeleteInput!) {
  flxAdsDraftDelete(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},j={id:`89515abbbb3f4bf1d730ff46dab90bd993a25a75f787d3d80828f561f64922e2`,name:`FlxAdsDraftDuplicate`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsDraftDuplicate($input: FlxAdsDraftDuplicateInput!) {
  flxAdsDraftDuplicate(input: $input) {
    id
    errors {
      code
      message
      field
    }
  }
}`},M={id:`5ceedef8952188c75b1fb5538e26d371371a6d0f40200f3828a72655d1927b49`,name:`FlxAdsDraftGenerate`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsDraftGenerate($input: FlxAdsDraftGenerateInput!) {
  flxAdsDraftGenerate(input: $input) {
    id
    draft {
      ...FlxAdsDraft
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAdsDraft on FlxAdsDraft {
  id
  status
  name
  objective
  link
  dailyBudget
  start
  end
  adsets {
    ...FlxAdsDraftAdset
  }
  ads {
    ...FlxAdsDraftAd
  }
  notes
  brief
  sent
  error
  sendSummary
}

fragment FlxAdsDraftAd on FlxAdsDraftAd {
  name
  text
  headline
  description
  cta
  image
}

fragment FlxAdsDraftAdset on FlxAdsDraftAdset {
  name
  ageMin
  ageMax
  gender
  countries
  audiences
  exclude
  share
}`},N={id:`7879833baa361069f8eca473c5b924722775b589b7abfeaf8b404ca2a3cb76e0`,name:`FlxAdsDraftSave`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsDraftSave($input: FlxAdsDraftSaveInput!) {
  flxAdsDraftSave(input: $input) {
    id
    draft {
      ...FlxAdsDraft
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAdsDraft on FlxAdsDraft {
  id
  status
  name
  objective
  link
  dailyBudget
  start
  end
  adsets {
    ...FlxAdsDraftAdset
  }
  ads {
    ...FlxAdsDraftAd
  }
  notes
  brief
  sent
  error
  sendSummary
}

fragment FlxAdsDraftAd on FlxAdsDraftAd {
  name
  text
  headline
  description
  cta
  image
}

fragment FlxAdsDraftAdset on FlxAdsDraftAdset {
  name
  ageMin
  ageMax
  gender
  countries
  audiences
  exclude
  share
}`},P={id:`0430cfde4021f60d470591cea01d3f32d9018718afc438e364d879f2b9ce05a3`,name:`FlxAdsDraftSend`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsDraftSend($input: FlxAdsDraftSendInput!) {
  flxAdsDraftSend(input: $input) {
    ok
    draft {
      ...FlxAdsDraft
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAdsDraft on FlxAdsDraft {
  id
  status
  name
  objective
  link
  dailyBudget
  start
  end
  adsets {
    ...FlxAdsDraftAdset
  }
  ads {
    ...FlxAdsDraftAd
  }
  notes
  brief
  sent
  error
  sendSummary
}

fragment FlxAdsDraftAd on FlxAdsDraftAd {
  name
  text
  headline
  description
  cta
  image
}

fragment FlxAdsDraftAdset on FlxAdsDraftAdset {
  name
  ageMin
  ageMax
  gender
  countries
  audiences
  exclude
  share
}`},F={id:`80e7b7ac54b372c91d5f722cc70b41eabf6ea670420a9cfd1f51b79ae2ea7fee`,name:`FlxAdsExperimentChoices`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsExperimentChoices {
  flxAdsExperimentChoices {
    ...FlxAdsExperimentChoices
  }
}

fragment FlxAdsAdChoice on FlxAdsAdChoice {
  key
  label
}

fragment FlxAdsAdGroup on FlxAdsAdGroup {
  campaign
  ads {
    ...FlxAdsAdChoice
  }
}

fragment FlxAdsExperimentChoices on FlxAdsExperimentChoices {
  metaReady
  today
  defaultEnd
  adGroups {
    ...FlxAdsAdGroup
  }
  audiences {
    ...FlxAdsHoldoutAudience
  }
}

fragment FlxAdsHoldoutAudience on FlxAdsHoldoutAudience {
  id
  label
}`},I={id:`033461a44cc988fdecba7a868f94beeae673e05e99e6a0f651dcc6f8ddf15cde`,name:`FlxAdsExperimentCreate`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsExperimentCreate($input: FlxAdsExperimentCreateInput!) {
  flxAdsExperimentCreate(input: $input) {
    id
    errors {
      code
      message
      field
    }
  }
}`},L={id:`b4802892f86bf8733e5fc2ecb0b9d2ac6424cb559f383444df60206dbea03941`,name:`FlxAdsExperimentDelete`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsExperimentDelete($input: FlxAdsExperimentDeleteInput!) {
  flxAdsExperimentDelete(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},R={id:`c6afed85d499801f21da2e4e8d69e4a9c7369bcfe7a5f3559725e49ec45c846b`,name:`FlxAdsExperimentFinish`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsExperimentFinish($input: FlxAdsExperimentFinishInput!) {
  flxAdsExperimentFinish(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},z={id:`69240b9776427bfac198af5f47566fdccc7671ccac938c9659fc647594b8f82d`,name:`FlxAdsExperimentPauseLoser`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsExperimentPauseLoser($input: FlxAdsExperimentPauseLoserInput!) {
  flxAdsExperimentPauseLoser(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},B={id:`a3f180f5122d49e555d7df40fa228d92e2b708ca41c4a91320a077bdeda9b4fa`,name:`FlxAdsExperiments`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsExperiments {
  flxAdsExperiments {
    ...FlxAdsExperiment
  }
}

fragment FlxAdsExperiment on FlxAdsExperiment {
  id
  type
  typeLabel
  name
  status
  running
  start
  end
  startLabel
  endLabel
  subject
  ok
  error
  headA
  headB
  table {
    ...FlxAdsExpTableRow
  }
  winner
  confidence
  small
  verdict
  loserAd {
    ...FlxAdsLoserAd
  }
}

fragment FlxAdsExpTableRow on FlxAdsExpTableRow {
  label
  a
  b
}

fragment FlxAdsLoserAd on FlxAdsLoserAd {
  id
  name
}`},V={id:`5d9d75833ba1fb533a368cd8154d472de9ffe0d605789f804ccc81ebf680b89c`,name:`FlxAdsHeartbeat`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsHeartbeat($input: FlxAdsHeartbeatInput!) {
  flxAdsHeartbeat(input: $input) {
    ok
    openSuggestions
    errors {
      code
      message
      field
    }
  }
}`},H={id:`e0f9c37d4069b16c70279171e21bb762df8274cb70cf6d2e92170a46624d8530`,name:`FlxAdsLinkBuilder`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsLinkBuilder {
  flxAdsLinkBuilder {
    ...FlxAdsLinkBuilder
  }
}

fragment FlxAdsLinkBuilder on FlxAdsLinkBuilder {
  homeUrl
  pages
  sources {
    ...FlxAdsOption
  }
  mediums {
    ...FlxAdsOption
  }
}

fragment FlxAdsOption on FlxAdsOption {
  key
  label
}`},U={id:`19a468a8f6f5ffb5c6da168b2982608ae7d88d44e17d13e372769a1281a300ea`,name:`FlxAdsReport`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsReport($filter: FlxAdsFilterInput) {
  flxAdsReport(filter: $filter) {
    ...FlxAdsReport
  }
}

fragment FlxAdsOption on FlxAdsOption {
  key
  label
}

fragment FlxAdsRecentOrder on FlxAdsRecentOrder {
  orderId
  orderNumber
  editUrl
  paidAt {
    iso
    label
    relative
  }
  isDonation
  newPerson
  channel
  summary
  total {
    amount
    currency
    formatted
  }
}

fragment FlxAdsReport on FlxAdsReport {
  range
  from
  to
  fromLabel
  toLabel
  kind
  by
  byLabel
  dimensions {
    ...FlxAdsOption
  }
  channel
  channelLabel
  base
  totals {
    ...FlxAdsReportTotals
  }
  showSpendColumns
  rows {
    ...FlxAdsReportRow
  }
  recent {
    ...FlxAdsRecentOrder
  }
}

fragment FlxAdsReportRow on FlxAdsReportRow {
  key
  label
  channel
  channelLabel
  count
  amount {
    amount
    currency
    formatted
  }
  amounts {
    amount
    currency
    formatted
  }
  otherCurrency
  newCount
  share
  hasSpend
  spend {
    amount
    currency
    formatted
  }
  cpa {
    amount
    currency
    formatted
  }
  roas
  roasLevel
}

fragment FlxAdsReportTotals on FlxAdsReportTotals {
  count
  amount {
    amount
    currency
    formatted
  }
  adsCount
  adsAmount {
    amount
    currency
    formatted
  }
  adsPercent
  newCount
  mixed
  hasSpend
  spend {
    amount
    currency
    formatted
  }
  clicks
  roas
  spendIncome {
    amount
    currency
    formatted
  }
  spendCount
  spendNew
  cpa {
    amount
    currency
    formatted
  }
  cpn {
    amount
    currency
    formatted
  }
}`},W={id:`4d8b9511f7b03e83474289c5bc7ad01f1a223b20e72587dd16d1c9f2cb5f5c5f`,name:`FlxAdsSeasonAdd`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSeasonAdd($input: FlxAdsSeasonAddInput!) {
  flxAdsSeasonAdd(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},G={id:`78a9c32a2cdfe9c5913dbb26ef15562d0b4ccd3a9415e2fe663a555a09d4b631`,name:`FlxAdsSeasonDelete`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSeasonDelete($input: FlxAdsSeasonDeleteInput!) {
  flxAdsSeasonDelete(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},K={id:`dc07f0cade5a2084311ae381d5099ea62c1550aa997fcd471197ccba702f25b8`,name:`FlxAdsSeasons`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsSeasons {
  flxAdsSeasons {
    ...FlxAdsSeason
  }
}

fragment FlxAdsSeason on FlxAdsSeason {
  key
  id
  name
  start
  end
  startLabel
  endLabel
  approx
  custom
  state
  stateLabel
  tone
  days
  whenLabel
  hasLast
  last {
    ...FlxAdsSeasonLast
  }
  brief
}

fragment FlxAdsSeasonLast on FlxAdsSeasonLast {
  payments
  income {
    amount
    currency
    formatted
  }
  newCount
  spend {
    amount
    currency
    formatted
  }
  roas
}`},q={id:`c9356d278116835ba38b6650ff12d25e1e61e19d6fae07a5c67f5fc6a7e258dd`,name:`FlxAdsSendLog`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsSendLog {
  flxAdsSendLog {
    ...FlxAdsSendLog
  }
}

fragment FlxAdsLogRow on FlxAdsLogRow {
  id
  platform
  platformLabel
  orderId
  orderNumber
  editUrl
  value {
    amount
    currency
    formatted
  }
  status
  statusLabel
  reason
  attempts
  at {
    iso
    label
    relative
  }
  canRetry
}

fragment FlxAdsSendLog on FlxAdsSendLog {
  sent
  pending
  failed
  skipped
  rows {
    ...FlxAdsLogRow
  }
}`},J={id:`f249dbddc4f9f35a4d800d5b883d9f9b403621c899975f4afc4b8868c9f98f0b`,name:`FlxAdsSendRetry`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSendRetry($input: FlxAdsSendRetryInput!) {
  flxAdsSendRetry(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},Y={id:`79cffe4095d5387aec2dc85c9ee353e8d208749df66181b7062c27e869c1f3ab`,name:`FlxAdsSettings`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsSettings {
  flxAdsSettings {
    ...FlxAdsSettings
  }
}

fragment FlxAdsPlatformState on FlxAdsPlatformState {
  key
  label
  on
  ready
  canTest
  test {
    ...FlxAdsTest
  }
}

fragment FlxAdsSecret on FlxAdsSecret {
  set
  mask
  fromConfig
}

fragment FlxAdsSettings on FlxAdsSettings {
  metaOn
  metaPixel
  metaToken {
    ...FlxAdsSecret
  }
  metaTest
  metaAccount
  metaPage
  metaAdsToken {
    ...FlxAdsSecret
  }
  tiktokOn
  tiktokPixel
  tiktokToken {
    ...FlxAdsSecret
  }
  tiktokTest
  ga4On
  ga4Id
  ga4ThemeId
  ga4Secret {
    ...FlxAdsSecret
  }
  consent
  pii
  browser
  windowDays
  optRoas
  optMinSpend
  platforms {
    ...FlxAdsPlatformState
  }
}

fragment FlxAdsTest on FlxAdsTest {
  ok
  message
  at {
    iso
    label
    relative
  }
}`},X={id:`24a9e8646c7d2467c1f9ef0daf4c100a2a4df6971c33343e297b4ecf7582d281`,name:`FlxAdsSettingsSave`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSettingsSave($input: FlxAdsSettingsSaveInput!) {
  flxAdsSettingsSave(input: $input) {
    settings {
      ...FlxAdsSettings
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAdsPlatformState on FlxAdsPlatformState {
  key
  label
  on
  ready
  canTest
  test {
    ...FlxAdsTest
  }
}

fragment FlxAdsSecret on FlxAdsSecret {
  set
  mask
  fromConfig
}

fragment FlxAdsSettings on FlxAdsSettings {
  metaOn
  metaPixel
  metaToken {
    ...FlxAdsSecret
  }
  metaTest
  metaAccount
  metaPage
  metaAdsToken {
    ...FlxAdsSecret
  }
  tiktokOn
  tiktokPixel
  tiktokToken {
    ...FlxAdsSecret
  }
  tiktokTest
  ga4On
  ga4Id
  ga4ThemeId
  ga4Secret {
    ...FlxAdsSecret
  }
  consent
  pii
  browser
  windowDays
  optRoas
  optMinSpend
  platforms {
    ...FlxAdsPlatformState
  }
}

fragment FlxAdsTest on FlxAdsTest {
  ok
  message
  at {
    iso
    label
    relative
  }
}`},Z={id:`b7ca184a98d1ee03a35d28f0ff539562795c566ae0e48eec7bc07669e9363747`,name:`FlxAdsSpend`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsSpend($filter: FlxAdsFilterInput) {
  flxAdsSpend(filter: $filter) {
    ...FlxAdsSpendPage
  }
}

fragment FlxAdsManualSpend on FlxAdsManualSpend {
  id
  day
  dayLabel
  channel
  channelLabel
  campaign
  ad
  spend {
    amount
    currency
    formatted
  }
}

fragment FlxAdsMetaStatus on FlxAdsMetaStatus {
  ok
  at {
    iso
    label
    relative
  }
  message
  rows
  since
  until
  sinceLabel
  untilLabel
}

fragment FlxAdsOption on FlxAdsOption {
  key
  label
}

fragment FlxAdsSpendCampaign on FlxAdsSpendCampaign {
  name
  channel
  channelLabel
  spend {
    amount
    currency
    formatted
  }
}

fragment FlxAdsSpendPage on FlxAdsSpendPage {
  range
  from
  to
  base
  metaReady
  metaStatus {
    ...FlxAdsMetaStatus
  }
  campaigns {
    ...FlxAdsSpendCampaign
  }
  total {
    amount
    currency
    formatted
  }
  manual {
    ...FlxAdsManualSpend
  }
  channels {
    ...FlxAdsOption
  }
}`},Q={id:`3bd3940ea9c4e06b7dbf33031290ab8d9e6ed4fec4b0be7297d064b37f525b0c`,name:`FlxAdsSpendAdd`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSpendAdd($input: FlxAdsSpendAddInput!) {
  flxAdsSpendAdd(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},ne={id:`4c7a66d6698c203e77643a5486d518bfaab14776558bfa6a7c67ec9a695c2768`,name:`FlxAdsSpendDelete`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSpendDelete($input: FlxAdsSpendDeleteInput!) {
  flxAdsSpendDelete(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},re={id:`321a15204f3a2eeafee102500c9f05985fc678551b77b27bb41ef41ea67ca0c2`,name:`FlxAdsSpendSync`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSpendSync($input: FlxAdsSpendSyncInput!) {
  flxAdsSpendSync(input: $input) {
    status {
      ...FlxAdsMetaStatus
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAdsMetaStatus on FlxAdsMetaStatus {
  ok
  at {
    iso
    label
    relative
  }
  message
  rows
  since
  until
  sinceLabel
  untilLabel
}`},ie={id:`6402f8d915ab7a970d1096ef12af62931ed8c50029a1f5490a106a629e625e4b`,name:`FlxAdsStudio`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsStudio {
  flxAdsStudio {
    ...FlxAdsStudioPage
  }
}

fragment FlxAdsDraftRow on FlxAdsDraftRow {
  id
  name
  status
  statusLabel
  statusTone
  objective
  objectiveLabel
  dailyBudget {
    amount
    currency
    formatted
  }
  adsetCount
  adCount
  updated
  metaCampaignId
  error
}

fragment FlxAdsOption on FlxAdsOption {
  key
  label
}

fragment FlxAdsStudioPage on FlxAdsStudioPage {
  aiReady
  missing
  objectives {
    ...FlxAdsOption
  }
  ctas {
    ...FlxAdsOption
  }
  audiences {
    ...FlxAdsOption
  }
  drafts {
    ...FlxAdsDraftRow
  }
  siteUrl
}`},ae={id:`7d8a60a058ce4b48e7047831db86f4ece1d4aa76a90a5598362e7ffdb1830669`,name:`FlxAdsSuggestionApply`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSuggestionApply($input: FlxAdsSuggestionApplyInput!) {
  flxAdsSuggestionApply(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},oe={id:`f69a419a23d70261401dc4bc190e759a71413b427c8c58e9e490e32502d1c49a`,name:`FlxAdsSuggestionDismiss`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSuggestionDismiss($input: FlxAdsSuggestionDismissInput!) {
  flxAdsSuggestionDismiss(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},se={id:`0d11b8f455f8a16515111047721b1dfebd7b70f52034cc85ad2c98efdd781972`,name:`FlxAdsSuggestions`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsSuggestions {
  flxAdsSuggestions {
    ...FlxAdsSuggestionsPage
  }
}

fragment FlxAdsSuggestion on FlxAdsSuggestion {
  id
  type
  typeLabel
  tone
  created {
    iso
    label
    relative
  }
  name
  level
  context
  reason
  campaignId
  action {
    ...FlxAdsSuggestionAction
  }
  brief
  start
  end
  status
  statusLabel
  decided {
    iso
    label
    relative
  }
  who
  result
}

fragment FlxAdsSuggestionAction on FlxAdsSuggestionAction {
  field
  to
  confirm
  label
}

fragment FlxAdsSuggestionRun on FlxAdsSuggestionRun {
  at {
    iso
    label
    relative
  }
  ok
  message
  newCount
}

fragment FlxAdsSuggestionsPage on FlxAdsSuggestionsPage {
  metaReady
  run {
    ...FlxAdsSuggestionRun
  }
  targetRoas
  rulesText
  open {
    ...FlxAdsSuggestion
  }
  earlier {
    ...FlxAdsSuggestion
  }
}`},ce={id:`3311e69615d4f9a6891a6a199712bac121e9397e58daee03f7e4935d5caec43d`,name:`FlxAdsSuggestionsRefresh`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSuggestionsRefresh($input: FlxAdsSuggestionsRefreshInput!) {
  flxAdsSuggestionsRefresh(input: $input) {
    newCount
    errors {
      code
      message
      field
    }
  }
}`},le={id:`e89bbd8ee4c7bf09dcfb37612cbe5859fc64666e6e91b3d6ac45fe9826e684aa`,name:`FlxAdsValue`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsValue($filter: FlxAdsFilterInput) {
  flxAdsValue(filter: $filter) {
    ...FlxAdsValuePage
  }
}

fragment FlxAdsCohortRow on FlxAdsCohortRow {
  month
  label
  newCount
  cells {
    amount
    currency
    formatted
  }
}

fragment FlxAdsValuePage on FlxAdsValuePage {
  range
  from
  to
  kind
  by
  base
  totals {
    ...FlxAdsValueTotals
  }
  rows {
    ...FlxAdsValueRow
  }
  cohortMonths
  cohort {
    ...FlxAdsCohortRow
  }
}

fragment FlxAdsValueRow on FlxAdsValueRow {
  key
  label
  channel
  channelLabel
  newCount
  first {
    amount
    currency
    formatted
  }
  ltv90 {
    amount
    currency
    formatted
  }
  ltv {
    amount
    currency
    formatted
  }
  avg {
    amount
    currency
    formatted
  }
  rate
  hasSpend
  spend {
    amount
    currency
    formatted
  }
  cac {
    amount
    currency
    formatted
  }
  ratio
  roasLevel
  payback
}

fragment FlxAdsValueTotals on FlxAdsValueTotals {
  newCount
  adsNew
  adsAvg {
    amount
    currency
    formatted
  }
  cac {
    amount
    currency
    formatted
  }
  spend {
    amount
    currency
    formatted
  }
  ratio
  rate
}`},ue={id:`6d130e09eecfd7058aed48ff1e920186a016d4213b6a6db97977b95cd0eadc09`,name:`FlxAiApprovalDecide`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiApprovalDecide($input: FlxAiApprovalDecideInput!) {
  flxAiApprovalDecide(input: $input) {
    state
    queue {
      ...FlxAiApprovalQueueFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiApprovalQueueFields on FlxAiApprovalQueue {
  count
  items {
    chatId
    chatTitle
    callId
    tool
    title
    args
    note
    time {
      timestamp
      label
    }
    taskId
    taskTitle
  }
  changes {
    id
    title
    chatId
    taskId
    taskTitle
    created {
      timestamp
      label
    }
    files {
      path
      op
      add
      del
    }
  }
}`},de={id:`c094dd97296a5f7fae585f6813e3e91ba9655b8eebc116746747db54c0c4a842`,name:`FlxAiApprovals`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiApprovals {
  flxAiApprovals {
    ...FlxAiApprovalQueueFields
  }
}

fragment FlxAiApprovalQueueFields on FlxAiApprovalQueue {
  count
  items {
    chatId
    chatTitle
    callId
    tool
    title
    args
    note
    time {
      timestamp
      label
    }
    taskId
    taskTitle
  }
  changes {
    id
    title
    chatId
    taskId
    taskTitle
    created {
      timestamp
      label
    }
    files {
      path
      op
      add
      del
    }
  }
}`},fe={id:`2ce74187ffc690342b4345a3cde10aefb9efc6e12ea495d8531228dc9647e8fe`,name:`FlxAiBilling`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiBilling($month: String) {
  flxAiBudget {
    ...FlxAiBudgetRow
  }
  flxAiBilling(month: $month) {
    month
    csvUrl
    report {
      ...FlxAiReportFields
    }
    months {
      month
      label
      cost
      saved
      calls
    }
    credits {
      checked {
        timestamp
        label
      }
      usage
      limit
      remaining
      credits
    }
  }
}

fragment FlxAiBreakdownFields on FlxAiBreakdownRow {
  key
  label
  calls
  tools
  cost
  fullCost
  inTokens
  outTokens
  cacheRead
  modelMs
  toolMs
  errors
  partial
}

fragment FlxAiBudgetRow on FlxAiBudgetPeriod {
  key
  label
  limit
  spent
  pct
  over
  resets {
    timestamp
    label
  }
}

fragment FlxAiReportFields on FlxAiReport {
  from {
    timestamp
    label
  }
  to {
    timestamp
    label
  }
  totals {
    calls
    tools
    inTokens
    outTokens
    cacheRead
    cacheWrite
    cost
    fullCost
    saved
    modelMs
    toolMs
    errors
  }
  daily {
    date
    cost
    inTokens
    outTokens
    cacheRead
    modelMs
    toolMs
    calls
    tools
    errors
  }
  bySource {
    ...FlxAiBreakdownFields
  }
  byModel {
    ...FlxAiBreakdownFields
  }
  byPurpose {
    ...FlxAiBreakdownFields
  }
  byTool {
    key
    label
    source
    calls
    avgMs
    maxMs
    totalMs
    errors
  }
  byProvider {
    ...FlxAiBreakdownFields
  }
}`},pe={id:`b3a750b6fd0443498787e2f4e0d3d13b90d91b045d92cac228b455185e51effd`,name:`FlxAiBrand`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiBrand {
  flxAiBrand {
    ...FlxAiBrandFields
  }
}

fragment FlxAiBrandFields on FlxAiBrand {
  name
  tagline
  website
  phone
  handle
  colorPrimary
  colorAccent
  colorDark
  colorLight
  logoId
  logoUrl
  logoLightId
  logoLightUrl
  partnerIds
  partnerUrls
  notes
  defaultName
  defaultTagline
  defaultWebsite
  docs {
    name
    content
  }
  themePalette {
    slug
    name
    color
  }
}`},me={id:`da632a2d2612577c5ac2b5f6dab4e8bf8aca22f001cff20cdb512cada14694a7`,name:`FlxAiBrandSave`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiBrandSave($input: FlxAiBrandSaveInput!) {
  flxAiBrandSave(input: $input) {
    brand {
      ...FlxAiBrandFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiBrandFields on FlxAiBrand {
  name
  tagline
  website
  phone
  handle
  colorPrimary
  colorAccent
  colorDark
  colorLight
  logoId
  logoUrl
  logoLightId
  logoLightUrl
  partnerIds
  partnerUrls
  notes
  defaultName
  defaultTagline
  defaultWebsite
  docs {
    name
    content
  }
  themePalette {
    slug
    name
    color
  }
}`},he={id:`fd799eb314797b602e77f1fee7607668d0d0f868cf45d58fd8f39ffdb0f933a2`,name:`FlxAiChangeDecide`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiChangeDecide($input: FlxAiChangeDecideInput!) {
  flxAiChangeDecide(input: $input) {
    queue {
      ...FlxAiApprovalQueueFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiApprovalQueueFields on FlxAiApprovalQueue {
  count
  items {
    chatId
    chatTitle
    callId
    tool
    title
    args
    note
    time {
      timestamp
      label
    }
    taskId
    taskTitle
  }
  changes {
    id
    title
    chatId
    taskId
    taskTitle
    created {
      timestamp
      label
    }
    files {
      path
      op
      add
      del
    }
  }
}`},ge={id:`c584fcb50fda45269d3a90fa9de3e103387b2ac5e43fac141cded87bfad1e27b`,name:`FlxAiConnectionDelete`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiConnectionDelete($input: FlxAiConnectionDeleteInput!) {
  flxAiConnectionDelete(input: $input) {
    deleted
    errors {
      code
      message
      field
    }
  }
}`},_e={id:`39852a224f2180bcf910ea090988691e4000f622a8c247d1ce2a75441d414d79`,name:`FlxAiConnectionPermissions`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiConnectionPermissions($input: FlxAiConnectionPermissionsInput!) {
  flxAiConnectionPermissions(input: $input) {
    connection {
      ...FlxAiConnectionFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiConnectionFields on FlxAiConnection {
  id
  name
  url
  auth
  header
  scope
  ownerId
  ownerLogin
  roles
  enabled
  editable
  hasSecret
  clientId
  hasClientSecret
  signedIn
  serverName
  serverVersion
  serverProtocol
  checked {
    timestamp
    label
  }
  error
  status
  prefix
  toolsOn
  tools {
    name
    toolName
    title
    description
    readonly
    destructive
    permission
  }
}`},ve={id:`be1ebb411a3c87ae6a095d86587c7615125f12ac4bf5e678f37d40046c5e9622`,name:`FlxAiConnectionRefresh`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiConnectionRefresh($input: FlxAiConnectionRefreshInput!) {
  flxAiConnectionRefresh(input: $input) {
    connection {
      ...FlxAiConnectionFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiConnectionFields on FlxAiConnection {
  id
  name
  url
  auth
  header
  scope
  ownerId
  ownerLogin
  roles
  enabled
  editable
  hasSecret
  clientId
  hasClientSecret
  signedIn
  serverName
  serverVersion
  serverProtocol
  checked {
    timestamp
    label
  }
  error
  status
  prefix
  toolsOn
  tools {
    name
    toolName
    title
    description
    readonly
    destructive
    permission
  }
}`},ye={id:`19d7d049543a095326161e79e4304c65afc3dd52749dae9f123ae489315439e9`,name:`FlxAiConnections`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiConnections {
  flxAiConnections {
    callbackUrl
    roles {
      id
      label
    }
    connections {
      ...FlxAiConnectionFields
    }
  }
}

fragment FlxAiConnectionFields on FlxAiConnection {
  id
  name
  url
  auth
  header
  scope
  ownerId
  ownerLogin
  roles
  enabled
  editable
  hasSecret
  clientId
  hasClientSecret
  signedIn
  serverName
  serverVersion
  serverProtocol
  checked {
    timestamp
    label
  }
  error
  status
  prefix
  toolsOn
  tools {
    name
    toolName
    title
    description
    readonly
    destructive
    permission
  }
}`},be={id:`d08c9aeabac78f5a9134006e481bf4d5630b50827c0554077d9b4aa9c11ced72`,name:`FlxAiConnectionSave`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiConnectionSave($input: FlxAiConnectionSaveInput!) {
  flxAiConnectionSave(input: $input) {
    connection {
      ...FlxAiConnectionFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiConnectionFields on FlxAiConnection {
  id
  name
  url
  auth
  header
  scope
  ownerId
  ownerLogin
  roles
  enabled
  editable
  hasSecret
  clientId
  hasClientSecret
  signedIn
  serverName
  serverVersion
  serverProtocol
  checked {
    timestamp
    label
  }
  error
  status
  prefix
  toolsOn
  tools {
    name
    toolName
    title
    description
    readonly
    destructive
    permission
  }
}`},xe={id:`fec0e46a5655ac1d54df6a74c7d278d9c2f6cb55589b8ac91de909d6e13060d1`,name:`FlxAiConnectionSignIn`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiConnectionSignIn($input: FlxAiConnectionSignInInput!) {
  flxAiConnectionSignIn(input: $input) {
    url
    errors {
      code
      message
      field
    }
  }
}`},Se={id:`a68c54ea203579ba00071037f70a0a24d7d6c1e0d759d854afc7c26683ce66f1`,name:`FlxAiConnectionSignOut`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiConnectionSignOut($input: FlxAiConnectionSignOutInput!) {
  flxAiConnectionSignOut(input: $input) {
    connection {
      ...FlxAiConnectionFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiConnectionFields on FlxAiConnection {
  id
  name
  url
  auth
  header
  scope
  ownerId
  ownerLogin
  roles
  enabled
  editable
  hasSecret
  clientId
  hasClientSecret
  signedIn
  serverName
  serverVersion
  serverProtocol
  checked {
    timestamp
    label
  }
  error
  status
  prefix
  toolsOn
  tools {
    name
    toolName
    title
    description
    readonly
    destructive
    permission
  }
}`},Ce={id:`ecfbbe459dc33b5d5788763a9b369b151e2c6fb1b03dcffe9f31e1fa29785ab3`,name:`FlxAiCreditsRefresh`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiCreditsRefresh {
  flxAiCreditsRefresh(input: {  }) {
    credits {
      checked {
        timestamp
        label
      }
      usage
      limit
      remaining
      credits
    }
    errors {
      code
      message
      field
    }
  }
}`},we={id:`580801a095e875c9d47b645c9cb73f0f6fdb1846df5ad9d786d2efa129e9a5c8`,name:`FlxAiLogBody`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiLogBody($id: Int!) {
  flxAiLogBody(id: $id) {
    logId
    request {
      role
      text
    }
    reply
    toolCalls
    error
  }
}`},Te={id:`eebe6c676c9d99c5200a53263539c163c3c4f838ca7e6fe125ce4b190711d0b5`,name:`FlxAiLogs`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiLogs($first: Int = 50, $after: String, $days: Int = 7, $kind: FlxAiLogKind, $source: String, $errorsOnly: Boolean = false) {
  flxAiLogs(
    first: $first
    after: $after
    days: $days
    kind: $kind
    source: $source
    errorsOnly: $errorsOnly
  ) {
    nodes {
      ...FlxAiLogRowFields
    }
    pageInfo {
      hasNextPage
      endCursor
    }
    totalCount
  }
  flxAiLogSources {
    id
    label
  }
}

fragment FlxAiLogRowFields on FlxAiLog {
  databaseId
  time {
    timestamp
    label
  }
  kind
  source
  sourceLabel
  purpose
  purposeLabel
  chatId
  userId
  userName
  provider
  model
  modelLabel
  tool
  inTokens
  outTokens
  cacheRead
  cacheWrite
  cost
  estimated
  ms
  ok
  error
  errorLabel
  hasBody
}`},Ee={id:`260f5986cb85f04bab258711264790f696601d549e356726f15b2dc3f3e7579e`,name:`FlxAiMcp`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiMcp {
  flxAiMcp {
    ...FlxAiMcpOverviewFields
  }
}

fragment FlxAiMcpOverviewFields on FlxAiMcpOverview {
  server {
    enabled
    oauth
    oauthActive
    legacy
    url
    restUrl
    toolCount
    rolesUrl
  }
  keys {
    id
    name
    type
    hint
    userId
    userLogin
    canUse
    tools
    created {
      timestamp
      label
    }
    lastUsed {
      timestamp
      label
    }
  }
  choices {
    name
    title
    readonly
  }
  log {
    time {
      timestamp
      label
    }
    userLogin
    tool
    target
    ok
  }
}`},De={id:`f97f6605a6fa484382293f98d1469ce8edcb3dce4278728593da69f09438acba`,name:`FlxAiMcpKeyCreate`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiMcpKeyCreate($input: FlxAiMcpKeyCreateInput!) {
  flxAiMcpKeyCreate(input: $input) {
    key
    overview {
      ...FlxAiMcpOverviewFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiMcpOverviewFields on FlxAiMcpOverview {
  server {
    enabled
    oauth
    oauthActive
    legacy
    url
    restUrl
    toolCount
    rolesUrl
  }
  keys {
    id
    name
    type
    hint
    userId
    userLogin
    canUse
    tools
    created {
      timestamp
      label
    }
    lastUsed {
      timestamp
      label
    }
  }
  choices {
    name
    title
    readonly
  }
  log {
    time {
      timestamp
      label
    }
    userLogin
    tool
    target
    ok
  }
}`},Oe={id:`8813b1716185294ae6ba81f92817bdc89453f604190bc64b887cce9b3eb46e2c`,name:`FlxAiMcpKeyRevoke`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiMcpKeyRevoke($input: FlxAiMcpKeyRevokeInput!) {
  flxAiMcpKeyRevoke(input: $input) {
    overview {
      ...FlxAiMcpOverviewFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiMcpOverviewFields on FlxAiMcpOverview {
  server {
    enabled
    oauth
    oauthActive
    legacy
    url
    restUrl
    toolCount
    rolesUrl
  }
  keys {
    id
    name
    type
    hint
    userId
    userLogin
    canUse
    tools
    created {
      timestamp
      label
    }
    lastUsed {
      timestamp
      label
    }
  }
  choices {
    name
    title
    readonly
  }
  log {
    time {
      timestamp
      label
    }
    userLogin
    tool
    target
    ok
  }
}`},ke={id:`0ae41a85219ce4680c23392ebfeae7e05876d73a907aca2e57384f8b43c1dfb8`,name:`FlxAiMcpServerUpdate`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiMcpServerUpdate($input: FlxAiMcpServerUpdateInput!) {
  flxAiMcpServerUpdate(input: $input) {
    overview {
      ...FlxAiMcpOverviewFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiMcpOverviewFields on FlxAiMcpOverview {
  server {
    enabled
    oauth
    oauthActive
    legacy
    url
    restUrl
    toolCount
    rolesUrl
  }
  keys {
    id
    name
    type
    hint
    userId
    userLogin
    canUse
    tools
    created {
      timestamp
      label
    }
    lastUsed {
      timestamp
      label
    }
  }
  choices {
    name
    title
    readonly
  }
  log {
    time {
      timestamp
      label
    }
    userLogin
    tool
    target
    ok
  }
}`},Ae={id:`ca6dc8ad4a997533257ffb1be1de3b6e892908b13ebd29cf8132953a97a62a87`,name:`FlxAiModels`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiModels($search: String, $first: Int = 50) {
  flxAiModels(search: $search, first: $first) {
    id
    name
    family
    provider
    context
    priceIn
    priceOut
    tools
    vision
    thinking
  }
}`},je={id:`26493d21bc4cbbc74a727b78e026b640105e8157477eefcbfb36f7ba46caa432`,name:`FlxAiModelTest`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiModelTest($input: FlxAiModelTestInput!) {
  flxAiModelTest(input: $input) {
    result {
      text
      ms
    }
    errors {
      code
      message
      field
    }
  }
}`},Me={id:`a016eb829167be15c5f406279dc7b461d1610db9bd6287d84a489d71f9cc1a17`,name:`FlxAiOverview`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiOverview($days: Int = 30, $source: String) {
  flxAiBudget {
    ...FlxAiBudgetRow
  }
  flxAiReport(days: $days, source: $source) {
    ...FlxAiReportFields
  }
  flxAiLogSources {
    id
    label
  }
  flxAiSecrets {
    name
    configured
  }
}

fragment FlxAiBreakdownFields on FlxAiBreakdownRow {
  key
  label
  calls
  tools
  cost
  fullCost
  inTokens
  outTokens
  cacheRead
  modelMs
  toolMs
  errors
  partial
}

fragment FlxAiBudgetRow on FlxAiBudgetPeriod {
  key
  label
  limit
  spent
  pct
  over
  resets {
    timestamp
    label
  }
}

fragment FlxAiReportFields on FlxAiReport {
  from {
    timestamp
    label
  }
  to {
    timestamp
    label
  }
  totals {
    calls
    tools
    inTokens
    outTokens
    cacheRead
    cacheWrite
    cost
    fullCost
    saved
    modelMs
    toolMs
    errors
  }
  daily {
    date
    cost
    inTokens
    outTokens
    cacheRead
    modelMs
    toolMs
    calls
    tools
    errors
  }
  bySource {
    ...FlxAiBreakdownFields
  }
  byModel {
    ...FlxAiBreakdownFields
  }
  byPurpose {
    ...FlxAiBreakdownFields
  }
  byTool {
    key
    label
    source
    calls
    avgMs
    maxMs
    totalMs
    errors
  }
  byProvider {
    ...FlxAiBreakdownFields
  }
}`},Ne={id:`97cc803140314bc8487bb7b36e05aa5709b31e5727793e322f8fb0a42a63077c`,name:`FlxAiRecipients`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiRecipients($search: String) {
  flxAiRecipients(search: $search) {
    channels {
      email
      sms
      whatsapp
    }
    items {
      ...FlxAiRecipientFields
    }
  }
}

fragment FlxAiRecipientFields on FlxAiRecipient {
  kind
  id
  name
  email
  phone
  jid
  sub
  channels
}`},Pe={id:`6b7262310f5715f6eaf6d3d7d1864540ba7597a648899ddce2cda734997e1e95`,name:`FlxAiRenderTest`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiRenderTest($input: FlxAiRenderTestInput!) {
  flxAiRenderTest(input: $input) {
    result {
      via
      ms
      image
    }
    errors {
      code
      message
      field
    }
  }
}`},Fe={id:`fcbb3cef6e71e12ccbf8e9ff024a957a7ec475336556e2ef9136bb3784c3bec2`,name:`FlxAiSettingsScreen`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiSettingsScreen {
  flxAiSettings {
    ...FlxAiSettingsFields
  }
  flxAiSecrets {
    ...FlxAiSecretFields
  }
  flxAiSettingsOptions {
    roles {
      id
      label
    }
    webProviders {
      id
      label
    }
    mediaModels {
      id
      label
      kind
      routes
      auto
    }
    mediaProviders {
      id
      label
    }
    defaultModel
    renderer
    webMonthCount
    providersReady
  }
}

fragment FlxAiSecretFields on FlxAiSecret {
  name
  group
  label
  help
  configured
  hint
  fromConfig
}

fragment FlxAiSettingsFields on FlxAiSettings {
  anthropicWorkspace
  model
  chatModel
  titleModel
  sourceModels {
    source
    label
    model
  }
  cache
  effort
  thinking
  limitDay
  limitWeek
  limitMonth
  limitAction
  limitWarnPct
  limitNotify
  chatRoles
  chatAdmin
  chatFront
  chatMode
  chatMaxRounds
  chatHistory
  chatInstructions
  chatWidgets
  mediaImageModel
  mediaVideoModel
  mediaDays
  imageToolOn
  webOn
  webFetchOn
  shellOn
  webProvider
  webSearchMonth
  webSearchCost
  renderUrl
  renderUser
  logDays
  logBodies
  logBodyDays
  deleteData
}`},Ie={id:`142806ed14f0d030b028c91f2ebfcb6be9b808394ff42f165b17ee726731c132`,name:`FlxAiSettingsUpdate`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSettingsUpdate($input: FlxAiSettingsUpdateInput!) {
  flxAiSettingsUpdate(input: $input) {
    settings {
      ...FlxAiSettingsFields
    }
    secrets {
      ...FlxAiSecretFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiSecretFields on FlxAiSecret {
  name
  group
  label
  help
  configured
  hint
  fromConfig
}

fragment FlxAiSettingsFields on FlxAiSettings {
  anthropicWorkspace
  model
  chatModel
  titleModel
  sourceModels {
    source
    label
    model
  }
  cache
  effort
  thinking
  limitDay
  limitWeek
  limitMonth
  limitAction
  limitWarnPct
  limitNotify
  chatRoles
  chatAdmin
  chatFront
  chatMode
  chatMaxRounds
  chatHistory
  chatInstructions
  chatWidgets
  mediaImageModel
  mediaVideoModel
  mediaDays
  imageToolOn
  webOn
  webFetchOn
  shellOn
  webProvider
  webSearchMonth
  webSearchCost
  renderUrl
  renderUser
  logDays
  logBodies
  logBodyDays
  deleteData
}`},Le={id:`0487ce458b717192e900f7caef75ddca5d4567a5a6e50fcc2cdff15b640f9bc7`,name:`FlxAiSkill`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiSkill($slug: String!) {
  flxAiSkill(slug: $slug) {
    ...FlxAiSkillDetailFields
  }
}

fragment FlxAiSkillDetailFields on FlxAiSkillDetail {
  skill {
    ...FlxAiSkillFields
  }
  body
  tools
  license
  compatibility
  example
  toolNames
  files {
    path
    size
    downloadUrl
  }
  evals {
    triggers {
      query
      shouldTrigger
    }
    cases {
      id
      prompt
      expectations
    }
  }
  results {
    trigger {
      ts {
        timestamp
        label
      }
      model
      hits
      total
      rate
      cost
      partial
      rows {
        query
        shouldTrigger
        triggered
        picked
      }
    }
    functional {
      ts {
        timestamp
        label
      }
      id
      prompt
      note
      judgeCost
      with {
        ...FlxAiSkillSideFields
      }
      without {
        ...FlxAiSkillSideFields
      }
      checks {
        text
        with
        without
      }
    }
  }
}

fragment FlxAiSkillFields on FlxAiSkill {
  slug
  name
  description
  version
  status
  bundled
  source
  changed
  saved
  fileCount
  hasScripts
  tasks
  exportUrl
}

fragment FlxAiSkillSideFields on FlxAiSkillSide {
  passed
  total
  in
  out
  cost
  ms
  output
}`},Re={id:`e09f255e7a9396b3fc199130ff80d8fe83b8d433c238f9979b9f8698ba8ee13d`,name:`FlxAiSkillDelete`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSkillDelete($input: FlxAiSkillDeleteInput!) {
  flxAiSkillDelete(input: $input) {
    deleted
    errors {
      code
      message
      field
    }
  }
}`},ze={id:`372eef6b1c9464e87ea02994b5fda77f806be1bf15e9b6910fe555ec19080362`,name:`FlxAiSkillEvalsSave`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSkillEvalsSave($input: FlxAiSkillEvalsSaveInput!) {
  flxAiSkillEvalsSave(input: $input) {
    skill {
      ...FlxAiSkillDetailFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiSkillDetailFields on FlxAiSkillDetail {
  skill {
    ...FlxAiSkillFields
  }
  body
  tools
  license
  compatibility
  example
  toolNames
  files {
    path
    size
    downloadUrl
  }
  evals {
    triggers {
      query
      shouldTrigger
    }
    cases {
      id
      prompt
      expectations
    }
  }
  results {
    trigger {
      ts {
        timestamp
        label
      }
      model
      hits
      total
      rate
      cost
      partial
      rows {
        query
        shouldTrigger
        triggered
        picked
      }
    }
    functional {
      ts {
        timestamp
        label
      }
      id
      prompt
      note
      judgeCost
      with {
        ...FlxAiSkillSideFields
      }
      without {
        ...FlxAiSkillSideFields
      }
      checks {
        text
        with
        without
      }
    }
  }
}

fragment FlxAiSkillFields on FlxAiSkill {
  slug
  name
  description
  version
  status
  bundled
  source
  changed
  saved
  fileCount
  hasScripts
  tasks
  exportUrl
}

fragment FlxAiSkillSideFields on FlxAiSkillSide {
  passed
  total
  in
  out
  cost
  ms
  output
}`},Be={id:`a6a472f970cb6325218460eaec9caffcc51c157174dbcb64dd6031019f2e07b7`,name:`FlxAiSkillFile`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiSkillFile($slug: String!, $path: String!) {
  flxAiSkillFile(slug: $slug, path: $path) {
    path
    size
    text
    content
    note
  }
}`},Ve={id:`d373e520e30964f085df84a2ea0884ff1454c19bb21f4fba45a02b72ddf170c5`,name:`FlxAiSkillFileDelete`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSkillFileDelete($input: FlxAiSkillFileDeleteInput!) {
  flxAiSkillFileDelete(input: $input) {
    skill {
      ...FlxAiSkillDetailFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiSkillDetailFields on FlxAiSkillDetail {
  skill {
    ...FlxAiSkillFields
  }
  body
  tools
  license
  compatibility
  example
  toolNames
  files {
    path
    size
    downloadUrl
  }
  evals {
    triggers {
      query
      shouldTrigger
    }
    cases {
      id
      prompt
      expectations
    }
  }
  results {
    trigger {
      ts {
        timestamp
        label
      }
      model
      hits
      total
      rate
      cost
      partial
      rows {
        query
        shouldTrigger
        triggered
        picked
      }
    }
    functional {
      ts {
        timestamp
        label
      }
      id
      prompt
      note
      judgeCost
      with {
        ...FlxAiSkillSideFields
      }
      without {
        ...FlxAiSkillSideFields
      }
      checks {
        text
        with
        without
      }
    }
  }
}

fragment FlxAiSkillFields on FlxAiSkill {
  slug
  name
  description
  version
  status
  bundled
  source
  changed
  saved
  fileCount
  hasScripts
  tasks
  exportUrl
}

fragment FlxAiSkillSideFields on FlxAiSkillSide {
  passed
  total
  in
  out
  cost
  ms
  output
}`},He={id:`09c37d0e71eb7657215d6bc698bc8d2db1029394076b3d229df3c9bbd790fe20`,name:`FlxAiSkillFileSave`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSkillFileSave($input: FlxAiSkillFileSaveInput!) {
  flxAiSkillFileSave(input: $input) {
    skill {
      ...FlxAiSkillDetailFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiSkillDetailFields on FlxAiSkillDetail {
  skill {
    ...FlxAiSkillFields
  }
  body
  tools
  license
  compatibility
  example
  toolNames
  files {
    path
    size
    downloadUrl
  }
  evals {
    triggers {
      query
      shouldTrigger
    }
    cases {
      id
      prompt
      expectations
    }
  }
  results {
    trigger {
      ts {
        timestamp
        label
      }
      model
      hits
      total
      rate
      cost
      partial
      rows {
        query
        shouldTrigger
        triggered
        picked
      }
    }
    functional {
      ts {
        timestamp
        label
      }
      id
      prompt
      note
      judgeCost
      with {
        ...FlxAiSkillSideFields
      }
      without {
        ...FlxAiSkillSideFields
      }
      checks {
        text
        with
        without
      }
    }
  }
}

fragment FlxAiSkillFields on FlxAiSkill {
  slug
  name
  description
  version
  status
  bundled
  source
  changed
  saved
  fileCount
  hasScripts
  tasks
  exportUrl
}

fragment FlxAiSkillSideFields on FlxAiSkillSide {
  passed
  total
  in
  out
  cost
  ms
  output
}`},Ue={id:`ebd8574ac829b2962c165a2746255c9f5ce6cf1e021e1ff970e262bc4da2d3a7`,name:`FlxAiSkillFileUpload`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSkillFileUpload($input: FlxAiSkillFileUploadInput!) {
  flxAiSkillFileUpload(input: $input) {
    skill {
      ...FlxAiSkillDetailFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiSkillDetailFields on FlxAiSkillDetail {
  skill {
    ...FlxAiSkillFields
  }
  body
  tools
  license
  compatibility
  example
  toolNames
  files {
    path
    size
    downloadUrl
  }
  evals {
    triggers {
      query
      shouldTrigger
    }
    cases {
      id
      prompt
      expectations
    }
  }
  results {
    trigger {
      ts {
        timestamp
        label
      }
      model
      hits
      total
      rate
      cost
      partial
      rows {
        query
        shouldTrigger
        triggered
        picked
      }
    }
    functional {
      ts {
        timestamp
        label
      }
      id
      prompt
      note
      judgeCost
      with {
        ...FlxAiSkillSideFields
      }
      without {
        ...FlxAiSkillSideFields
      }
      checks {
        text
        with
        without
      }
    }
  }
}

fragment FlxAiSkillFields on FlxAiSkill {
  slug
  name
  description
  version
  status
  bundled
  source
  changed
  saved
  fileCount
  hasScripts
  tasks
  exportUrl
}

fragment FlxAiSkillSideFields on FlxAiSkillSide {
  passed
  total
  in
  out
  cost
  ms
  output
}`},We={id:`d9191efde70835ba5cfed6ceb69090c4efade488a245e71ab5ebd320914d2ad0`,name:`FlxAiSkillImport`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSkillImport($input: FlxAiSkillImportInput!) {
  flxAiSkillImport(input: $input) {
    result {
      imported
      notes
      overview {
        ...FlxAiSkillsOverviewFields
      }
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiSkillFields on FlxAiSkill {
  slug
  name
  description
  version
  status
  bundled
  source
  changed
  saved
  fileCount
  hasScripts
  tasks
  exportUrl
}

fragment FlxAiSkillsOverviewFields on FlxAiSkillsOverview {
  restrict
  skills {
    ...FlxAiSkillFields
  }
}`},Ge={id:`cf20bd001ca38dfb6cfde68b6cf3c3338b3ec8ab2f1c32c3839bb6e986b2d82f`,name:`FlxAiSkills`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiSkills {
  flxAiSkills {
    ...FlxAiSkillsOverviewFields
  }
}

fragment FlxAiSkillFields on FlxAiSkill {
  slug
  name
  description
  version
  status
  bundled
  source
  changed
  saved
  fileCount
  hasScripts
  tasks
  exportUrl
}

fragment FlxAiSkillsOverviewFields on FlxAiSkillsOverview {
  restrict
  skills {
    ...FlxAiSkillFields
  }
}`},Ke={id:`6cde51e04a2e3a47ba5121f5df5cbdc44ff904a6612f8aeabc8e9a962f4a1d90`,name:`FlxAiSkillSave`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSkillSave($input: FlxAiSkillSaveInput!) {
  flxAiSkillSave(input: $input) {
    skill {
      ...FlxAiSkillDetailFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiSkillDetailFields on FlxAiSkillDetail {
  skill {
    ...FlxAiSkillFields
  }
  body
  tools
  license
  compatibility
  example
  toolNames
  files {
    path
    size
    downloadUrl
  }
  evals {
    triggers {
      query
      shouldTrigger
    }
    cases {
      id
      prompt
      expectations
    }
  }
  results {
    trigger {
      ts {
        timestamp
        label
      }
      model
      hits
      total
      rate
      cost
      partial
      rows {
        query
        shouldTrigger
        triggered
        picked
      }
    }
    functional {
      ts {
        timestamp
        label
      }
      id
      prompt
      note
      judgeCost
      with {
        ...FlxAiSkillSideFields
      }
      without {
        ...FlxAiSkillSideFields
      }
      checks {
        text
        with
        without
      }
    }
  }
}

fragment FlxAiSkillFields on FlxAiSkill {
  slug
  name
  description
  version
  status
  bundled
  source
  changed
  saved
  fileCount
  hasScripts
  tasks
  exportUrl
}

fragment FlxAiSkillSideFields on FlxAiSkillSide {
  passed
  total
  in
  out
  cost
  ms
  output
}`},qe={id:`da67faeb795417a5c9c511c64f539a783b84b00959f301cdf4bcb51ca83159dd`,name:`FlxAiSkillsSettings`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSkillsSettings($input: FlxAiSkillsSettingsInput!) {
  flxAiSkillsSettings(input: $input) {
    overview {
      ...FlxAiSkillsOverviewFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiSkillFields on FlxAiSkill {
  slug
  name
  description
  version
  status
  bundled
  source
  changed
  saved
  fileCount
  hasScripts
  tasks
  exportUrl
}

fragment FlxAiSkillsOverviewFields on FlxAiSkillsOverview {
  restrict
  skills {
    ...FlxAiSkillFields
  }
}`},Je={id:`b1420ef58530cffd638bfb94181dc30468180e48a8d5d28faa50553961fd57d1`,name:`FlxAiSkillTestRun`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSkillTestRun($input: FlxAiSkillTestRunInput!) {
  flxAiSkillTestRun(input: $input) {
    skill {
      ...FlxAiSkillDetailFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiSkillDetailFields on FlxAiSkillDetail {
  skill {
    ...FlxAiSkillFields
  }
  body
  tools
  license
  compatibility
  example
  toolNames
  files {
    path
    size
    downloadUrl
  }
  evals {
    triggers {
      query
      shouldTrigger
    }
    cases {
      id
      prompt
      expectations
    }
  }
  results {
    trigger {
      ts {
        timestamp
        label
      }
      model
      hits
      total
      rate
      cost
      partial
      rows {
        query
        shouldTrigger
        triggered
        picked
      }
    }
    functional {
      ts {
        timestamp
        label
      }
      id
      prompt
      note
      judgeCost
      with {
        ...FlxAiSkillSideFields
      }
      without {
        ...FlxAiSkillSideFields
      }
      checks {
        text
        with
        without
      }
    }
  }
}

fragment FlxAiSkillFields on FlxAiSkill {
  slug
  name
  description
  version
  status
  bundled
  source
  changed
  saved
  fileCount
  hasScripts
  tasks
  exportUrl
}

fragment FlxAiSkillSideFields on FlxAiSkillSide {
  passed
  total
  in
  out
  cost
  ms
  output
}`},Ye={id:`fdb8bd020db940528756b520ebf649d8516754ea03b16bd1d4cf43216eb556f5`,name:`FlxAiSkillToggle`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSkillToggle($input: FlxAiSkillToggleInput!) {
  flxAiSkillToggle(input: $input) {
    skill {
      ...FlxAiSkillDetailFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiSkillDetailFields on FlxAiSkillDetail {
  skill {
    ...FlxAiSkillFields
  }
  body
  tools
  license
  compatibility
  example
  toolNames
  files {
    path
    size
    downloadUrl
  }
  evals {
    triggers {
      query
      shouldTrigger
    }
    cases {
      id
      prompt
      expectations
    }
  }
  results {
    trigger {
      ts {
        timestamp
        label
      }
      model
      hits
      total
      rate
      cost
      partial
      rows {
        query
        shouldTrigger
        triggered
        picked
      }
    }
    functional {
      ts {
        timestamp
        label
      }
      id
      prompt
      note
      judgeCost
      with {
        ...FlxAiSkillSideFields
      }
      without {
        ...FlxAiSkillSideFields
      }
      checks {
        text
        with
        without
      }
    }
  }
}

fragment FlxAiSkillFields on FlxAiSkill {
  slug
  name
  description
  version
  status
  bundled
  source
  changed
  saved
  fileCount
  hasScripts
  tasks
  exportUrl
}

fragment FlxAiSkillSideFields on FlxAiSkillSide {
  passed
  total
  in
  out
  cost
  ms
  output
}`},Xe={id:`97e294dc31a61739f94e276e07f3a784c69619c14137143d7fa6c20e6ffd6ab5`,name:`FlxAiSystems`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiSystems {
  flxAiSystems {
    ...FlxAiSystemRow
  }
}

fragment FlxAiSystemRow on FlxAiSystem {
  source
  label
  installed
  active
  on
  model
  modelLabel
  calls
  tools
  tokens
  cost
  errors
  lastUsed {
    timestamp
    label
  }
  settingsPage
  hasAiSettings
}`},Ze={id:`d4ed3c9a54a944e4f3f13663d24756babda1e6af03f49a52568a6eae6f2b4230`,name:`FlxAiSystemSettings`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiSystemSettings($source: String!) {
  flxAiSystemSettings(source: $source) {
    ...FlxAiSystemSettingsFields
  }
}

fragment FlxAiSystemSettingsFields on FlxAiSystemSettings {
  source
  label
  description
  on
  model
  defaultModel
  declared
  toolsTotal
  toolsOn
  sdkVersion
  fields {
    key
    type
    label
    help
    value
    min
    max
    options {
      id
      label
    }
  }
}`},Qe={id:`407dab4bb03fd8c1d73c33fc465338a17cbaeb37b57f8dc3caa479c173a9c284`,name:`FlxAiSystemSettingsUpdate`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSystemSettingsUpdate($input: FlxAiSystemSettingsUpdateInput!) {
  flxAiSystemSettingsUpdate(input: $input) {
    settings {
      ...FlxAiSystemSettingsFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiSystemSettingsFields on FlxAiSystemSettings {
  source
  label
  description
  on
  model
  defaultModel
  declared
  toolsTotal
  toolsOn
  sdkVersion
  fields {
    key
    type
    label
    help
    value
    min
    max
    options {
      id
      label
    }
  }
}`},$e={id:`73431ff2a62e32d7f2e91309552ed1c18343d40e544dd7ad09c09618c1c845fb`,name:`FlxAiSystemsUpdate`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSystemsUpdate($input: FlxAiSystemsUpdateInput!) {
  flxAiSystemsUpdate(input: $input) {
    systems {
      ...FlxAiSystemRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiSystemRow on FlxAiSystem {
  source
  label
  installed
  active
  on
  model
  modelLabel
  calls
  tools
  tokens
  cost
  errors
  lastUsed {
    timestamp
    label
  }
  settingsPage
  hasAiSettings
}`},et={id:`968bc851fc41b9038e26b9f469b50c6b8abc9aa6a0231c99c42f65810f2f65da`,name:`FlxAiTaskDelete`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiTaskDelete($input: FlxAiTaskDeleteInput!) {
  flxAiTaskDelete(input: $input) {
    deleted
    errors {
      code
      message
      field
    }
  }
}`},tt={id:`a716b6d24e46024257e51c1690e1e1283a388f1806a112617551e4ad58a552bb`,name:`FlxAiTaskRun`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiTaskRun($input: FlxAiTaskRunInput!) {
  flxAiTaskRun(input: $input) {
    task {
      ...FlxAiTaskFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiRecipientFields on FlxAiRecipient {
  kind
  id
  name
  email
  phone
  jid
  sub
  channels
}

fragment FlxAiTaskFields on FlxAiTask {
  id
  title
  prompt
  schedule {
    freq
    time
    days
    day
    every
    at
  }
  scheduleLabel
  status
  nextRun {
    timestamp
    label
  }
  lastRun {
    timestamp
    label
  }
  lastState
  lastError
  lastOutput
  runs
  cost
  avgMs
  chatId
  owner
  mine
  skill
  unread
  notify {
    mode
    email
    panel
    whatsapp
    promo
    label
    to {
      ...FlxAiRecipientFields
    }
  }
  workspace {
    dir
    access
    folders
  }
}`},nt={id:`430fa2417957a3261f5b5cad236830f3d186555265e97f90c244bea62694201b`,name:`FlxAiTasks`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiTasks {
  flxAiTasks {
    ...FlxAiTaskOverviewFields
  }
}

fragment FlxAiRecipientFields on FlxAiRecipient {
  kind
  id
  name
  email
  phone
  jid
  sub
  channels
}

fragment FlxAiTaskFields on FlxAiTask {
  id
  title
  prompt
  schedule {
    freq
    time
    days
    day
    every
    at
  }
  scheduleLabel
  status
  nextRun {
    timestamp
    label
  }
  lastRun {
    timestamp
    label
  }
  lastState
  lastError
  lastOutput
  runs
  cost
  avgMs
  chatId
  owner
  mine
  skill
  unread
  notify {
    mode
    email
    panel
    whatsapp
    promo
    label
    to {
      ...FlxAiRecipientFields
    }
  }
  workspace {
    dir
    access
    folders
  }
}

fragment FlxAiTaskOverviewFields on FlxAiTaskOverview {
  enabled
  nextRun {
    timestamp
    label
  }
  awaiting
  lastCheck {
    timestamp
    label
  }
  workspaceReady
  whatsappReady
  ownerEmail
  timezone
  skills {
    slug
    name
    active
  }
  channels {
    email
    sms
    whatsapp
  }
  tasks {
    ...FlxAiTaskFields
  }
}`},rt={id:`1f4f0cf7a4e19e42faa4145528018626aa1c712f6d77cb4c87781073e74ab24d`,name:`FlxAiTaskSave`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiTaskSave($input: FlxAiTaskSaveInput!) {
  flxAiTaskSave(input: $input) {
    task {
      ...FlxAiTaskFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiRecipientFields on FlxAiRecipient {
  kind
  id
  name
  email
  phone
  jid
  sub
  channels
}

fragment FlxAiTaskFields on FlxAiTask {
  id
  title
  prompt
  schedule {
    freq
    time
    days
    day
    every
    at
  }
  scheduleLabel
  status
  nextRun {
    timestamp
    label
  }
  lastRun {
    timestamp
    label
  }
  lastState
  lastError
  lastOutput
  runs
  cost
  avgMs
  chatId
  owner
  mine
  skill
  unread
  notify {
    mode
    email
    panel
    whatsapp
    promo
    label
    to {
      ...FlxAiRecipientFields
    }
  }
  workspace {
    dir
    access
    folders
  }
}`},it={id:`534e50822d3b85dbba646ca9b3fe4266802f3a320c8df8cdbc649d8abf8777be`,name:`FlxAiTaskSetActive`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiTaskSetActive($input: FlxAiTaskSetActiveInput!) {
  flxAiTaskSetActive(input: $input) {
    task {
      ...FlxAiTaskFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiRecipientFields on FlxAiRecipient {
  kind
  id
  name
  email
  phone
  jid
  sub
  channels
}

fragment FlxAiTaskFields on FlxAiTask {
  id
  title
  prompt
  schedule {
    freq
    time
    days
    day
    every
    at
  }
  scheduleLabel
  status
  nextRun {
    timestamp
    label
  }
  lastRun {
    timestamp
    label
  }
  lastState
  lastError
  lastOutput
  runs
  cost
  avgMs
  chatId
  owner
  mine
  skill
  unread
  notify {
    mode
    email
    panel
    whatsapp
    promo
    label
    to {
      ...FlxAiRecipientFields
    }
  }
  workspace {
    dir
    access
    folders
  }
}`},at={id:`5b14958f712eea70ed3c52f18f2399b72ff469ae34b95246c31fdc9b2481caad`,name:`FlxAiTasksToggle`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiTasksToggle($input: FlxAiTasksToggleInput!) {
  flxAiTasksToggle(input: $input) {
    overview {
      ...FlxAiTaskOverviewFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiRecipientFields on FlxAiRecipient {
  kind
  id
  name
  email
  phone
  jid
  sub
  channels
}

fragment FlxAiTaskFields on FlxAiTask {
  id
  title
  prompt
  schedule {
    freq
    time
    days
    day
    every
    at
  }
  scheduleLabel
  status
  nextRun {
    timestamp
    label
  }
  lastRun {
    timestamp
    label
  }
  lastState
  lastError
  lastOutput
  runs
  cost
  avgMs
  chatId
  owner
  mine
  skill
  unread
  notify {
    mode
    email
    panel
    whatsapp
    promo
    label
    to {
      ...FlxAiRecipientFields
    }
  }
  workspace {
    dir
    access
    folders
  }
}

fragment FlxAiTaskOverviewFields on FlxAiTaskOverview {
  enabled
  nextRun {
    timestamp
    label
  }
  awaiting
  lastCheck {
    timestamp
    label
  }
  workspaceReady
  whatsappReady
  ownerEmail
  timezone
  skills {
    slug
    name
    active
  }
  channels {
    email
    sms
    whatsapp
  }
  tasks {
    ...FlxAiTaskFields
  }
}`},ot={id:`89cc3100bddc0b80ca45c79713741b1e5c52fbff15fcc12aa33aa648bfa6b3cd`,name:`FlxAiToolPick`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiToolPick($input: FlxAiToolPickInput!) {
  flxAiToolPick(input: $input) {
    tools {
      ...FlxAiToolRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiToolRow on FlxAiTool {
  name
  title
  description
  source
  sourceLabel
  readonly
  approval
  capability
  mcp
  chat
  off
  aliases
  duplicate
  rivals
  prefixed
  calls
  errors
  avgMs
}`},st={id:`2ac9ecc70a8c53e97b50f2b9b4761536f293a681d6e3e0c4fe65ae7b83a2cacb`,name:`FlxAiTools`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiTools {
  flxAiTools {
    ...FlxAiToolRow
  }
}

fragment FlxAiToolRow on FlxAiTool {
  name
  title
  description
  source
  sourceLabel
  readonly
  approval
  capability
  mcp
  chat
  off
  aliases
  duplicate
  rivals
  prefixed
  calls
  errors
  avgMs
}`},ct={id:`fa6d026ab04301f37b80092e734c35cea8adfdf785d61057e8a0d53f71e4fdae`,name:`FlxAiToolsUpdate`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiToolsUpdate($input: FlxAiToolsUpdateInput!) {
  flxAiToolsUpdate(input: $input) {
    tools {
      ...FlxAiToolRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiToolRow on FlxAiTool {
  name
  title
  description
  source
  sourceLabel
  readonly
  approval
  capability
  mcp
  chat
  off
  aliases
  duplicate
  rivals
  prefixed
  calls
  errors
  avgMs
}`},lt={id:`38c66e1db90a0c7f811614cb50f0f82d679a21ff93ebe0cbf3b021b9e5a9f7af`,name:`FlxAnalyticsAggregate`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsAggregate($input: FlxAnalyticsAggregateInput!) {
  flxAnalyticsAggregate(input: $input) {
    processed
    settings {
      ...FlxAnalyticsSettingsFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAnalyticsSettingsFields on FlxAnalyticsSettings {
  on
  method
  methodNow
  dnt
  excludeRoles
  excludeIps
  evOutbound
  evDownload
  evContact
  evSearch
  evDonation
  postsColumn
  adminbar
  keepMonths
  tmHub
  hubEndpoint
  lastAggregated {
    iso
    label
    relative
  }
  pendingBytes
  pendingLabel
  fastPossible
  fastTest
  roles {
    value
    label
  }
  funnels {
    id
    name
    steps {
      label
      type
      value
    }
  }
  funnelDefaults {
    id
    name
    steps {
      label
      type
      value
    }
  }
  funnelEvents {
    value
    label
  }
  funnelPages {
    path
    title
  }
  koko {
    days
    first
    last
  }
  exportUrl
}`},ut={id:`6b342c00c7aeb5b90485e9ccd04debf159a54692615072abccfe121ae406f79f`,name:`FlxAnalyticsBreakdown`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsBreakdown($filter: FlxAnalyticsFilterInput, $kind: FlxAnalyticsDimKind!, $first: Int, $after: String) {
  flxAnalyticsBreakdown(
    filter: $filter
    kind: $kind
    first: $first
    after: $after
  ) {
    totalCount
    pageInfo {
      hasNextPage
      endCursor
    }
    nodes {
      ...FlxAnalyticsDimRowFields
    }
  }
}

fragment FlxAnalyticsDimRowFields on FlxAnalyticsDimRow {
  value
  label
  visitors
  pageviews
}`},dt={id:`2023f590bac125700fa5bcb6ddf42b8117fe52804f68d372a0a34d4868d6832b`,name:`FlxAnalyticsConfig`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsConfig {
  flxAnalyticsConfig {
    presets {
      value
      label
    }
    kinds {
      value
      label
    }
    events {
      value
      label
    }
    channels {
      value
      label
    }
    segments {
      value
      label
    }
    funnelBy {
      value
      label
    }
    goals {
      value
      label
    }
    defaultGoal
    canManageRefs
    canManage
    ignoreBrowser
    fastEndpoint
    exportUrl
    homeUrl
    settingsUrl
    funnelSettingsUrl
    hasWorldMap
  }
}`},ft={id:`5576bd9bb02666b281e1c009983a7c270480a77cf90231fd8ba61016a7056037`,name:`FlxAnalyticsEvents`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsEvents($filter: FlxAnalyticsFilterInput, $name: String, $first: Int, $after: String) {
  flxAnalyticsEvents(filter: $filter, name: $name, first: $first, after: $after) {
    totalCount
    pageInfo {
      hasNextPage
      endCursor
    }
    nodes {
      ...FlxAnalyticsEventRowFields
    }
  }
}

fragment FlxAnalyticsEventRowFields on FlxAnalyticsEventRow {
  name
  label
  param
  hits
  uniques
  amount
}`},pt={id:`79d1dccd00bee3f2f3c4de1ed9fa6ff89fd2056b81518e5f7e92466bdbf3a388`,name:`FlxAnalyticsFastTest`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsFastTest($input: FlxAnalyticsFastTestInput!) {
  flxAnalyticsFastTest(input: $input) {
    method
    errors {
      code
      message
      field
    }
  }
}`},mt={id:`ea6bd1bf4b9cbda61bd0c6e6053256c45c01af2682b85d64981868aa0b5d82f8`,name:`FlxAnalyticsFunnel`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsFunnel($filter: FlxAnalyticsFilterInput, $id: Int, $by: FlxAnalyticsFunnelBy) {
  flxAnalyticsFunnel(filter: $filter, id: $id, by: $by) {
    ...FlxAnalyticsFunnelFields
  }
}

fragment FlxAnalyticsFunnelFields on FlxAnalyticsFunnel {
  found
  id
  name
  by
  steps {
    label
    type
    value
    count
    rate
    drop
  }
  groups {
    value
    label
    counts
    rate
  }
  funnels {
    id
    name
  }
}`},ht={id:`712f94387c03d252e5e19ad5cc11d4f20cc35fa4b778aba666cae6a27dff36a5`,name:`FlxAnalyticsJourneys`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsJourneys($filter: FlxAnalyticsFilterInput, $options: FlxAnalyticsJourneysInput) {
  flxAnalyticsJourneys(filter: $filter, options: $options) {
    range {
      ...FlxAnalyticsRangeFields
    }
    options {
      steps
      from
      to
      goal
      group
      top
    }
    goals {
      value
      label
    }
    summary {
      sessions
      multi
      multiPct
      multiChange
      avgLen
      avgLenChange
      topEntry {
        key
        label
        count
        pct
      }
      topExit {
        key
        label
        count
        pct
      }
      goalRate
      goalReached
      goalChange
    }
    flow {
      columns {
        nodes {
          key
          label
          path
          ev
          count
          ends
        }
      }
      links {
        step
        from
        to
        count
      }
    }
    paths {
      top
      total
      items {
        sessions
        reached
        share
        prev
        change
        rate
        steps {
          key
          label
          path
          ev
        }
      }
    }
  }
}

fragment FlxAnalyticsRangeFields on FlxAnalyticsRange {
  preset
  start
  end
  label
  days
  prevStart
  prevEnd
}`},gt={id:`e7a13b823796a603132470f9f248230c22cedfd4d2391e05e1a1a6626ecbd35a`,name:`FlxAnalyticsKokoImport`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsKokoImport($input: FlxAnalyticsKokoImportInput!) {
  flxAnalyticsKokoImport(input: $input) {
    rows
    settings {
      ...FlxAnalyticsSettingsFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAnalyticsSettingsFields on FlxAnalyticsSettings {
  on
  method
  methodNow
  dnt
  excludeRoles
  excludeIps
  evOutbound
  evDownload
  evContact
  evSearch
  evDonation
  postsColumn
  adminbar
  keepMonths
  tmHub
  hubEndpoint
  lastAggregated {
    iso
    label
    relative
  }
  pendingBytes
  pendingLabel
  fastPossible
  fastTest
  roles {
    value
    label
  }
  funnels {
    id
    name
    steps {
      label
      type
      value
    }
  }
  funnelDefaults {
    id
    name
    steps {
      label
      type
      value
    }
  }
  funnelEvents {
    value
    label
  }
  funnelPages {
    path
    title
  }
  koko {
    days
    first
    last
  }
  exportUrl
}`},_t={id:`8d1c4f7574619f7eb39e4b908cdd7ff3e11a3b13bc255f0e2b3f8efd83489839`,name:`FlxAnalyticsPage`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsPage($filter: FlxAnalyticsFilterInput, $target: String!) {
  flxAnalyticsPage(filter: $filter, target: $target) {
    found
    paths
    visitors
    pageviews
    series {
      date
      visitors
      pageviews
    }
  }
}`},vt={id:`ebbac7ddcb08b32fb8b2c0eb5b67c80284fa09be3165b30eebeae0a282cee423`,name:`FlxAnalyticsPages`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsPages($filter: FlxAnalyticsFilterInput, $q: String, $only404: Boolean, $first: Int, $after: String) {
  flxAnalyticsPages(
    filter: $filter
    q: $q
    only404: $only404
    first: $first
    after: $after
  ) {
    totalCount
    pageInfo {
      hasNextPage
      endCursor
    }
    nodes {
      ...FlxAnalyticsPageRowFields
    }
  }
}

fragment FlxAnalyticsPageRowFields on FlxAnalyticsPageRow {
  path
  url
  postId
  title
  is404
  visitors
  pageviews
}`},yt={id:`db042cdd9f459d72081431f7df6d8a9e1081c5230eef59de2e263c30dcb9d698`,name:`FlxAnalyticsRealtime`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsRealtime($filter: FlxAnalyticsFilterInput, $minutes: Int, $kpi: Boolean) {
  flxAnalyticsRealtime(filter: $filter, minutes: $minutes, kpi: $kpi) {
    ...FlxAnalyticsRealtimeFields
    kpi {
      ...FlxAnalyticsKpiFields
    }
  }
}

fragment FlxAnalyticsKpiFields on FlxAnalyticsKpi {
  totals {
    visitors
    pageviews
  }
  prev {
    visitors
    pageviews
  }
  visits {
    sessions
    avgDuration
    bounceRate
    pagesPer
  }
  visitsPrev {
    sessions
    avgDuration
    bounceRate
    pagesPer
  }
  change {
    visitors
    pageviews
    sessions
    avgDuration
    bounceRate
    pagesPer
  }
  series {
    group
    items {
      date
      visitors
      pageviews
    }
  }
}

fragment FlxAnalyticsRealtimeFields on FlxAnalyticsRealtime {
  minutes
  active
  visitors
  pageviews
  perMinute
  updated {
    iso
    label
    relative
  }
  pages {
    path
    title
    pageviews
  }
  referrers {
    host
    pageviews
  }
  countries {
    value
    label
    visitors
  }
  codes {
    code
    label
    visitors
  }
}`},bt={id:`3c8464c3ca4e71ec015a9a16728f8a3696bdbd22a4e4eecc19312c893c3d4db1`,name:`FlxAnalyticsReferrers`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsReferrers($filter: FlxAnalyticsFilterInput, $q: String, $first: Int, $after: String) {
  flxAnalyticsReferrers(filter: $filter, q: $q, first: $first, after: $after) {
    totalCount
    pageInfo {
      hasNextPage
      endCursor
    }
    nodes {
      ...FlxAnalyticsReferrerRowFields
    }
  }
}

fragment FlxAnalyticsReferrerRowFields on FlxAnalyticsReferrerRow {
  host
  channel
  channelLabel
  visitors
  pageviews
}`},xt={id:`59ee013d5260056c879e17dc79e67240198450d0cb156e6425159922a2d37744`,name:`FlxAnalyticsRefSave`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsRefSave($input: FlxAnalyticsRefSaveInput!) {
  flxAnalyticsRefSave(input: $input) {
    ref {
      code
      name
      url
    }
    errors {
      code
      message
      field
    }
  }
}`},St={id:`8379b10f9853966238a2d54a9b2b4dd22175b692573038145ee03bf5b3501f45`,name:`FlxAnalyticsReport`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsReport($filter: FlxAnalyticsFilterInput) {
  flxAnalyticsReport(filter: $filter) {
    range {
      ...FlxAnalyticsRangeFields
    }
    filter {
      seg
      rc
    }
    kpi {
      ...FlxAnalyticsKpiFields
    }
    pages {
      total
      items {
        ...FlxAnalyticsPageRowFields
      }
    }
    notFound {
      total
      items {
        ...FlxAnalyticsPageRowFields
      }
    }
    referrers {
      total
      items {
        ...FlxAnalyticsReferrerRowFields
      }
    }
    dims {
      kind
      total
      items {
        ...FlxAnalyticsDimRowFields
      }
    }
    segments {
      value
      label
      visitors
      pageviews
    }
    refs {
      ...FlxAnalyticsRefFields
    }
    funnel {
      ...FlxAnalyticsFunnelFields
    }
    eventSummary {
      name
      label
      hits
      uniques
      amount
    }
    events {
      total
      items {
        ...FlxAnalyticsEventRowFields
      }
    }
    realtime {
      ...FlxAnalyticsRealtimeFields
    }
  }
}

fragment FlxAnalyticsDimRowFields on FlxAnalyticsDimRow {
  value
  label
  visitors
  pageviews
}

fragment FlxAnalyticsEventRowFields on FlxAnalyticsEventRow {
  name
  label
  param
  hits
  uniques
  amount
}

fragment FlxAnalyticsFunnelFields on FlxAnalyticsFunnel {
  found
  id
  name
  by
  steps {
    label
    type
    value
    count
    rate
    drop
  }
  groups {
    value
    label
    counts
    rate
  }
  funnels {
    id
    name
  }
}

fragment FlxAnalyticsKpiFields on FlxAnalyticsKpi {
  totals {
    visitors
    pageviews
  }
  prev {
    visitors
    pageviews
  }
  visits {
    sessions
    avgDuration
    bounceRate
    pagesPer
  }
  visitsPrev {
    sessions
    avgDuration
    bounceRate
    pagesPer
  }
  change {
    visitors
    pageviews
    sessions
    avgDuration
    bounceRate
    pagesPer
  }
  series {
    group
    items {
      date
      visitors
      pageviews
    }
  }
}

fragment FlxAnalyticsPageRowFields on FlxAnalyticsPageRow {
  path
  url
  postId
  title
  is404
  visitors
  pageviews
}

fragment FlxAnalyticsRangeFields on FlxAnalyticsRange {
  preset
  start
  end
  label
  days
  prevStart
  prevEnd
}

fragment FlxAnalyticsRealtimeFields on FlxAnalyticsRealtime {
  minutes
  active
  visitors
  pageviews
  perMinute
  updated {
    iso
    label
    relative
  }
  pages {
    path
    title
    pageviews
  }
  referrers {
    host
    pageviews
  }
  countries {
    value
    label
    visitors
  }
  codes {
    code
    label
    visitors
  }
}

fragment FlxAnalyticsReferrerRowFields on FlxAnalyticsReferrerRow {
  host
  channel
  channelLabel
  visitors
  pageviews
}

fragment FlxAnalyticsRefFields on FlxAnalyticsRef {
  code
  name
  label
  url
  visitors
  pageviews
  sessions
  pagesPer
  avgDuration
  donations
  amount
  deepest
  deepestLabel
  completed
}`},Ct={id:`ce33713a41cfcf183974cb43003436209c4bedc460a35228a0fd27982819f466`,name:`FlxAnalyticsReset`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsReset($input: FlxAnalyticsResetInput!) {
  flxAnalyticsReset(input: $input) {
    settings {
      ...FlxAnalyticsSettingsFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAnalyticsSettingsFields on FlxAnalyticsSettings {
  on
  method
  methodNow
  dnt
  excludeRoles
  excludeIps
  evOutbound
  evDownload
  evContact
  evSearch
  evDonation
  postsColumn
  adminbar
  keepMonths
  tmHub
  hubEndpoint
  lastAggregated {
    iso
    label
    relative
  }
  pendingBytes
  pendingLabel
  fastPossible
  fastTest
  roles {
    value
    label
  }
  funnels {
    id
    name
    steps {
      label
      type
      value
    }
  }
  funnelDefaults {
    id
    name
    steps {
      label
      type
      value
    }
  }
  funnelEvents {
    value
    label
  }
  funnelPages {
    path
    title
  }
  koko {
    days
    first
    last
  }
  exportUrl
}`},wt={id:`129cfba2b194904d644f1f6c1a89065b2c3534dd0982d6aa88d30aa855920c9f`,name:`FlxAnalyticsSettings`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsSettings {
  flxAnalyticsSettings {
    ...FlxAnalyticsSettingsFields
  }
}

fragment FlxAnalyticsSettingsFields on FlxAnalyticsSettings {
  on
  method
  methodNow
  dnt
  excludeRoles
  excludeIps
  evOutbound
  evDownload
  evContact
  evSearch
  evDonation
  postsColumn
  adminbar
  keepMonths
  tmHub
  hubEndpoint
  lastAggregated {
    iso
    label
    relative
  }
  pendingBytes
  pendingLabel
  fastPossible
  fastTest
  roles {
    value
    label
  }
  funnels {
    id
    name
    steps {
      label
      type
      value
    }
  }
  funnelDefaults {
    id
    name
    steps {
      label
      type
      value
    }
  }
  funnelEvents {
    value
    label
  }
  funnelPages {
    path
    title
  }
  koko {
    days
    first
    last
  }
  exportUrl
}`},Tt={id:`fcf4e0148b6d01ec7f05e578011d35764659fdef2afb0d3cd5438e14707c4c8f`,name:`FlxAnalyticsSettingsSave`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsSettingsSave($input: FlxAnalyticsSettingsSaveInput!) {
  flxAnalyticsSettingsSave(input: $input) {
    settings {
      ...FlxAnalyticsSettingsFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAnalyticsSettingsFields on FlxAnalyticsSettings {
  on
  method
  methodNow
  dnt
  excludeRoles
  excludeIps
  evOutbound
  evDownload
  evContact
  evSearch
  evDonation
  postsColumn
  adminbar
  keepMonths
  tmHub
  hubEndpoint
  lastAggregated {
    iso
    label
    relative
  }
  pendingBytes
  pendingLabel
  fastPossible
  fastTest
  roles {
    value
    label
  }
  funnels {
    id
    name
    steps {
      label
      type
      value
    }
  }
  funnelDefaults {
    id
    name
    steps {
      label
      type
      value
    }
  }
  funnelEvents {
    value
    label
  }
  funnelPages {
    path
    title
  }
  koko {
    days
    first
    last
  }
  exportUrl
}`},Et={id:`eb5453fc995071cd78ac870527d7da1090a3441f298f6113f2af7cfe9fde45f4`,name:`FlxAnalyticsTelemetry`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsTelemetry($days: Int, $country: String) {
  flxAnalyticsTelemetry(days: $days, country: $country) {
    enabled
    endpoint
    days
    country
    countries {
      code
      name
      sites
    }
    overview {
      installs
      reports
      errorsOpen
      fatalOpen
      events
      appUsage {
        day
        platform
        accounts
      }
      usage {
        slug
        name
        sites
        topVersion
      }
      pairs {
        aName
        bName
        sites
      }
      combos {
        names
        sites
      }
      php {
        key
        label
        sites
      }
      wp {
        key
        label
        sites
      }
      locales {
        key
        label
        sites
      }
      countries {
        key
        label
        sites
      }
    }
    onboarding {
      runs
      done
      skipped
      left
      medianSeconds
      reach {
        step
        label
        count
      }
      stops {
        step
        label
        outcome
        count
      }
      cards {
        slug
        name
        picked
        dropped
        recOff
      }
      presets {
        key
        count
      }
      styles {
        key
        count
      }
    }
  }
}`},Dt={id:`98c319fa61824c0c4714f4d86466d15123634d74291edb45f9001c7d7b6b7fbb`,name:`FlxAnalyticsTelemetryErrors`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsTelemetryErrors($days: Int, $country: String, $plugin: String, $type: String) {
  flxAnalyticsTelemetryErrors(
    days: $days
    country: $country
    plugin: $plugin
    type: $type
  ) {
    plugins {
      value
      label
    }
    items {
      hash
      type
      fatal
      message
      more
      file
      line
      plugin
      pluginName
      version
      sites
      count
      last {
        iso
        label
        relative
      }
    }
  }
}`},Ot={id:`d64ae79eb5491a540154bd58029d69fc0b5e212e8c46414279d4ca0eaa940e1d`,name:`FlxAnalyticsTelemetryForget`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsTelemetryForget($input: FlxAnalyticsTelemetryForgetInput!) {
  flxAnalyticsTelemetryForget(input: $input) {
    removed
    errors {
      code
      message
      field
    }
  }
}`},kt={id:`1dd252522e2e7e352cbe31ebb7ef763661f7f52d407b0023f418e1365e0d5aa8`,name:`FlxAppAccess`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAccess($all: Boolean) {
  flxAppAccess(all: $all) {
    devices {
      id
      name
      platform
      app
      brand
      push
      lastSeen
      lastSeenAgo
      userId
      user
      current
    }
    rows {
      id
      kind
      name
      hint
      userId
      user
      created
      lastUsed
      lastIp
      expires
    }
    canCreateKeys
    canManage
    downloads {
      macos
      windows
      windowsMsi
    }
  }
}`},At={id:`0410fe39ca78d5cd046a033c326dd6bd357096aaab36212f8c0f44f9056de2c1`,name:`FlxAppAccessRevoke`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAccessRevoke($input: FlxAppAccessRevokeInput!) {
  flxAppAccessRevoke(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},jt={id:`1860c58a8fdc3e9a90238fafaef5f1ecdc4bcf298ce323ef674dd35eefbe2e6c`,name:`FlxAppAdminAppearance`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminAppearance {
  flxAppAdminAppearance {
    ...FlxAppAdminAppearanceFields
  }
}

fragment FlxAppAdminAppearanceFields on FlxAppAdminAppearance {
  option
  theme
  fonts
  colorKeys {
    key
    label
  }
  targets {
    label
    items {
      key
      label
    }
  }
  targetLabels {
    key
    label
  }
  screens {
    key
    label
    on
  }
  previewMobile
  previewDesktop
}`},Mt={id:`762de9d5115d73d8f01452c7b5f8a8ac15213df2102f3986d735018483a7670b`,name:`FlxAppAdminAppearanceSave`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminAppearanceSave($input: FlxAppAdminAppearanceSaveInput!) {
  flxAppAdminAppearanceSave(input: $input) {
    appearance {
      ...FlxAppAdminAppearanceFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAppAdminAppearanceFields on FlxAppAdminAppearance {
  option
  theme
  fonts
  colorKeys {
    key
    label
  }
  targets {
    label
    items {
      key
      label
    }
  }
  targetLabels {
    key
    label
  }
  screens {
    key
    label
    on
  }
  previewMobile
  previewDesktop
}`},Nt={id:`14ba5b9cfa34c05e5bef9a80d5087b56019d5b4c4a5a22ce7810d12dd6089f8c`,name:`FlxAppAdminClientCreate`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminClientCreate($input: FlxAppAdminClientCreateInput!) {
  flxAppAdminClientCreate(input: $input) {
    client {
      ...FlxAppAdminClientFields
    }
    secret
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAppAdminClientFields on FlxAppAdminClient {
  id
  name
  redirectUris
  kind
  hasSecret
}`},Pt={id:`0acad378aa0aa39d0d878475c0e4c2c5e8a5a09045e3bd946641c722c6b0f44b`,name:`FlxAppAdminClientDelete`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminClientDelete($input: FlxAppAdminClientDeleteInput!) {
  flxAppAdminClientDelete(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},Ft={id:`0aa8eaf58f40a410644147b5f052aa2441f94a7c247af742f281bd5590f5b61f`,name:`FlxAppAdminClients`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminClients {
  flxAppAdminClients {
    clients {
      ...FlxAppAdminClientFields
    }
    connection {
      key
      label
    }
  }
}

fragment FlxAppAdminClientFields on FlxAppAdminClient {
  id
  name
  redirectUris
  kind
  hasSecret
}`},It={id:`67b0edec2f4ef8fb57c5f796df9e32ec167f35dae7bec4196df4edcdff650a82`,name:`FlxAppAdminMenu`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminMenu {
  flxAppAdminMenu {
    available
    editorUrl
    platforms {
      key
      label
    }
    rows {
      role
      menu
      platforms {
        key
        visible
      }
    }
  }
}`},Lt={id:`e27de38feec02d07f97aa0a6cee1f3782bf39e4b41f0fd3cf84edb52d04a7d74`,name:`FlxAppAdminMobile`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminMobile {
  flxAppAdminMobile {
    ...FlxAppAdminMobileFields
  }
}

fragment FlxAppAdminMobileFields on FlxAppAdminMobile {
  genericApp
  storeIos
  storeAndroid
  pushEnabled
  pushHide
  hasExpoToken
  embedFront
  devRedirects
  apps {
    id
    name
    platform
    redirectUris
  }
  deviceCount
  pushCount
  lastPush {
    at
    ago
    sent
    failed
    error
  }
  bootstrapUrl
  preset
}`},Rt={id:`3140882c7e64b03a33a9bdeaab1dc3b5eb7c50ba5709a2a95778672679795ae2`,name:`FlxAppAdminMobileSave`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminMobileSave($input: FlxAppAdminMobileSaveInput!) {
  flxAppAdminMobileSave(input: $input) {
    mobile {
      ...FlxAppAdminMobileFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAppAdminMobileFields on FlxAppAdminMobile {
  genericApp
  storeIos
  storeAndroid
  pushEnabled
  pushHide
  hasExpoToken
  embedFront
  devRedirects
  apps {
    id
    name
    platform
    redirectUris
  }
  deviceCount
  pushCount
  lastPush {
    at
    ago
    sent
    failed
    error
  }
  bootstrapUrl
  preset
}`},zt={id:`23953eab015437dc0f727a62ee3d73cb1e6cb45be22fc7f6b79e101318571d10`,name:`FlxAppAdminModules`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminModules {
  flxAppAdminModules {
    ...FlxAppAdminModuleFields
  }
}

fragment FlxAppAdminModuleFields on FlxAppAdminModule {
  id
  title
  menu
  cap
  version
  kind
  screens
  errors
  on
}`},Bt={id:`a7aad76ad18dbda2567f29ad4d8b488efe4043659ae782a8d0f92eefba54349c`,name:`FlxAppAdminModulesSave`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminModulesSave($input: FlxAppAdminModulesSaveInput!) {
  flxAppAdminModulesSave(input: $input) {
    modules {
      ...FlxAppAdminModuleFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAppAdminModuleFields on FlxAppAdminModule {
  id
  title
  menu
  cap
  version
  kind
  screens
  errors
  on
}`},Vt={id:`b9dddc6a4c12070645fd30a6fbdf39863d2b58ea1cbe71f7ebb5ee2db6bc9b3a`,name:`FlxAppAdminPushTest`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminPushTest {
  flxAppAdminPushTest(input: {  }) {
    sent
    failed
    skipped
    errors {
      code
      message
      field
    }
  }
}`},Ht={id:`b7d4bf6833511227a38948aef18cf38f05a29043d4dbfc3b04e49fcc1878ec06`,name:`FlxAppAdminSettings`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminSettings {
  flxAppAdminSettings {
    ...FlxAppAdminSettingsFields
  }
}

fragment FlxAppAdminSettingsFields on FlxAppAdminSettings {
  accessMinutes
  refreshDays
  dynamicClients
  webSession
  otpEnabled
  otpCreate
  otpStaff
  phoneSelfVerify
  keysCap
  keysCapChoices {
    key
    label
  }
  version
}`},Ut={id:`fc53aca192620408e9133dd653741e741a10c7d19ada18e38845f088b88e1788`,name:`FlxAppAdminSettingsSave`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminSettingsSave($input: FlxAppAdminSettingsSaveInput!) {
  flxAppAdminSettingsSave(input: $input) {
    settings {
      ...FlxAppAdminSettingsFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAppAdminSettingsFields on FlxAppAdminSettings {
  accessMinutes
  refreshDays
  dynamicClients
  webSession
  otpEnabled
  otpCreate
  otpStaff
  phoneSelfVerify
  keysCap
  keysCapChoices {
    key
    label
  }
  version
}`},Wt={id:`9f61478d3bc50857048da2499c97ed5de0bdc51362012d2153e2363175cb7d37`,name:`FlxAppDeviceRevoke`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppDeviceRevoke($input: FlxAppDeviceRevokeInput!) {
  flxAppDeviceRevoke(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},Gt={id:`49577b1d3b994160a8f4f89898086f3ffb70db6242a809e48b3515c7268d6e21`,name:`FlxAppKeyCreate`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppKeyCreate($input: FlxAppKeyCreateInput!) {
  flxAppKeyCreate(input: $input) {
    key
    errors {
      code
      message
      field
    }
  }
}`},Kt={id:`8488b51d8ac2b33ecbd21791e9f59b8915e6afc07bd6bb903a5c002010852316`,name:`FlxBackupCancel`,kind:`mutation`,plugin:`flx-backup`,document:`mutation FlxBackupCancel {
  flxBackupCancel(input: {  }) {
    pending
    job {
      ...FlxBackupJobFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxBackupJobFields on FlxBackupJob {
  status
  kind
  id
  phase
  percent
  text
  error
  token
  cancelled
  elapsed
  elapsedLabel
  eta
  etaLabel
  title
  sizeLabel
  result
}`},qt={id:`5e0c2cbc788253a2687dbd388201e2a17fd45703bb4e80d90ee27db885b7a7dd`,name:`FlxBackupDashboard`,kind:`query`,plugin:`flx-backup`,document:`query FlxBackupDashboard {
  flxBackupOverview {
    usedBytes
    usedLabel
    quotaBytes
    quotaLabel
    percent
    level
    freeLabel
    backupCount
    lockedCount
    last {
      id
      title
      created {
        timestamp
        label
        relative
      }
    }
    nextRun {
      timestamp
      label
    }
    scheduleKind
    preScope
    byType {
      type
      label
      color
      bytes
      bytesLabel
      count
      share
    }
    recent {
      id
      type
      typeLabel
      typeColor
      status
      title
      sizeLabel
      error
      created {
        timestamp
        label
      }
    }
    events {
      level
      message
      at {
        timestamp
        label
      }
    }
  }
  flxBackupHistory {
    days {
      key
      label
      fullLabel
      values
      valueLabels
      totalLabel
      measured
    }
    series {
      key
      name
      color
    }
    ranges {
      days
      max
      showQuota
      ticks {
        value
        label
      }
    }
    quota
    quotaLabel
  }
  flxBackupHealth {
    ...FlxBackupHealthFields
  }
  flxBackupSettings {
    ...FlxBackupSettingsFields
  }
}

fragment FlxBackupHealthFields on FlxBackupHealth {
  zipAvailable
  writable
  folder
  chunkSize
  nonce
  ajaxUrl
  lastRestore
  includeCoreDefault
  safetyDefault
}

fragment FlxBackupSettingsFields on FlxBackupSettings {
  quotaGb
  preScope
  preKeepDays
  schedule
  scheduleHour
  scheduleDay
  scheduleKeep
  includeCore
  exclude
  safetyRestore
  deleteOnUninstall
}`},Jt={id:`762db37c8115f91fa0708dd983e1d5a6497ff9e471a1d48d4736e39b966030c8`,name:`FlxBackupDelete`,kind:`mutation`,plugin:`flx-backup`,document:`mutation FlxBackupDelete($input: FlxBackupDeleteInput!) {
  flxBackupDelete(input: $input) {
    deletedIds
    counts {
      ...FlxBackupCountsFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxBackupCountsFields on FlxBackupCounts {
  all
  usedBytes
  usedLabel
  types {
    type
    label
    count
  }
}`},Yt={id:`7efa2a37904f7bc808c0c94dd88aad63935b3f1b89f6d6f859769c0ffbfedfe8`,name:`FlxBackupDetail`,kind:`query`,plugin:`flx-backup`,document:`query FlxBackupDetail($id: ID!) {
  flxBackup(id: $id) {
    ...FlxBackupRow
    wpVersion
    originalCreated {
      timestamp
      label
    }
    finished {
      timestamp
      label
    }
    keepUntil {
      timestamp
      label
    }
    breakdown {
      raw
      zip
      categories {
        key
        label
        color
        isDatabase
        raw
        zip
        count
        items {
          name
          label
          raw
          zip
          count
        }
        moreCount
        moreRaw
        moreZip
        moreItems
      }
    }
  }
}

fragment FlxBackupRow on FlxBackup {
  id
  type
  typeLabel
  typeColor
  status
  statusLabel
  title
  label
  created {
    iso
    timestamp
    label
    relative
  }
  size
  sizeLabel
  files
  includesDatabase
  contents
  source
  sourceLabel
  originalSite
  locked
  keepKind
  keepLabel
  error
  packages {
    slug
    name
    fromVersion
    toVersion
    label
  }
  downloadUrl
  percent
  progress
  running
  restore {
    id
    title
    database
    foreignSite
    prefixInBackup
    prefixOnSite
    groups {
      key
      label
      items {
        path
        label
      }
    }
  }
}`},Xt={id:`3441ccc32a645f6092c405c3bea324de1e67815de578be76bebfeb991c2c0567`,name:`FlxBackupJobRead`,kind:`query`,plugin:`flx-backup`,document:`query FlxBackupJobRead {
  flxBackupJob {
    ...FlxBackupJobFields
  }
}

fragment FlxBackupJobFields on FlxBackupJob {
  status
  kind
  id
  phase
  percent
  text
  error
  token
  cancelled
  elapsed
  elapsedLabel
  eta
  etaLabel
  title
  sizeLabel
  result
}`},Zt={id:`444fe54b9b0e5cdeba4d419732fc6004b26926ea3e5ae242b820ccafc8b0d12c`,name:`FlxBackupList`,kind:`query`,plugin:`flx-backup`,document:`query FlxBackupList($first: Int = 20, $after: String, $type: FlxBackupType, $status: FlxBackupStatus) {
  flxBackups(first: $first, after: $after, type: $type, status: $status) {
    nodes {
      ...FlxBackupRow
    }
    pageInfo {
      hasNextPage
      endCursor
    }
    totalCount
  }
  flxBackupCounts {
    ...FlxBackupCountsFields
  }
  flxBackupHealth {
    ...FlxBackupHealthFields
  }
}

fragment FlxBackupCountsFields on FlxBackupCounts {
  all
  usedBytes
  usedLabel
  types {
    type
    label
    count
  }
}

fragment FlxBackupHealthFields on FlxBackupHealth {
  zipAvailable
  writable
  folder
  chunkSize
  nonce
  ajaxUrl
  lastRestore
  includeCoreDefault
  safetyDefault
}

fragment FlxBackupRow on FlxBackup {
  id
  type
  typeLabel
  typeColor
  status
  statusLabel
  title
  label
  created {
    iso
    timestamp
    label
    relative
  }
  size
  sizeLabel
  files
  includesDatabase
  contents
  source
  sourceLabel
  originalSite
  locked
  keepKind
  keepLabel
  error
  packages {
    slug
    name
    fromVersion
    toVersion
    label
  }
  downloadUrl
  percent
  progress
  running
  restore {
    id
    title
    database
    foreignSite
    prefixInBackup
    prefixOnSite
    groups {
      key
      label
      items {
        path
        label
      }
    }
  }
}`},Qt={id:`48e64891298acc8e11a7b7988f76d522414a779aa2ded6cac1b81642c7bd6d87`,name:`FlxBackupLock`,kind:`mutation`,plugin:`flx-backup`,document:`mutation FlxBackupLock($input: FlxBackupLockInput!) {
  flxBackupLock(input: $input) {
    backup {
      ...FlxBackupRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxBackupRow on FlxBackup {
  id
  type
  typeLabel
  typeColor
  status
  statusLabel
  title
  label
  created {
    iso
    timestamp
    label
    relative
  }
  size
  sizeLabel
  files
  includesDatabase
  contents
  source
  sourceLabel
  originalSite
  locked
  keepKind
  keepLabel
  error
  packages {
    slug
    name
    fromVersion
    toVersion
    label
  }
  downloadUrl
  percent
  progress
  running
  restore {
    id
    title
    database
    foreignSite
    prefixInBackup
    prefixOnSite
    groups {
      key
      label
      items {
        path
        label
      }
    }
  }
}`},$t={id:`1f63d67d4775c30a38621420617d5fe48b180e7fcb37ed2548169f8185236331`,name:`FlxBackupRestoreStart`,kind:`mutation`,plugin:`flx-backup`,document:`mutation FlxBackupRestoreStart($input: FlxBackupRestoreStartInput!) {
  flxBackupRestoreStart(input: $input) {
    job {
      ...FlxBackupJobFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxBackupJobFields on FlxBackupJob {
  status
  kind
  id
  phase
  percent
  text
  error
  token
  cancelled
  elapsed
  elapsedLabel
  eta
  etaLabel
  title
  sizeLabel
  result
}`},en={id:`fc5a400fc1bcd3cc9add10409ab93b7e08cff77540a6c3e4c425d4aba82bcd5e`,name:`FlxBackupSettingsSave`,kind:`mutation`,plugin:`flx-backup`,document:`mutation FlxBackupSettingsSave($input: FlxBackupSettingsSaveInput!) {
  flxBackupSettingsSave(input: $input) {
    settings {
      ...FlxBackupSettingsFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxBackupSettingsFields on FlxBackupSettings {
  quotaGb
  preScope
  preKeepDays
  schedule
  scheduleHour
  scheduleDay
  scheduleKeep
  includeCore
  exclude
  safetyRestore
  deleteOnUninstall
}`},tn={id:`0c1e33d45c14005f514bea1ded294ed69a0d03119f779346d180c9953fb404d5`,name:`FlxBackupSettingsScreen`,kind:`query`,plugin:`flx-backup`,document:`query FlxBackupSettingsScreen {
  flxBackupSettings {
    ...FlxBackupSettingsFields
  }
  flxBackupCounts {
    ...FlxBackupCountsFields
  }
  flxBackupHealth {
    ...FlxBackupHealthFields
  }
}

fragment FlxBackupCountsFields on FlxBackupCounts {
  all
  usedBytes
  usedLabel
  types {
    type
    label
    count
  }
}

fragment FlxBackupHealthFields on FlxBackupHealth {
  zipAvailable
  writable
  folder
  chunkSize
  nonce
  ajaxUrl
  lastRestore
  includeCoreDefault
  safetyDefault
}

fragment FlxBackupSettingsFields on FlxBackupSettings {
  quotaGb
  preScope
  preKeepDays
  schedule
  scheduleHour
  scheduleDay
  scheduleKeep
  includeCore
  exclude
  safetyRestore
  deleteOnUninstall
}`},nn={id:`6b6e92e138ef921340c8a8bd7a2c1f56d1fef85f289f28d8552e1a13c2f5afd2`,name:`FlxBackupStart`,kind:`mutation`,plugin:`flx-backup`,document:`mutation FlxBackupStart($input: FlxBackupStartInput!) {
  flxBackupStart(input: $input) {
    job {
      ...FlxBackupJobFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxBackupJobFields on FlxBackupJob {
  status
  kind
  id
  phase
  percent
  text
  error
  token
  cancelled
  elapsed
  elapsedLabel
  eta
  etaLabel
  title
  sizeLabel
  result
}`},rn={id:`3ca258ac5c8de7419cf97041ab75861522f24988dd7c92e5b855a4a785a25b1d`,name:`FlxBackupStep`,kind:`mutation`,plugin:`flx-backup`,document:`mutation FlxBackupStep {
  flxBackupStep(input: {  }) {
    job {
      ...FlxBackupJobFields
    }
  }
}

fragment FlxBackupJobFields on FlxBackupJob {
  status
  kind
  id
  phase
  percent
  text
  error
  token
  cancelled
  elapsed
  elapsedLabel
  eta
  etaLabel
  title
  sizeLabel
  result
}`},an={id:`0cdd28e36785f9a36c07bcff67a19c55e28b55316009f469fc97857134e4a2b4`,name:`FlxBlocksBlockTypeSetActive`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksBlockTypeSetActive($input: FlxBlocksBlockTypeSetActiveInput!) {
  flxBlocksBlockTypeSetActive(input: $input) {
    blockType {
      ...FlxBlocksBlockTypeRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxBlocksBlockTypeRow on FlxBlocksBlockType {
  slug
  name
  description
  active
  link
  linkText
}`},on={id:`a707f69c55e13b00163c2d5f835704b3a9234102fefdc59f7e9bf0e1861e5d87`,name:`FlxBlocksDashboard`,kind:`query`,plugin:`flx-blocks`,document:`query FlxBlocksDashboard {
  flxBlocksBlockTypes {
    ...FlxBlocksBlockTypeRow
  }
  flxBlocksSettings {
    ...FlxBlocksSettingsFields
  }
  flxBlocksLibrarySources {
    ...FlxBlocksSourceRow
  }
  flxViewer {
    canManageOptions: can(capability: "manage_options")
  }
}

fragment FlxBlocksBlockTypeRow on FlxBlocksBlockType {
  slug
  name
  description
  active
  link
  linkText
}

fragment FlxBlocksSettingsFields on FlxBlocksSettings {
  showHeadersInAdminBar
  showAdminBarSetting
  styleLibraryUrl
  editorWidth {
    enabled
    small
    large
    postDefault
    pageDefault
    contentWidth
  }
}

fragment FlxBlocksSourceRow on FlxBlocksLibrarySource {
  id
  name
  url
  hasKey
}`},sn={id:`ee8041abf4f646b0db2b506f7ede107c7bf199332d81ac3023f8a0e204408ad3`,name:`FlxBlocksLibraryPatterns`,kind:`query`,plugin:`flx-blocks`,document:`query FlxBlocksLibraryPatterns($source: ID, $category: String, $search: String, $first: Int = 24, $after: String, $refresh: Boolean) {
  flxBlocksLibrary(source: $source, refresh: $refresh) {
    name
    url
    source
    categories {
      slug
      label
      count
    }
    patterns(category: $category, search: $search, first: $first, after: $after) {
      totalCount
      nodes {
        ...FlxBlocksPatternRow
      }
      pageInfo {
        hasNextPage
        endCursor
      }
    }
  }
}

fragment FlxBlocksPatternRow on FlxBlocksPattern {
  id
  title
  description
  categories
  viewportWidth
  source
}`},cn={id:`340869fd203dcf45e0db366bded93136560b5e3c7b8ee4e7f0efce9a99a933d1`,name:`FlxBlocksLibraryShare`,kind:`query`,plugin:`flx-blocks`,document:`query FlxBlocksLibraryShare {
  flxBlocksLibraryShare {
    ...FlxBlocksShareFields
  }
}

fragment FlxBlocksShareFields on FlxBlocksLibraryShare {
  enabled
  key
  url
}`},ln={id:`a3ae64b495e2c2a1dc0d327b08e2df372ccea65c7c1827f676f6445bd7356177`,name:`FlxBlocksLibraryShareUpdate`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksLibraryShareUpdate($input: FlxBlocksLibraryShareUpdateInput!) {
  flxBlocksLibraryShareUpdate(input: $input) {
    share {
      ...FlxBlocksShareFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxBlocksShareFields on FlxBlocksLibraryShare {
  enabled
  key
  url
}`},un={id:`aab0d512355bb050e77a897874c870786b849b10ce29b327b1d353869d17292c`,name:`FlxBlocksLibrarySourceAdd`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksLibrarySourceAdd($input: FlxBlocksLibrarySourceAddInput!) {
  flxBlocksLibrarySourceAdd(input: $input) {
    source {
      ...FlxBlocksSourceRow
    }
    sources {
      ...FlxBlocksSourceRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxBlocksSourceRow on FlxBlocksLibrarySource {
  id
  name
  url
  hasKey
}`},dn={id:`5406d45161cd50d86861128e261f0716f087150902c42c151688c7ad997ab317`,name:`FlxBlocksLibrarySourceRemove`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksLibrarySourceRemove($input: FlxBlocksLibrarySourceRemoveInput!) {
  flxBlocksLibrarySourceRemove(input: $input) {
    sources {
      ...FlxBlocksSourceRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxBlocksSourceRow on FlxBlocksLibrarySource {
  id
  name
  url
  hasKey
}`},fn={id:`cebc38b2c4a42ddcdabe0145d488d587190ee259688daac8f0b2ea1e7599d82e`,name:`FlxBlocksPatternPreview`,kind:`query`,plugin:`flx-blocks`,document:`query FlxBlocksPatternPreview($id: String!, $source: ID) {
  flxBlocksPattern(id: $id, source: $source) {
    ...FlxBlocksPatternRow
    content
    rendered {
      html
      headHtml
      footerHtml
    }
  }
}

fragment FlxBlocksPatternRow on FlxBlocksPattern {
  id
  title
  description
  categories
  viewportWidth
  source
}`},pn={id:`c22bde71875f8c21a1d1477eb43dad65257b059c911c8ad41284ffe3ba8c18e8`,name:`FlxBlocksSettingsUpdate`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksSettingsUpdate($input: FlxBlocksSettingsUpdateInput!) {
  flxBlocksSettingsUpdate(input: $input) {
    settings {
      ...FlxBlocksSettingsFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxBlocksSettingsFields on FlxBlocksSettings {
  showHeadersInAdminBar
  showAdminBarSetting
  styleLibraryUrl
  editorWidth {
    enabled
    small
    large
    postDefault
    pageDefault
    contentWidth
  }
}`},mn={id:`edb4984f25c406a9143913da2ef715d97887a74bbacdcda82da3f7a8c9707ca2`,name:`FlxChatbotConversationClose`,kind:`mutation`,plugin:`flx-chatbot`,document:`mutation FlxChatbotConversationClose($input: FlxChatbotConversationCloseInput!) {
  flxChatbotConversationClose(input: $input) {
    conversation {
      ...FlxChatbotConversationRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxChatbotConversationRow on FlxChatbotConversation {
  id
  databaseId
  title
  name
  email
  phone
  status
  unread
  rating
  ratingNote
  isLead
  orderId
  orderUrl
  pageUrl
  pageTitle
  userId
  agentName
  fluxChatUrl
  summary
  lastActivity {
    timestamp
    label
    relative
  }
  online
  typing
  lastMessage {
    role
    body
  }
}`},hn={id:`06f77cf96adcd58c306be0a2123151c07f7b20c102c675ac503f4e0f2193d3aa`,name:`FlxChatbotConversationDelete`,kind:`mutation`,plugin:`flx-chatbot`,document:`mutation FlxChatbotConversationDelete($input: FlxChatbotConversationDeleteInput!) {
  flxChatbotConversationDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},gn={id:`0159e5c0272f4a76eb58d3908ee9e42355887bca6384a602ca16fa3ded368fb7`,name:`FlxChatbotConversationList`,kind:`query`,plugin:`flx-chatbot`,document:`query FlxChatbotConversationList($filter: FlxChatbotConversationFilter, $search: String, $first: Int = 50) {
  flxChatbotConversations(filter: $filter, search: $search, first: $first) {
    nodes {
      ...FlxChatbotConversationRow
    }
    pageInfo {
      hasNextPage
      endCursor
    }
    totalCount
  }
}

fragment FlxChatbotConversationRow on FlxChatbotConversation {
  id
  databaseId
  title
  name
  email
  phone
  status
  unread
  rating
  ratingNote
  isLead
  orderId
  orderUrl
  pageUrl
  pageTitle
  userId
  agentName
  fluxChatUrl
  summary
  lastActivity {
    timestamp
    label
    relative
  }
  online
  typing
  lastMessage {
    role
    body
  }
}`},_n={id:`c22dcbbc9132d33808d9995d8744230f8914601afae91ff9f71b5aa8e40f71f4`,name:`FlxChatbotConversationOpen`,kind:`query`,plugin:`flx-chatbot`,document:`query FlxChatbotConversationOpen($id: ID!, $afterId: Int = 0, $peek: Boolean = false) {
  flxChatbotConversation(id: $id) {
    ...FlxChatbotConversationRow
  }
  flxChatbotMessages(conversationId: $id, afterId: $afterId, peek: $peek) {
    nodes {
      ...FlxChatbotMessageFields
    }
    lastId
    hasMore
  }
}

fragment FlxChatbotConversationRow on FlxChatbotConversation {
  id
  databaseId
  title
  name
  email
  phone
  status
  unread
  rating
  ratingNote
  isLead
  orderId
  orderUrl
  pageUrl
  pageTitle
  userId
  agentName
  fluxChatUrl
  summary
  lastActivity {
    timestamp
    label
    relative
  }
  online
  typing
  lastMessage {
    role
    body
  }
}

fragment FlxChatbotMessageFields on FlxChatbotMessage {
  databaseId
  role
  type
  body
  sentAt {
    timestamp
    label
  }
  author
  file {
    name
    mime
    url
  }
  cards {
    title
    url
    image
    price
    inStock
    cartUrl
  }
}`},vn={id:`019b8ae4942af3eb50b362f80c7946ad71090cbad2a0b11a8383c6dc969665f8`,name:`FlxChatbotConversationReply`,kind:`mutation`,plugin:`flx-chatbot`,document:`mutation FlxChatbotConversationReply($input: FlxChatbotConversationReplyInput!) {
  flxChatbotConversationReply(input: $input) {
    conversation {
      ...FlxChatbotConversationRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxChatbotConversationRow on FlxChatbotConversation {
  id
  databaseId
  title
  name
  email
  phone
  status
  unread
  rating
  ratingNote
  isLead
  orderId
  orderUrl
  pageUrl
  pageTitle
  userId
  agentName
  fluxChatUrl
  summary
  lastActivity {
    timestamp
    label
    relative
  }
  online
  typing
  lastMessage {
    role
    body
  }
}`},yn={id:`4b73e1d9351052ac89be5c9f77fe281fc9c69498e55c1b83e984e65c49ddcafd`,name:`FlxChatbotConversationTyping`,kind:`mutation`,plugin:`flx-chatbot`,document:`mutation FlxChatbotConversationTyping($input: FlxChatbotConversationTypingInput!) {
  flxChatbotConversationTyping(input: $input) {
    ok
  }
}`},bn={id:`2578214d2427732b0bca1b77938018e459d2b57a4a4b50fab18baa2c9c90171f`,name:`FlxChatbotInbox`,kind:`query`,plugin:`flx-chatbot`,document:`query FlxChatbotInbox {
  flxChatbotInbox {
    fluxChatUrl
    settingsUrl
    canDelete
    agentName
    maxUploadMb
  }
}`},xn={id:`58380f4eafb8b23ee0ed48cc1b49d596c5a05fda9b8cce508d5cc3a3ebcd0b4c`,name:`FlxChatbotSettingsSave`,kind:`mutation`,plugin:`flx-chatbot`,document:`mutation FlxChatbotSettingsSave($input: FlxChatbotSettingsSaveInput!) {
  flxChatbotSettingsSave(input: $input) {
    settings {
      ...FlxChatbotSettingsFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxChatbotSettingsFields on FlxSettingsChatbot {
  enabled
  mode
  botName
  avatarId
  avatarIcon
  color
  colorText
  theme
  position
  offsetX
  offsetY
  launcherIcon
  launcherLabel
  launcherSize
  radius
  width
  headerStyle
  font
  mobileFull
  showBranding
  sound
  homeEnabled
  homeTitle
  homeText
  welcome
  placeholder
  quickReplies
  links
  showTeam
  teaserEnabled
  teaserText
  teaserDelay
  aiPersona
  aiInstructions
  aiKnowledge
  aiSrcPages
  aiSrcPosts
  aiSrcProducts
  aiSrcWiki
  aiToolOrders
  aiToolLead
  aiCards
  aiMaxTurns
  aiHourLimit
  aiDailyLimit
  handoffTo
  hoursMode
  hours
  askEmail
  notifyEmail
  emailReplies
  rating
  uploads
  uploadMb
  dictation
  offlineText
  audience
  deviceDesktop
  deviceMobile
  excludeUrls
  retentionDays
  privacyText
  showOnHome
  showOnPosts
  showOnPages
  showOnShop
  showOnCart
  showOnCheckout
  showOnAccount
  showOnOther
}`},Sn={id:`c116623608453060a2014d87ec9efcc566c9956ce079c536f86803f37e4f56e7`,name:`FlxChatbotSettingsScreen`,kind:`query`,plugin:`flx-chatbot`,document:`query FlxChatbotSettingsScreen($days: Int = 30) {
  flxSettings {
    chatbot {
      ...FlxChatbotSettingsFields
    }
  }
  flxChatbotAdmin {
    stats(days: $days) {
      total
      human
      resolved
      leads
      orders
      rating
      rated
      open
    }
    status {
      key
      state
      title
      text
      url
      urlLabel
    }
    textDefaults {
      key
      text
    }
    pageTypes {
      key
      label
    }
    weekdays {
      key
      label
    }
    avatarUrl
    notifyEmailDefault
    aiReady
    aiSettingsUrl
    fluxChatReady
    fluxChatActive
    shopActive
    ordersActive
    wikiActive
    inboxUrl
    siteUrl
    preview {
      config
      scriptUrl
    }
  }
}

fragment FlxChatbotSettingsFields on FlxSettingsChatbot {
  enabled
  mode
  botName
  avatarId
  avatarIcon
  color
  colorText
  theme
  position
  offsetX
  offsetY
  launcherIcon
  launcherLabel
  launcherSize
  radius
  width
  headerStyle
  font
  mobileFull
  showBranding
  sound
  homeEnabled
  homeTitle
  homeText
  welcome
  placeholder
  quickReplies
  links
  showTeam
  teaserEnabled
  teaserText
  teaserDelay
  aiPersona
  aiInstructions
  aiKnowledge
  aiSrcPages
  aiSrcPosts
  aiSrcProducts
  aiSrcWiki
  aiToolOrders
  aiToolLead
  aiCards
  aiMaxTurns
  aiHourLimit
  aiDailyLimit
  handoffTo
  hoursMode
  hours
  askEmail
  notifyEmail
  emailReplies
  rating
  uploads
  uploadMb
  dictation
  offlineText
  audience
  deviceDesktop
  deviceMobile
  excludeUrls
  retentionDays
  privacyText
  showOnHome
  showOnPosts
  showOnPages
  showOnShop
  showOnCart
  showOnCheckout
  showOnAccount
  showOnOther
}`},Cn={id:`7a2703962a923dbd66bbb68ae1b67a23777d1f64cd8eebb3f7f7e3ac839725c7`,name:`FlxCrmContact`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmContact($id: ID!) {
  flxContact(id: $id) {
    ...FlxCrmContactCard
  }
}

fragment FlxCrmContactCard on FlxContact {
  ...FlxCrmContactRow
  rawName
  country
  source
  sourceLabel
  utmSource
  utmCampaign
  userId
  ownerId
  ownerName
  note
  paidDays
  firstPaidAt {
    ...FlxCrmWhen
  }
  avgGapDays
  rfm {
    recency
    frequency
    monetary
  }
  consentAt {
    ...FlxCrmWhen
  }
  editUrl
  sources {
    label
    url
    date
  }
}

fragment FlxCrmContactRow on FlxContact {
  id
  databaseId
  name
  email
  phone {
    e164
    display
  }
  avatar
  company
  city
  roles
  donations
  orders
  segment
  segmentLabel
  ltv {
    ...FlxCrmMoney
  }
  churn
  atRisk
  status
  lastActivityAt {
    ...FlxCrmWhen
  }
  lastPaidAt {
    ...FlxCrmWhen
  }
  createdAt {
    ...FlxCrmWhen
  }
  consent {
    email
    sms
    whatsapp
    ads
  }
  tags {
    ...FlxCrmTag
  }
}

fragment FlxCrmMoney on FlxMoney {
  amount
  currency
  formatted
}

fragment FlxCrmTag on FlxTag {
  databaseId
  name
  color
  count
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  label
  relative
}`},wn={id:`d64b39e87439228efb39cf454ec4b8156cf3e0fd2704751e0c30f58a4d4e8698`,name:`FlxCrmContactBulk`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactBulk($input: FlxContactBulkInput!) {
  flxContactBulk(input: $input) {
    changed
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},Tn={id:`1ddbb8969d399e686efebc66dfd2669494b2a20c18beffe637a9d26bba6c9e94`,name:`FlxCrmContactCreate`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactCreate($input: FlxContactCreateInput!) {
  flxContactCreate(input: $input) {
    contact {
      ...FlxCrmContactCard
    }
    existed
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmContactCard on FlxContact {
  ...FlxCrmContactRow
  rawName
  country
  source
  sourceLabel
  utmSource
  utmCampaign
  userId
  ownerId
  ownerName
  note
  paidDays
  firstPaidAt {
    ...FlxCrmWhen
  }
  avgGapDays
  rfm {
    recency
    frequency
    monetary
  }
  consentAt {
    ...FlxCrmWhen
  }
  editUrl
  sources {
    label
    url
    date
  }
}

fragment FlxCrmContactRow on FlxContact {
  id
  databaseId
  name
  email
  phone {
    e164
    display
  }
  avatar
  company
  city
  roles
  donations
  orders
  segment
  segmentLabel
  ltv {
    ...FlxCrmMoney
  }
  churn
  atRisk
  status
  lastActivityAt {
    ...FlxCrmWhen
  }
  lastPaidAt {
    ...FlxCrmWhen
  }
  createdAt {
    ...FlxCrmWhen
  }
  consent {
    email
    sms
    whatsapp
    ads
  }
  tags {
    ...FlxCrmTag
  }
}

fragment FlxCrmMoney on FlxMoney {
  amount
  currency
  formatted
}

fragment FlxCrmTag on FlxTag {
  databaseId
  name
  color
  count
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  label
  relative
}`},En={id:`e5ac09b6da29cd8a29870ad1eeecceb4e0930a083afacb5cb0f0823727443497`,name:`FlxCrmContactDelete`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactDelete($input: FlxContactDeleteInput!) {
  flxContactDelete(input: $input) {
    deletedId
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},Dn={id:`5b37df020f5fcf7eb644d59e7d43255a10daf12b3e1e81becfe80e6be2b63666`,name:`FlxCrmContactMerge`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactMerge($input: FlxContactMergeInput!) {
  flxContactMerge(input: $input) {
    merged
    contact {
      id
      databaseId
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},On={id:`78fef294f41cc0264573687c40208fb3d18ade9ed42cf1d4328c25414ceb2aa3`,name:`FlxCrmContacts`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmContacts($filter: FlxContactFilterInput, $orderBy: FlxContactOrderBy, $order: FlxContactOrder, $first: Int = 25, $after: String) {
  flxContacts(
    filter: $filter
    orderBy: $orderBy
    order: $order
    first: $first
    after: $after
  ) {
    totalCount
    nodes {
      ...FlxCrmContactRow
    }
    pageInfo {
      hasNextPage
      hasPreviousPage
      startCursor
      endCursor
    }
  }
}

fragment FlxCrmContactRow on FlxContact {
  id
  databaseId
  name
  email
  phone {
    e164
    display
  }
  avatar
  company
  city
  roles
  donations
  orders
  segment
  segmentLabel
  ltv {
    ...FlxCrmMoney
  }
  churn
  atRisk
  status
  lastActivityAt {
    ...FlxCrmWhen
  }
  lastPaidAt {
    ...FlxCrmWhen
  }
  createdAt {
    ...FlxCrmWhen
  }
  consent {
    email
    sms
    whatsapp
    ads
  }
  tags {
    ...FlxCrmTag
  }
}

fragment FlxCrmMoney on FlxMoney {
  amount
  currency
  formatted
}

fragment FlxCrmTag on FlxTag {
  databaseId
  name
  color
  count
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  label
  relative
}`},kn={id:`5c13b0d3b065e0dd85f87fc9e53cbb77e20f0338223bcdd15c5a57a504623083`,name:`FlxCrmContactUpdate`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactUpdate($input: FlxContactUpdateInput!) {
  flxContactUpdate(input: $input) {
    contact {
      ...FlxCrmContactCard
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmContactCard on FlxContact {
  ...FlxCrmContactRow
  rawName
  country
  source
  sourceLabel
  utmSource
  utmCampaign
  userId
  ownerId
  ownerName
  note
  paidDays
  firstPaidAt {
    ...FlxCrmWhen
  }
  avgGapDays
  rfm {
    recency
    frequency
    monetary
  }
  consentAt {
    ...FlxCrmWhen
  }
  editUrl
  sources {
    label
    url
    date
  }
}

fragment FlxCrmContactRow on FlxContact {
  id
  databaseId
  name
  email
  phone {
    e164
    display
  }
  avatar
  company
  city
  roles
  donations
  orders
  segment
  segmentLabel
  ltv {
    ...FlxCrmMoney
  }
  churn
  atRisk
  status
  lastActivityAt {
    ...FlxCrmWhen
  }
  lastPaidAt {
    ...FlxCrmWhen
  }
  createdAt {
    ...FlxCrmWhen
  }
  consent {
    email
    sms
    whatsapp
    ads
  }
  tags {
    ...FlxCrmTag
  }
}

fragment FlxCrmMoney on FlxMoney {
  amount
  currency
  formatted
}

fragment FlxCrmTag on FlxTag {
  databaseId
  name
  color
  count
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  label
  relative
}`},An={id:`d8cbff1ab3075cba9ae5ea057a6d68195e6aaa27a724bbf9c924efa5974873cd`,name:`FlxCrmDuplicates`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmDuplicates {
  flxContactDuplicates {
    why
    items {
      usedBy
      contact {
        ...FlxCrmContactRow
      }
    }
  }
}

fragment FlxCrmContactRow on FlxContact {
  id
  databaseId
  name
  email
  phone {
    e164
    display
  }
  avatar
  company
  city
  roles
  donations
  orders
  segment
  segmentLabel
  ltv {
    ...FlxCrmMoney
  }
  churn
  atRisk
  status
  lastActivityAt {
    ...FlxCrmWhen
  }
  lastPaidAt {
    ...FlxCrmWhen
  }
  createdAt {
    ...FlxCrmWhen
  }
  consent {
    email
    sms
    whatsapp
    ads
  }
  tags {
    ...FlxCrmTag
  }
}

fragment FlxCrmMoney on FlxMoney {
  amount
  currency
  formatted
}

fragment FlxCrmTag on FlxTag {
  databaseId
  name
  color
  count
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  label
  relative
}`},jn={id:`fe10d4a31eedbbd448e3769a47a190a8872c2bdad2256def5f7e54cdf091eae0`,name:`FlxCrmEventAdd`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmEventAdd($input: FlxContactEventAddInput!) {
  flxContactEventAdd(input: $input) {
    event {
      ...FlxCrmEvent
    }
    contact {
      id
      lastActivityAt {
        ...FlxCrmWhen
      }
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmEvent on FlxContactEvent {
  databaseId
  contactId
  contactName
  type
  typeLabel
  icon
  source
  title
  body
  amount {
    ...FlxCrmMoney
  }
  paid
  status
  url
  userName
  at {
    ...FlxCrmWhen
  }
  answers {
    question
    answer
  }
  deletable
}

fragment FlxCrmMoney on FlxMoney {
  amount
  currency
  formatted
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  label
  relative
}`},Mn={id:`a76a713193a0c8d25b06c56932ac7a945251d1c2966e1e35351c2b154124d10d`,name:`FlxCrmEventDelete`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmEventDelete($input: FlxContactEventDeleteInput!) {
  flxContactEventDelete(input: $input) {
    deletedId
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},Nn={id:`e4410099ca450172284928b787d6653e3a20bb9fea759f3405dcc65b4b2d60fc`,name:`FlxCrmJourneyDelete`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmJourneyDelete($input: FlxJourneyDeleteInput!) {
  flxJourneyDelete(input: $input) {
    deletedId
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},Pn={id:`81f33cb7df2b01fe473af742d3664bc98d855fff2d8d6c6bd10bf813b3126a00`,name:`FlxCrmJourneyFromTemplate`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmJourneyFromTemplate($input: FlxJourneyFromTemplateInput!) {
  flxJourneyFromTemplate(input: $input) {
    journey {
      ...FlxCrmJourneyRow
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmJourneyRow on FlxJourney {
  databaseId
  name
  status
  description
  triggerType
  triggerValue
  steps {
    type
    days
    hours
    subject
    text
    condition
    value
    tag
  }
  opts {
    goal
    reenterDays
    quietFrom
    quietTo
    capWeek
  }
  stats {
    entered
    finished
    goal
    sent
    skipped
    active
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},Fn={id:`1d65e68f34e737d57ba69b8f75f693f31df5df46147478680767d4a886eba9a4`,name:`FlxCrmJourneys`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmJourneys {
  flxJourneys {
    ...FlxCrmJourneyRow
  }
  flxJourneyConfig {
    triggers {
      key
      label
    }
    stepTypes {
      key
      label
    }
    conditions {
      key
      label
    }
    statuses {
      key
      label
    }
    goals {
      key
      label
    }
    templates {
      key
      label
    }
    defaults {
      goal
      reenterDays
      quietFrom
      quietTo
      capWeek
    }
    triggerValues {
      trigger
      values {
        value
        label
      }
    }
    mailActive
  }
}

fragment FlxCrmJourneyRow on FlxJourney {
  databaseId
  name
  status
  description
  triggerType
  triggerValue
  steps {
    type
    days
    hours
    subject
    text
    condition
    value
    tag
  }
  opts {
    goal
    reenterDays
    quietFrom
    quietTo
    capWeek
  }
  stats {
    entered
    finished
    goal
    sent
    skipped
    active
  }
}`},In={id:`bb7940aae0aa4487e6596f7310dfe9d6c66cd05f801d9d2571a2fbd024003e89`,name:`FlxCrmJourneySave`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmJourneySave($input: FlxJourneySaveInput!) {
  flxJourneySave(input: $input) {
    journey {
      ...FlxCrmJourneyRow
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmJourneyRow on FlxJourney {
  databaseId
  name
  status
  description
  triggerType
  triggerValue
  steps {
    type
    days
    hours
    subject
    text
    condition
    value
    tag
  }
  opts {
    goal
    reenterDays
    quietFrom
    quietTo
    capWeek
  }
  stats {
    entered
    finished
    goal
    sent
    skipped
    active
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},Ln={id:`271eb15db3d24fbf7acc5848820ed761c6fdcece95a02526b41ff2a7316997a2`,name:`FlxCrmJourneySetStatus`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmJourneySetStatus($input: FlxJourneySetStatusInput!) {
  flxJourneySetStatus(input: $input) {
    journey {
      ...FlxCrmJourneyRow
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmJourneyRow on FlxJourney {
  databaseId
  name
  status
  description
  triggerType
  triggerValue
  steps {
    type
    days
    hours
    subject
    text
    condition
    value
    tag
  }
  opts {
    goal
    reenterDays
    quietFrom
    quietTo
    capWeek
  }
  stats {
    entered
    finished
    goal
    sent
    skipped
    active
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},Rn={id:`172de029371f50368a15f9bd18e6643d27d6d370593aeac6979a894b17a7bf8b`,name:`FlxCrmOverview`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmOverview {
  flxCrmOverview {
    total
    new30
    newPrev
    active
    risk
    ltv {
      ...FlxCrmMoney
    }
    paying
    repeat
    currency
    activeDays
    riskThreshold
    scoredAt {
      ...FlxCrmWhen
    }
    syncedAt {
      ...FlxCrmWhen
    }
    needsSync
    syncSources
    roles {
      key
      label
      color
      count
    }
    segments {
      key
      label
      hint
      tone
      count
      value {
        ...FlxCrmMoney
      }
    }
    sources {
      key
      label
      count
    }
  }
  flxCrmRecentEvents(limit: 8) {
    ...FlxCrmEvent
  }
  atRisk: flxContacts(
    first: 6
    filter: { atRisk: true }
    orderBy: LTV
    order: DESC
  ) {
    nodes {
      ...FlxCrmContactRow
    }
  }
}

fragment FlxCrmContactRow on FlxContact {
  id
  databaseId
  name
  email
  phone {
    e164
    display
  }
  avatar
  company
  city
  roles
  donations
  orders
  segment
  segmentLabel
  ltv {
    ...FlxCrmMoney
  }
  churn
  atRisk
  status
  lastActivityAt {
    ...FlxCrmWhen
  }
  lastPaidAt {
    ...FlxCrmWhen
  }
  createdAt {
    ...FlxCrmWhen
  }
  consent {
    email
    sms
    whatsapp
    ads
  }
  tags {
    ...FlxCrmTag
  }
}

fragment FlxCrmEvent on FlxContactEvent {
  databaseId
  contactId
  contactName
  type
  typeLabel
  icon
  source
  title
  body
  amount {
    ...FlxCrmMoney
  }
  paid
  status
  url
  userName
  at {
    ...FlxCrmWhen
  }
  answers {
    question
    answer
  }
  deletable
}

fragment FlxCrmMoney on FlxMoney {
  amount
  currency
  formatted
}

fragment FlxCrmTag on FlxTag {
  databaseId
  name
  color
  count
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  label
  relative
}`},zn={id:`e0edd7dec5bacd4fa0efa1061b492dfd4514405725805c3d2dad8e334f1fa625`,name:`FlxCrmScoreRun`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmScoreRun($input: FlxCrmScoreRunInput!) {
  flxCrmScoreRun(input: $input) {
    result {
      scored
      changed
      done
      total
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},Bn={id:`2f13b0c4d23cfcf18bb040c35a545c3899b8be9c777bef71b9a3a1889584e38c`,name:`FlxCrmSegmentDelete`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmSegmentDelete($input: FlxSegmentDeleteInput!) {
  flxSegmentDelete(input: $input) {
    deletedId
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},Vn={id:`acc491cf70a4b00af7fce340efd2fd26dc087fe84690b01a364966abf7dea880`,name:`FlxCrmSegmentEditor`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmSegmentEditor {
  flxSegmentEditor {
    fields {
      key
      label
      type
      ops
    }
    ops {
      key
      label
    }
    options {
      type
      values {
        value
        label
      }
    }
  }
}`},Hn={id:`564b5e761395c08e8045dbc65f96f00b7f4fa14bc2dcfa48e28a0beff5edd00e`,name:`FlxCrmSegmentPreview`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmSegmentPreview($match: FlxSegmentMatch, $rules: [FlxSegmentRuleInput!]!) {
  flxSegmentPreview(match: $match, rules: $rules) {
    count
    summary
    sample {
      databaseId
      name
    }
  }
}`},Un={id:`6035e1dbb5301cebeb87fa3481cf44003f54cdd356668d7d22ff87b96ec87866`,name:`FlxCrmSegmentSave`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmSegmentSave($input: FlxSegmentSaveInput!) {
  flxSegmentSave(input: $input) {
    segment {
      ...FlxCrmSegmentRow
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmSegmentRow on FlxSegment {
  databaseId
  name
  description
  count
  summary
  match
  rules {
    field
    op
    value
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},Wn={id:`4e800655079b39d7b0c8f5dca828111815df5e95cd0e91406785150732efd4a3`,name:`FlxCrmSettings`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmSettings {
  flxCrmSettings {
    chatEvents
    aiTools
    aiWrite
    aiDestructive
    riskThreshold
    activeDays
    baseCurrency
    currency
    sources {
      key
      label
      description
      installed
      enabled
      hasRole
      role
    }
    roles {
      key
      label
      color
      builtin
    }
    syncedAt {
      ...FlxCrmWhen
    }
    scoredAt {
      ...FlxCrmWhen
    }
  }
  flxCrmAi {
    aiInstalled
    mcpInstalled
    mcpEnabled
    mcpUrl
    mcpAdminUrl
    toolsOn
    tools {
      name
      title
      kind
      kindLabel
      enabled
    }
  }
  flxCrmAiLog(limit: 15) {
    at {
      ...FlxCrmWhen
    }
    who
    channel
    channelLabel
    action
    target
    ok
    error
  }
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  label
  relative
}`},Gn={id:`6a79c573c70d3bbbdef5121997576cc4a22dfbd6b0a88b874a7029fa85cccbea`,name:`FlxCrmSettingsSave`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmSettingsSave($input: FlxCrmSettingsSaveInput!) {
  flxCrmSettingsSave(input: $input) {
    settings {
      chatEvents
      aiTools
      aiWrite
      aiDestructive
      riskThreshold
      activeDays
      baseCurrency
      currency
      sources {
        key
        label
        description
        installed
        enabled
        hasRole
        role
      }
      roles {
        key
        label
        color
        builtin
      }
      syncedAt {
        ...FlxCrmWhen
      }
      scoredAt {
        ...FlxCrmWhen
      }
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  label
  relative
}`},Kn={id:`3a59d8e4195bf7ba635933d4e7baafd37f013da4a16f4d4cfc49b7501f3a7de4`,name:`FlxCrmShell`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmShell {
  flxCrmMeta {
    roles {
      key
      label
      color
      builtin
      hidden
    }
    rfm {
      key
      label
      hint
      tone
    }
    eventTypes {
      key
      label
      icon
      manual
    }
    sources {
      key
      label
    }
    consents {
      key
      label
    }
    timelineFilters {
      key
      label
    }
    currency
    canWrite
    hasPackageSections
    owners {
      databaseId
      name
    }
  }
  flxTags {
    ...FlxCrmTag
  }
  flxSegments {
    ...FlxCrmSegmentRow
  }
}

fragment FlxCrmSegmentRow on FlxSegment {
  databaseId
  name
  description
  count
  summary
  match
  rules {
    field
    op
    value
  }
}

fragment FlxCrmTag on FlxTag {
  databaseId
  name
  color
  count
}`},qn={id:`91833bc806371dcec389dec7ee846bdc3806edd84b27da2b516ee064b794c6a5`,name:`FlxCrmSyncStep`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmSyncStep($input: FlxCrmSyncStepInput!) {
  flxCrmSyncStep(input: $input) {
    result {
      step
      label
      scanned
      total
      next
      page
      finished
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},$={id:`803c73840e66e14fc6312eb733e95eb00bb1261c0fcdcaa93508c07d05751c35`,name:`FlxCrmTagAdd`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmTagAdd($input: FlxContactTagAddInput!) {
  flxContactTagAdd(input: $input) {
    tag {
      ...FlxCrmTag
    }
    contact {
      id
      tags {
        ...FlxCrmTag
      }
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmTag on FlxTag {
  databaseId
  name
  color
  count
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},Jn={id:`68836030d68ed1f45df0595812ec3fbbf361855944537c066a08b8ea641057fa`,name:`FlxCrmTagRemove`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmTagRemove($input: FlxContactTagRemoveInput!) {
  flxContactTagRemove(input: $input) {
    contact {
      id
      tags {
        ...FlxCrmTag
      }
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmTag on FlxTag {
  databaseId
  name
  color
  count
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},Yn={id:`b019ec38b06648c194238f86b99019a21dcdd4ae0092c44cddbc896a1421275c`,name:`FlxCrmTimeline`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmTimeline($id: ID!, $filter: String, $first: Int = 20, $after: String) {
  flxContact(id: $id) {
    id
    timeline(filter: $filter, first: $first, after: $after) {
      nodes {
        ...FlxCrmEvent
      }
      pageInfo {
        hasNextPage
        endCursor
      }
    }
  }
}

fragment FlxCrmEvent on FlxContactEvent {
  databaseId
  contactId
  contactName
  type
  typeLabel
  icon
  source
  title
  body
  amount {
    ...FlxCrmMoney
  }
  paid
  status
  url
  userName
  at {
    ...FlxCrmWhen
  }
  answers {
    question
    answer
  }
  deletable
}

fragment FlxCrmMoney on FlxMoney {
  amount
  currency
  formatted
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  label
  relative
}`},Xn={id:`61dca741b4cda6ec8f60505b8548df2e5d33c72546464ffa58e107cc85ba83d5`,name:`FlxFilesAiEdit`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesAiEdit($input: FlxFilesAiEditInput!) {
  flxFilesAiEdit(input: $input) {
    content
    note
    errors {
      ...FlxFilesUserError
    }
  }
}

fragment FlxFilesUserError on FlxUserError {
  code
  message
  field
}`},Zn={id:`32abcee8e0a969a62b439f52fbce12c71c847005925cfae133f29ebb40068a31`,name:`FlxFilesAiExplain`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesAiExplain($input: FlxFilesAiExplainInput!) {
  flxFilesAiExplain(input: $input) {
    answer
    errors {
      ...FlxFilesUserError
    }
  }
}

fragment FlxFilesUserError on FlxUserError {
  code
  message
  field
}`},Qn={id:`11c4bcaba7b6a88fc32514cdc6c72fa7eddee29ae51a30d924c9ef337ae01482`,name:`FlxFilesBootstrap`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesBootstrap {
  flxFilesConfig {
    rootPath
    rootName
    uploadsPath
    uploadsUrl
    homeUrl
    fileUrl
    host
    writable
    chunkSize
    aiReady
  }
  flxFilesSettings {
    ...FlxFilesSettingsFields
  }
}

fragment FlxFilesSettingsFields on FlxFilesSettings {
  trashDays
  versions
  quotaGb
  showHidden
  mcpWrite
  aiCode
  shared
}`},$n={id:`4f023bb510e149c91f1629825589973c85cae4835fa812ad549677ba6980d5bc`,name:`FlxFilesChmod`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesChmod($input: FlxFilesChmodInput!) {
  flxFilesChmod(input: $input) {
    item {
      ...FlxFilesItemFields
    }
    errors {
      ...FlxFilesUserError
    }
  }
}

fragment FlxFilesItemFields on FlxFilesItem {
  name
  path
  isDir
  kind
  extension
  size
  modified {
    iso
    label
    relative
    timestamp
  }
  permissions
  writable
  locked
  core
  sensitive
  shared
  isLink
  itemCount
}

fragment FlxFilesUserError on FlxUserError {
  code
  message
  field
}`},er={id:`f68b3adcc3c580e155d2613c5ebd32eda97074945cf130cd3b0c3aa8b15796e9`,name:`FlxFilesCopy`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesCopy($input: FlxFilesCopyInput!) {
  flxFilesCopy(input: $input) {
    items {
      ...FlxFilesItemFields
    }
    failed {
      ...FlxFilesFailureFields
    }
    errors {
      ...FlxFilesUserError
    }
  }
}

fragment FlxFilesFailureFields on FlxFilesFailure {
  path
  message
}

fragment FlxFilesItemFields on FlxFilesItem {
  name
  path
  isDir
  kind
  extension
  size
  modified {
    iso
    label
    relative
    timestamp
  }
  permissions
  writable
  locked
  core
  sensitive
  shared
  isLink
  itemCount
}

fragment FlxFilesUserError on FlxUserError {
  code
  message
  field
}`},tr={id:`9a932876948ac21226ac489170974c79b8567a55bd985e88b64f69a0f76baee6`,name:`FlxFilesDisk`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesDisk($refresh: Boolean) {
  flxFilesDisk(refresh: $refresh) {
    usedBytes
    totalBytes
    fileCount
    measured
    scanning
  }
}`},nr={id:`f669e131af94b4ab873afbea5a686bef1d50491e121c61abf6d622f7de7ee944`,name:`FlxFilesDuplicate`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesDuplicate($input: FlxFilesDuplicateInput!) {
  flxFilesDuplicate(input: $input) {
    items {
      ...FlxFilesItemFields
    }
    failed {
      ...FlxFilesFailureFields
    }
    errors {
      ...FlxFilesUserError
    }
  }
}

fragment FlxFilesFailureFields on FlxFilesFailure {
  path
  message
}

fragment FlxFilesItemFields on FlxFilesItem {
  name
  path
  isDir
  kind
  extension
  size
  modified {
    iso
    label
    relative
    timestamp
  }
  permissions
  writable
  locked
  core
  sensitive
  shared
  isLink
  itemCount
}

fragment FlxFilesUserError on FlxUserError {
  code
  message
  field
}`},rr={id:`68f93e146bd77ed04fea958b274e19984ff9fea5474d5c1818fea7a00976d3aa`,name:`FlxFilesFile`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesFile($path: String!) {
  flxFilesFile(path: $path) {
    hash
    content
    item {
      ...FlxFilesItemFields
    }
  }
}

fragment FlxFilesItemFields on FlxFilesItem {
  name
  path
  isDir
  kind
  extension
  size
  modified {
    iso
    label
    relative
    timestamp
  }
  permissions
  writable
  locked
  core
  sensitive
  shared
  isLink
  itemCount
}`},ir={id:`eff7fe14bc929cb4523e5cc13b4c9944073068de7ed0704080b8676ab83e75da`,name:`FlxFilesFolder`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesFolder($path: String!, $first: Int = 500, $after: String, $sort: FlxFilesSort, $order: FlxFilesOrder, $hidden: Boolean, $foldersOnly: Boolean, $countItems: Boolean) {
  flxFilesFolder(path: $path) {
    path
    parent
    dir {
      ...FlxFilesItemFields
    }
    items(
      first: $first
      after: $after
      sort: $sort
      order: $order
      hidden: $hidden
      foldersOnly: $foldersOnly
      countItems: $countItems
    ) {
      totalCount
      pageInfo {
        hasNextPage
        endCursor
      }
      nodes {
        ...FlxFilesItemFields
      }
    }
  }
}

fragment FlxFilesItemFields on FlxFilesItem {
  name
  path
  isDir
  kind
  extension
  size
  modified {
    iso
    label
    relative
    timestamp
  }
  permissions
  writable
  locked
  core
  sensitive
  shared
  isLink
  itemCount
}`},ar={id:`3df09f77ce66415c30bf3f11448063018063298f8afe8693354151f339789a2d`,name:`FlxFilesFolderCreate`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesFolderCreate($input: FlxFilesFolderCreateInput!) {
  flxFilesFolderCreate(input: $input) {
    item {
      ...FlxFilesItemFields
    }
    errors {
      ...FlxFilesUserError
    }
  }
}

fragment FlxFilesItemFields on FlxFilesItem {
  name
  path
  isDir
  kind
  extension
  size
  modified {
    iso
    label
    relative
    timestamp
  }
  permissions
  writable
  locked
  core
  sensitive
  shared
  isLink
  itemCount
}

fragment FlxFilesUserError on FlxUserError {
  code
  message
  field
}`},or={id:`df297c445606cc8674482cb2a7dad93ef00b19f27724a00e88ff1013a86847bc`,name:`FlxFilesLog`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesLog($first: Int = 50, $after: String, $path: String, $via: FlxFilesVia) {
  flxFilesLog(first: $first, after: $after, path: $path, via: $via) {
    totalCount
    pageInfo {
      hasNextPage
      endCursor
    }
    nodes {
      action
      path
      to
      user
      userId
      via
      when {
        iso
        label
        relative
      }
    }
  }
}`},sr={id:`ed9598e1137f47c40a7f58d684fd9cd4a825c7389ea9a74ee2d41891f38db51c`,name:`FlxFilesMove`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesMove($input: FlxFilesMoveInput!) {
  flxFilesMove(input: $input) {
    items {
      ...FlxFilesItemFields
    }
    failed {
      ...FlxFilesFailureFields
    }
    errors {
      ...FlxFilesUserError
    }
  }
}

fragment FlxFilesFailureFields on FlxFilesFailure {
  path
  message
}

fragment FlxFilesItemFields on FlxFilesItem {
  name
  path
  isDir
  kind
  extension
  size
  modified {
    iso
    label
    relative
    timestamp
  }
  permissions
  writable
  locked
  core
  sensitive
  shared
  isLink
  itemCount
}

fragment FlxFilesUserError on FlxUserError {
  code
  message
  field
}`},cr={id:`4fda84d50728f46b957ba8c54dd0ddfa92f51531d8881ae73a899645d69dda5b`,name:`FlxFilesRename`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesRename($input: FlxFilesRenameInput!) {
  flxFilesRename(input: $input) {
    item {
      ...FlxFilesItemFields
    }
    errors {
      ...FlxFilesUserError
    }
  }
}

fragment FlxFilesItemFields on FlxFilesItem {
  name
  path
  isDir
  kind
  extension
  size
  modified {
    iso
    label
    relative
    timestamp
  }
  permissions
  writable
  locked
  core
  sensitive
  shared
  isLink
  itemCount
}

fragment FlxFilesUserError on FlxUserError {
  code
  message
  field
}`},lr={id:`1a3f6c38af3fdde3ce542ed5459d58bc51e2e0531923742d050c3944e43fbfc6`,name:`FlxFilesRevert`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesRevert($input: FlxFilesRevertInput!) {
  flxFilesRevert(input: $input) {
    file {
      hash
      content
      item {
        ...FlxFilesItemFields
      }
    }
    errors {
      ...FlxFilesUserError
    }
  }
}

fragment FlxFilesItemFields on FlxFilesItem {
  name
  path
  isDir
  kind
  extension
  size
  modified {
    iso
    label
    relative
    timestamp
  }
  permissions
  writable
  locked
  core
  sensitive
  shared
  isLink
  itemCount
}

fragment FlxFilesUserError on FlxUserError {
  code
  message
  field
}`},ur={id:`5356c293231db15f518a757f5eb788d353ec8f33108eb57039c272de7e36fff4`,name:`FlxFilesSearch`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesSearch($path: String!, $query: String!, $content: Boolean) {
  flxFilesSearch(path: $path, query: $query, content: $content) {
    query
    partial
    results {
      ...FlxFilesItemFields
      matches {
        line
        text
      }
    }
  }
}

fragment FlxFilesItemFields on FlxFilesItem {
  name
  path
  isDir
  kind
  extension
  size
  modified {
    iso
    label
    relative
    timestamp
  }
  permissions
  writable
  locked
  core
  sensitive
  shared
  isLink
  itemCount
}`},dr={id:`47f5421ba6b9e20badaeb414b94d527088d2bfd140e32f7a147a3c730d4c5041`,name:`FlxFilesSettingsUpdate`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesSettingsUpdate($input: FlxFilesSettingsUpdateInput!) {
  flxFilesSettingsUpdate(input: $input) {
    settings {
      ...FlxFilesSettingsFields
    }
    errors {
      ...FlxFilesUserError
    }
  }
}

fragment FlxFilesSettingsFields on FlxFilesSettings {
  trashDays
  versions
  quotaGb
  showHidden
  mcpWrite
  aiCode
  shared
}

fragment FlxFilesUserError on FlxUserError {
  code
  message
  field
}`},fr={id:`67739f018af821391785f275b9817550d051b7dad1879bcd1e491c84931c3314`,name:`FlxFilesShare`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesShare($input: FlxFilesShareInput!) {
  flxFilesShare(input: $input) {
    shared
    shortcode
    errors {
      ...FlxFilesUserError
    }
  }
}

fragment FlxFilesUserError on FlxUserError {
  code
  message
  field
}`},pr={id:`714c52700ef0d6771f581011a651a3c23a0fe8e8ba99395c93d621c7cb988b4a`,name:`FlxFilesSize`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesSize($path: String!) {
  flxFilesSize(path: $path) {
    bytes
    files
    folders
    partial
  }
}`},mr={id:`770f0507a338ab54a68921d53536c2521475117d015f2ecbae7c73c9836e5f8e`,name:`FlxFilesTrash`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesTrash($input: FlxFilesTrashInput!) {
  flxFilesTrash(input: $input) {
    trashed {
      path
      trashId
    }
    failed {
      ...FlxFilesFailureFields
    }
    errors {
      ...FlxFilesUserError
    }
  }
}

fragment FlxFilesFailureFields on FlxFilesFailure {
  path
  message
}

fragment FlxFilesUserError on FlxUserError {
  code
  message
  field
}`},hr={id:`e7695dfcf0d582a7b2aac6dff97e29d64d15b8aacebe1bb54905fc69dac327a1`,name:`FlxFilesTrashList`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesTrashList($first: Int = 200, $after: String) {
  flxFilesTrash {
    days
    items(first: $first, after: $after) {
      totalCount
      pageInfo {
        hasNextPage
        endCursor
      }
      nodes {
        id
        name
        path
        isDir
        kind
        size
        deleted {
          iso
          label
          relative
        }
        until
        user
        via
      }
    }
  }
}`},gr={id:`2f986646c02a7e0cbfc324e66a5f57e02434a3b14c8137f0586fb3800647cb98`,name:`FlxFilesTrashPurge`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesTrashPurge($input: FlxFilesTrashPurgeInput!) {
  flxFilesTrashPurge(input: $input) {
    removed
    failed {
      ...FlxFilesFailureFields
    }
    errors {
      ...FlxFilesUserError
    }
  }
}

fragment FlxFilesFailureFields on FlxFilesFailure {
  path
  message
}

fragment FlxFilesUserError on FlxUserError {
  code
  message
  field
}`},_r={id:`ae8ec6576f6d062c32245c2784087a446d828c8ed7f09e69761abbf62429ddaa`,name:`FlxFilesTrashRestore`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesTrashRestore($input: FlxFilesTrashRestoreInput!) {
  flxFilesTrashRestore(input: $input) {
    items {
      ...FlxFilesItemFields
    }
    failed {
      ...FlxFilesFailureFields
    }
    errors {
      ...FlxFilesUserError
    }
  }
}

fragment FlxFilesFailureFields on FlxFilesFailure {
  path
  message
}

fragment FlxFilesItemFields on FlxFilesItem {
  name
  path
  isDir
  kind
  extension
  size
  modified {
    iso
    label
    relative
    timestamp
  }
  permissions
  writable
  locked
  core
  sensitive
  shared
  isLink
  itemCount
}

fragment FlxFilesUserError on FlxUserError {
  code
  message
  field
}`},vr={id:`d75223e3edb904ea34d2c371549694e223391ed1969dcb5eb23fa6e8350d6775`,name:`FlxFilesUnzip`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesUnzip($input: FlxFilesUnzipInput!) {
  flxFilesUnzip(input: $input) {
    path
    fileCount
    errors {
      ...FlxFilesUserError
    }
  }
}

fragment FlxFilesUserError on FlxUserError {
  code
  message
  field
}`},yr={id:`f0d2ddd90670e48e6ecda7e06b56f5ed9687520d1a05c430b6f7d6e2269e8e5d`,name:`FlxFilesVersionContent`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesVersionContent($path: String!, $versionId: String!) {
  flxFilesVersionContent(path: $path, versionId: $versionId)
}`},br={id:`6723e081addb1e78d9fbf04474e63ac1877473e63ee8fa3e83bd70f8d13b0272`,name:`FlxFilesVersions`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesVersions($path: String!) {
  flxFilesVersions(path: $path) {
    id
    saved {
      iso
      label
      relative
    }
    size
    user
    via
  }
}`},xr={id:`723e828c659888ada86d76eaf190cb45ba9d65731e4474ab57051f8cd19db9b6`,name:`FlxFilesWrite`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesWrite($input: FlxFilesWriteInput!) {
  flxFilesWrite(input: $input) {
    file {
      hash
      item {
        ...FlxFilesItemFields
      }
    }
    errors {
      ...FlxFilesUserError
    }
  }
}

fragment FlxFilesItemFields on FlxFilesItem {
  name
  path
  isDir
  kind
  extension
  size
  modified {
    iso
    label
    relative
    timestamp
  }
  permissions
  writable
  locked
  core
  sensitive
  shared
  isLink
  itemCount
}

fragment FlxFilesUserError on FlxUserError {
  code
  message
  field
}`},Sr={id:`32203b0cbb4dd23257d8028daf389e5819361d7e3352f1ddab908a7459ba9244`,name:`FlxFilesZip`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesZip($input: FlxFilesZipInput!) {
  flxFilesZip(input: $input) {
    item {
      ...FlxFilesItemFields
    }
    errors {
      ...FlxFilesUserError
    }
  }
}

fragment FlxFilesItemFields on FlxFilesItem {
  name
  path
  isDir
  kind
  extension
  size
  modified {
    iso
    label
    relative
    timestamp
  }
  permissions
  writable
  locked
  core
  sensitive
  shared
  isLink
  itemCount
}

fragment FlxFilesUserError on FlxUserError {
  code
  message
  field
}`},Cr={id:`8233fa1a720d8d3b637b078018978496b1887a386680756d92fbd16293d47f2f`,name:`FlxHeadlessFrontendDelete`,kind:`mutation`,plugin:`flx-headless`,document:`mutation FlxHeadlessFrontendDelete($id: ID!) {
  flxHeadlessFrontendDelete(input: { id: $id }) {
    settings {
      ...FlxHeadlessSettingsFields
    }
    errors {
      code
      field
      message
    }
  }
}

fragment FlxHeadlessDeliveryFields on FlxHeadlessDelivery {
  time {
    iso
    label
  }
  frontendId
  frontendName
  event
  tags
  paths
  status
  ok
  message
  ms
}

fragment FlxHeadlessFrontendFields on FlxHeadlessFrontend {
  id
  name
  url
  origin
  languages
  enabled
  isDefault
  revalidateUrl
  secretSet
  secretHint
  previewPath
}

fragment FlxHeadlessSettingsFields on FlxHeadlessSettings {
  frontends {
    ...FlxHeadlessFrontendFields
  }
  wpFront
  corsOrigins
  graphqlUrl
  graphqlSettingsUrl
  persistedOnly
  languagesActive
  languages {
    code
    name
    isDefault
  }
  deliveries {
    ...FlxHeadlessDeliveryFields
  }
}`},wr={id:`5ecc4551a4b6abc0cce31176b093700436235251b61b4f28759c2fd460131607`,name:`FlxHeadlessFrontendSave`,kind:`mutation`,plugin:`flx-headless`,document:`mutation FlxHeadlessFrontendSave($input: FlxHeadlessFrontendSaveInput!) {
  flxHeadlessFrontendSave(input: $input) {
    frontend {
      ...FlxHeadlessFrontendFields
    }
    settings {
      ...FlxHeadlessSettingsFields
    }
    errors {
      code
      field
      message
    }
  }
}

fragment FlxHeadlessDeliveryFields on FlxHeadlessDelivery {
  time {
    iso
    label
  }
  frontendId
  frontendName
  event
  tags
  paths
  status
  ok
  message
  ms
}

fragment FlxHeadlessFrontendFields on FlxHeadlessFrontend {
  id
  name
  url
  origin
  languages
  enabled
  isDefault
  revalidateUrl
  secretSet
  secretHint
  previewPath
}

fragment FlxHeadlessSettingsFields on FlxHeadlessSettings {
  frontends {
    ...FlxHeadlessFrontendFields
  }
  wpFront
  corsOrigins
  graphqlUrl
  graphqlSettingsUrl
  persistedOnly
  languagesActive
  languages {
    code
    name
    isDefault
  }
  deliveries {
    ...FlxHeadlessDeliveryFields
  }
}`},Tr={id:`bf41d3f0d9fb782fb25fcdc9d3aa5c650feeb215b2c4821a160007d620c82330`,name:`FlxHeadlessLogClear`,kind:`mutation`,plugin:`flx-headless`,document:`mutation FlxHeadlessLogClear {
  flxHeadlessLogClear(input: {  }) {
    settings {
      ...FlxHeadlessSettingsFields
    }
    errors {
      code
      field
      message
    }
  }
}

fragment FlxHeadlessDeliveryFields on FlxHeadlessDelivery {
  time {
    iso
    label
  }
  frontendId
  frontendName
  event
  tags
  paths
  status
  ok
  message
  ms
}

fragment FlxHeadlessFrontendFields on FlxHeadlessFrontend {
  id
  name
  url
  origin
  languages
  enabled
  isDefault
  revalidateUrl
  secretSet
  secretHint
  previewPath
}

fragment FlxHeadlessSettingsFields on FlxHeadlessSettings {
  frontends {
    ...FlxHeadlessFrontendFields
  }
  wpFront
  corsOrigins
  graphqlUrl
  graphqlSettingsUrl
  persistedOnly
  languagesActive
  languages {
    code
    name
    isDefault
  }
  deliveries {
    ...FlxHeadlessDeliveryFields
  }
}`},Er={id:`60fb20bbe35b7a0b3fb9206ce9728e3a6c6aaa895df682236e39d8fc7d0d45de`,name:`FlxHeadlessRevalidateAll`,kind:`mutation`,plugin:`flx-headless`,document:`mutation FlxHeadlessRevalidateAll {
  flxHeadlessRevalidateAll(input: {  }) {
    settings {
      ...FlxHeadlessSettingsFields
    }
    errors {
      code
      field
      message
    }
  }
}

fragment FlxHeadlessDeliveryFields on FlxHeadlessDelivery {
  time {
    iso
    label
  }
  frontendId
  frontendName
  event
  tags
  paths
  status
  ok
  message
  ms
}

fragment FlxHeadlessFrontendFields on FlxHeadlessFrontend {
  id
  name
  url
  origin
  languages
  enabled
  isDefault
  revalidateUrl
  secretSet
  secretHint
  previewPath
}

fragment FlxHeadlessSettingsFields on FlxHeadlessSettings {
  frontends {
    ...FlxHeadlessFrontendFields
  }
  wpFront
  corsOrigins
  graphqlUrl
  graphqlSettingsUrl
  persistedOnly
  languagesActive
  languages {
    code
    name
    isDefault
  }
  deliveries {
    ...FlxHeadlessDeliveryFields
  }
}`},Dr={id:`c850f01c05a2db3c5f7f27a28eb483d145904e67024db4a0d21ec0ab68a55398`,name:`FlxHeadlessRevalidateTest`,kind:`mutation`,plugin:`flx-headless`,document:`mutation FlxHeadlessRevalidateTest($id: ID!) {
  flxHeadlessRevalidateTest(input: { id: $id }) {
    delivery {
      ...FlxHeadlessDeliveryFields
    }
    settings {
      ...FlxHeadlessSettingsFields
    }
    errors {
      code
      field
      message
    }
  }
}

fragment FlxHeadlessDeliveryFields on FlxHeadlessDelivery {
  time {
    iso
    label
  }
  frontendId
  frontendName
  event
  tags
  paths
  status
  ok
  message
  ms
}

fragment FlxHeadlessFrontendFields on FlxHeadlessFrontend {
  id
  name
  url
  origin
  languages
  enabled
  isDefault
  revalidateUrl
  secretSet
  secretHint
  previewPath
}

fragment FlxHeadlessSettingsFields on FlxHeadlessSettings {
  frontends {
    ...FlxHeadlessFrontendFields
  }
  wpFront
  corsOrigins
  graphqlUrl
  graphqlSettingsUrl
  persistedOnly
  languagesActive
  languages {
    code
    name
    isDefault
  }
  deliveries {
    ...FlxHeadlessDeliveryFields
  }
}`},Or={id:`9a71e23cbefadc4944a25a2724be3e1026f4fa6272749759a08f3a87982f282b`,name:`FlxHeadlessScreen`,kind:`query`,plugin:`flx-headless`,document:`query FlxHeadlessScreen {
  flxHeadlessSettings {
    ...FlxHeadlessSettingsFields
  }
}

fragment FlxHeadlessDeliveryFields on FlxHeadlessDelivery {
  time {
    iso
    label
  }
  frontendId
  frontendName
  event
  tags
  paths
  status
  ok
  message
  ms
}

fragment FlxHeadlessFrontendFields on FlxHeadlessFrontend {
  id
  name
  url
  origin
  languages
  enabled
  isDefault
  revalidateUrl
  secretSet
  secretHint
  previewPath
}

fragment FlxHeadlessSettingsFields on FlxHeadlessSettings {
  frontends {
    ...FlxHeadlessFrontendFields
  }
  wpFront
  corsOrigins
  graphqlUrl
  graphqlSettingsUrl
  persistedOnly
  languagesActive
  languages {
    code
    name
    isDefault
  }
  deliveries {
    ...FlxHeadlessDeliveryFields
  }
}`},kr={id:`5448a2583b93fb932b473fb1fec95118719f654d4fc601b499bdebaa9ac86ed0`,name:`FlxHeadlessWpFrontSet`,kind:`mutation`,plugin:`flx-headless`,document:`mutation FlxHeadlessWpFrontSet($mode: FlxHeadlessWpFront!) {
  flxHeadlessWpFrontSet(input: { mode: $mode }) {
    settings {
      ...FlxHeadlessSettingsFields
    }
    errors {
      code
      field
      message
    }
  }
}

fragment FlxHeadlessDeliveryFields on FlxHeadlessDelivery {
  time {
    iso
    label
  }
  frontendId
  frontendName
  event
  tags
  paths
  status
  ok
  message
  ms
}

fragment FlxHeadlessFrontendFields on FlxHeadlessFrontend {
  id
  name
  url
  origin
  languages
  enabled
  isDefault
  revalidateUrl
  secretSet
  secretHint
  previewPath
}

fragment FlxHeadlessSettingsFields on FlxHeadlessSettings {
  frontends {
    ...FlxHeadlessFrontendFields
  }
  wpFront
  corsOrigins
  graphqlUrl
  graphqlSettingsUrl
  persistedOnly
  languagesActive
  languages {
    code
    name
    isDefault
  }
  deliveries {
    ...FlxHeadlessDeliveryFields
  }
}`},Ar={id:`14ae18c34134e93d807ec1e3d21907a4c002555d4d327011a6cbdd05c13743ab`,name:`FlxLanguageSettingsScreen`,kind:`query`,plugin:`flx-lang`,document:`query FlxLanguageSettingsScreen {
  flxLanguageSettings {
    ...FlxLanguageSettingsFields
  }
}

fragment FlxLanguageRow on FlxLanguage {
  code
  name
  locale
  flag
  active
  default
  address
  hreflang
}

fragment FlxLanguageSettingsFields on FlxLanguageSettings {
  defaultLanguage
  languages {
    ...FlxLanguageRow
  }
  postTypes
  fallback
  suggest
  suggestTo
  homeCountries
  switcher
  switcherPlace
  texts {
    lang
    key
    value
  }
  catalog {
    code
    name
    locale
    flag
  }
  postTypeChoices {
    name
    label
    selected
  }
  textFields {
    key
    label
    textarea
    placeholder
  }
  geo {
    country
    source
  }
  aiAvailable
}`},jr={id:`0da4e1f193deec851174ea768e4699d956a94e7670b172e44dafe5bf9f788a52`,name:`FlxLanguageSettingsUpdate`,kind:`mutation`,plugin:`flx-lang`,document:`mutation FlxLanguageSettingsUpdate($input: FlxLanguageSettingsUpdateInput!) {
  flxLanguageSettingsUpdate(input: $input) {
    settings {
      ...FlxLanguageSettingsFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxLanguageRow on FlxLanguage {
  code
  name
  locale
  flag
  active
  default
  address
  hreflang
}

fragment FlxLanguageSettingsFields on FlxLanguageSettings {
  defaultLanguage
  languages {
    ...FlxLanguageRow
  }
  postTypes
  fallback
  suggest
  suggestTo
  homeCountries
  switcher
  switcherPlace
  texts {
    lang
    key
    value
  }
  catalog {
    code
    name
    locale
    flag
  }
  postTypeChoices {
    name
    label
    selected
  }
  textFields {
    key
    label
    textarea
    placeholder
  }
  geo {
    country
    source
  }
  aiAvailable
}`},Mr={id:`8475313bd287a3d2bbf784eb2da2abcae1fbc70a38f9c1d6257b9251ea9c0873`,name:`FlxRecaptchaKeysTest`,kind:`mutation`,plugin:`flx-recaptcha`,document:`mutation FlxRecaptchaKeysTest($input: FlxRecaptchaKeysTestInput!) {
  flxRecaptchaKeysTest(input: $input) {
    result {
      ok
      message
      score
    }
    settings {
      ...FlxRecaptchaSettingsFields
    }
    forms {
      ...FlxRecaptchaFormRow
    }
    status {
      ...FlxRecaptchaStatusFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxRecaptchaFormRow on FlxRecaptchaForm {
  key
  label
  description
  group
  groupLabel
  available
  enabled
  passed
  blocked
}

fragment FlxRecaptchaSettingsFields on FlxRecaptchaSettings {
  version
  siteKey
  siteKeyLocked
  secretSaved
  secretHint
  secretLocked
  threshold
  domain
  v2Theme
  v2Size
  hideBadge
  skipLoggedIn
  failOpen
  allowIps
  currentIp
  keysVerified
  disabledByConstant
  formsOverlap
}

fragment FlxRecaptchaStatusFields on FlxRecaptchaStatus {
  state
  version
  formsOn
  formsAvailable
  passed
  blocked
  lastTest {
    time {
      iso
      label
    }
    ok
    message
  }
  lastError {
    time {
      iso
      label
    }
    kind
    reason
    letPass
  }
}`},Nr={id:`9c0623f86ccc32d8df7367593176ecb135881242b39ad7dca166ac7c9ab9a98d`,name:`FlxRecaptchaScreen`,kind:`query`,plugin:`flx-recaptcha`,document:`query FlxRecaptchaScreen {
  flxRecaptchaSettings {
    ...FlxRecaptchaSettingsFields
  }
  flxRecaptchaForms {
    ...FlxRecaptchaFormRow
  }
  flxRecaptchaStatus {
    ...FlxRecaptchaStatusFields
  }
}

fragment FlxRecaptchaFormRow on FlxRecaptchaForm {
  key
  label
  description
  group
  groupLabel
  available
  enabled
  passed
  blocked
}

fragment FlxRecaptchaSettingsFields on FlxRecaptchaSettings {
  version
  siteKey
  siteKeyLocked
  secretSaved
  secretHint
  secretLocked
  threshold
  domain
  v2Theme
  v2Size
  hideBadge
  skipLoggedIn
  failOpen
  allowIps
  currentIp
  keysVerified
  disabledByConstant
  formsOverlap
}

fragment FlxRecaptchaStatusFields on FlxRecaptchaStatus {
  state
  version
  formsOn
  formsAvailable
  passed
  blocked
  lastTest {
    time {
      iso
      label
    }
    ok
    message
  }
  lastError {
    time {
      iso
      label
    }
    kind
    reason
    letPass
  }
}`},Pr={id:`2d24402e0c3acaad9aeb96bea6cb25a54ff8664cf8103f515b767c891690206e`,name:`FlxRecaptchaSettingsUpdate`,kind:`mutation`,plugin:`flx-recaptcha`,document:`mutation FlxRecaptchaSettingsUpdate($input: FlxRecaptchaSettingsUpdateInput!) {
  flxRecaptchaSettingsUpdate(input: $input) {
    settings {
      ...FlxRecaptchaSettingsFields
    }
    forms {
      ...FlxRecaptchaFormRow
    }
    status {
      ...FlxRecaptchaStatusFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxRecaptchaFormRow on FlxRecaptchaForm {
  key
  label
  description
  group
  groupLabel
  available
  enabled
  passed
  blocked
}

fragment FlxRecaptchaSettingsFields on FlxRecaptchaSettings {
  version
  siteKey
  siteKeyLocked
  secretSaved
  secretHint
  secretLocked
  threshold
  domain
  v2Theme
  v2Size
  hideBadge
  skipLoggedIn
  failOpen
  allowIps
  currentIp
  keysVerified
  disabledByConstant
  formsOverlap
}

fragment FlxRecaptchaStatusFields on FlxRecaptchaStatus {
  state
  version
  formsOn
  formsAvailable
  passed
  blocked
  lastTest {
    time {
      iso
      label
    }
    ok
    message
  }
  lastError {
    time {
      iso
      label
    }
    kind
    reason
    letPass
  }
}`},Fr={id:`21a336969a0dc41b8caaa1be0a20b9bd740359347ff57bc8cb72f70ca6254f9e`,name:`FlxRoleCreate`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleCreate($input: FlxRoleCreateInput!) {
  flxRoleCreate(input: $input) {
    role {
      ...FlxRoleRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxRoleRow on FlxRole {
  key
  label
  description
  custom
  memberCount
  capabilities
  home
}`},Ir={id:`2bd5579ac1188eadb0dff5235840b66098c68b1b0d9e5d9a86c1a07b54d3acea`,name:`FlxRoleDelete`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleDelete($input: FlxRoleDeleteInput!) {
  flxRoleDelete(input: $input) {
    deletedKey
    errors {
      code
      message
      field
    }
  }
}`},Lr={id:`af09ea3f7594b6e37e1aa272f116e57dd7079ca275a754a8107fc230ce3014df`,name:`FlxRoleMemberAdd`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleMemberAdd($input: FlxRoleMemberAddInput!) {
  flxRoleMemberAdd(input: $input) {
    member {
      ...FlxRoleMemberRow
    }
    roles {
      key
      memberCount
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxRoleMemberRow on FlxRoleMember {
  databaseId
  name
  email
  phone {
    display
    e164
  }
  editUrl
  referralLink
}`},Rr={id:`6eab4d6f1f97457a8679a9b1dd92d47dc2e01a7f41163eff439dc854213b2a7c`,name:`FlxRoleMemberRemove`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleMemberRemove($input: FlxRoleMemberRemoveInput!) {
  flxRoleMemberRemove(input: $input) {
    removedUserId
    roles {
      key
      memberCount
    }
    errors {
      code
      message
      field
    }
  }
}`},zr={id:`067ae709ce0675a0f8e8ab3ab7d21d722afa9f7449f358767be373133c98de6f`,name:`FlxRoleMembers`,kind:`query`,plugin:`flx-roles`,document:`query FlxRoleMembers($key: String!, $first: Int = 50, $after: String) {
  flxRole(key: $key) {
    key
    members(first: $first, after: $after) {
      ...FlxRoleMemberPage
    }
  }
}

fragment FlxRoleMemberPage on FlxRoleMemberConnection {
  nodes {
    ...FlxRoleMemberRow
  }
  pageInfo {
    hasNextPage
    endCursor
  }
  totalCount
}

fragment FlxRoleMemberRow on FlxRoleMember {
  databaseId
  name
  email
  phone {
    display
    e164
  }
  editUrl
  referralLink
}`},Br={id:`20e6cdbb88f7639a8749e74cf75ef58a4ab704143f0cf0fa3474dbb2366cb1d0`,name:`FlxRoleReset`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleReset($input: FlxRoleResetInput!) {
  flxRoleReset(input: $input) {
    role {
      ...FlxRoleRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxRoleRow on FlxRole {
  key
  label
  description
  custom
  memberCount
  capabilities
  home
}`},Vr={id:`0a4da13faad85546b92fab5b45716dab5b06e87791475a9dfaa4664888375658`,name:`FlxRolesSave`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRolesSave($input: FlxRolesSaveInput!) {
  flxRolesSave(input: $input) {
    roles {
      ...FlxRoleRow
    }
    config {
      ...FlxRolesConfigFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxRoleRow on FlxRole {
  key
  label
  description
  custom
  memberCount
  capabilities
  home
}

fragment FlxRolesConfigFields on FlxRolesConfig {
  referralDays
  newUserUrl
  permissionsUrl
  homeChoices {
    key
    label
  }
}`},Hr={id:`2c22565866bcebf0aaed510b75bec73444fc8257c8d2b327873e9c2963c59030`,name:`FlxRolesScreen`,kind:`query`,plugin:`flx-roles`,document:`query FlxRolesScreen($members: Int = 50) {
  flxRoles {
    ...FlxRoleRow
    members(first: $members) {
      ...FlxRoleMemberPage
    }
  }
  flxRoleCapabilityGroups {
    key
    label
    plugin
    active
    capabilities {
      key
      label
      locked
      broad
      pending
    }
  }
  flxRolesConfig {
    ...FlxRolesConfigFields
  }
}

fragment FlxRoleMemberPage on FlxRoleMemberConnection {
  nodes {
    ...FlxRoleMemberRow
  }
  pageInfo {
    hasNextPage
    endCursor
  }
  totalCount
}

fragment FlxRoleMemberRow on FlxRoleMember {
  databaseId
  name
  email
  phone {
    display
    e164
  }
  editUrl
  referralLink
}

fragment FlxRoleRow on FlxRole {
  key
  label
  description
  custom
  memberCount
  capabilities
  home
}

fragment FlxRolesConfigFields on FlxRolesConfig {
  referralDays
  newUserUrl
  permissionsUrl
  homeChoices {
    key
    label
  }
}`},Ur={id:`24ef9e0380385f71b2af51a8163f15a8362c7a02a3fee076051efdd289a4d7a4`,name:`FlxSeoAuditRun`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoAuditRun($input: FlxSeoAuditRunInput!) {
  flxSeoAuditRun(input: $input) {
    audit {
      ...FlxSeoAuditFields
    }
    history {
      ...FlxSeoHistoryFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSeoAuditFields on FlxSeoAudit {
  counts {
    ...FlxSeoCountsFields
  }
  local {
    time {
      iso
      label
      relative
    }
    url
    durationMs
    httpStatus
  }
  pageSpeed {
    strategy
    time {
      iso
      label
      relative
    }
    url
    lighthouse
    scores {
      category
      label
      score
      passed
      total
    }
    metrics {
      key
      label
      value
      score
    }
  }
  agentic {
    id
    label
    passed
  }
  agenticPassed
  auditUrl
  mcpServerOn
  llmsUrl
  findings {
    id
    title
    priority
    priorityLabel
    category
    categoryLabel
    also
    detail
    fix
    owner
    ownerLabel
    items
    sources
    fixId
    fixLabel
  }
}

fragment FlxSeoCountsFields on FlxSeoCounts {
  critical
  high
  medium
  low
}

fragment FlxSeoHistoryFields on FlxSeoHistory {
  total
  running
  errors
  next {
    iso
    label
  }
  entries {
    time {
      iso
      label
      relative
    }
    trigger
    triggerLabel
    url
    counts {
      ...FlxSeoCountsFields
    }
    scores {
      strategy
      category
      label
      score
    }
    metrics {
      strategy
      fcp
      lcp
      tbt
      cls
      si
    }
    agentic
    added {
      id
      title
    }
    resolved {
      id
      title
    }
  }
}`},Wr={id:`25585531a77819b9e9b681a85e767cb408d01dcab0ecb5e5e3717d30b20fb89e`,name:`FlxSeoAuditScreen`,kind:`query`,plugin:`flx-seo`,document:`query FlxSeoAuditScreen($history: Int = 30) {
  flxSeoAudit {
    ...FlxSeoAuditFields
  }
  flxSeoAuditHistory(limit: $history) {
    ...FlxSeoHistoryFields
  }
}

fragment FlxSeoAuditFields on FlxSeoAudit {
  counts {
    ...FlxSeoCountsFields
  }
  local {
    time {
      iso
      label
      relative
    }
    url
    durationMs
    httpStatus
  }
  pageSpeed {
    strategy
    time {
      iso
      label
      relative
    }
    url
    lighthouse
    scores {
      category
      label
      score
      passed
      total
    }
    metrics {
      key
      label
      value
      score
    }
  }
  agentic {
    id
    label
    passed
  }
  agenticPassed
  auditUrl
  mcpServerOn
  llmsUrl
  findings {
    id
    title
    priority
    priorityLabel
    category
    categoryLabel
    also
    detail
    fix
    owner
    ownerLabel
    items
    sources
    fixId
    fixLabel
  }
}

fragment FlxSeoCountsFields on FlxSeoCounts {
  critical
  high
  medium
  low
}

fragment FlxSeoHistoryFields on FlxSeoHistory {
  total
  running
  errors
  next {
    iso
    label
  }
  entries {
    time {
      iso
      label
      relative
    }
    trigger
    triggerLabel
    url
    counts {
      ...FlxSeoCountsFields
    }
    scores {
      strategy
      category
      label
      score
    }
    metrics {
      strategy
      fcp
      lcp
      tbt
      cls
      si
    }
    agentic
    added {
      id
      title
    }
    resolved {
      id
      title
    }
  }
}`},Gr={id:`97d49670dd4df105502f5013764c377eaadec82d4f58ba3d9d0f8d3a00566bd2`,name:`FlxSeoContentCheck`,kind:`query`,plugin:`flx-seo`,document:`query FlxSeoContentCheck($limit: Int = 300) {
  flxSeoContentCheck(limit: $limit) {
    checked
    total
    items {
      databaseId
      title
      postType
      url
      editUrl
      issues {
        level
        code
        message
      }
    }
  }
}`},Kr={id:`aca5c530650ff0a558a94968a069e5d1d183315910cf69556396462fb63d557a`,name:`FlxSeoFixApply`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoFixApply($input: FlxSeoFixApplyInput!) {
  flxSeoFixApply(input: $input) {
    audit {
      ...FlxSeoAuditFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSeoAuditFields on FlxSeoAudit {
  counts {
    ...FlxSeoCountsFields
  }
  local {
    time {
      iso
      label
      relative
    }
    url
    durationMs
    httpStatus
  }
  pageSpeed {
    strategy
    time {
      iso
      label
      relative
    }
    url
    lighthouse
    scores {
      category
      label
      score
      passed
      total
    }
    metrics {
      key
      label
      value
      score
    }
  }
  agentic {
    id
    label
    passed
  }
  agenticPassed
  auditUrl
  mcpServerOn
  llmsUrl
  findings {
    id
    title
    priority
    priorityLabel
    category
    categoryLabel
    also
    detail
    fix
    owner
    ownerLabel
    items
    sources
    fixId
    fixLabel
  }
}

fragment FlxSeoCountsFields on FlxSeoCounts {
  critical
  high
  medium
  low
}`},qr={id:`fa7f2e42636c7fd69983e7637be5625f01102cc336c76b7e751dfc6b25299aea`,name:`FlxSeoGuide`,kind:`query`,plugin:`flx-seo`,document:`query FlxSeoGuide($lang: FlxSeoGuideLang) {
  flxSeoGuide(lang: $lang) {
    lang
    markdown
    sections
  }
}`},Jr={id:`84dfec5a3b859e284998fc8124abae04e2fdd4419dac91ab6cb5971882673a9d`,name:`FlxSeoIndexNowSubmit`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoIndexNowSubmit($input: FlxSeoIndexNowSubmitInput!) {
  flxSeoIndexNowSubmit(input: $input) {
    submitted
    setup {
      ...FlxSeoSetupFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSeoSetupFields on FlxSeoSetup {
  otherPlugin
  aiReady
  blogName
  homeUrl
  sitemapUrl
  logoDefault
  imageDefault
  homeTitle
  homeDesc
  sitekit
  sitePublic
  indexnowKey
  indexnowUrl
  indexnowLast
  indexnowReady
  psiKeySaved
  adminEmail
  guideSections
  engines {
    key
    field
    name
    url
    link
    placeholder
    help
  }
}`},Yr={id:`59e6780cadb6bc0d4dcecc09e482056c52aeb00879b5b1c00fa35049f815dea2`,name:`FlxSeoPsiKeySave`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoPsiKeySave($input: FlxSeoPsiKeySaveInput!) {
  flxSeoPsiKeySave(input: $input) {
    saved
    errors {
      code
      message
      field
    }
  }
}`},Xr={id:`ffd79b34482e7fd6c534446f2a81d044658a4eea3fafffa2d65eb14c8b4101fd`,name:`FlxSeoSettingsSave`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoSettingsSave($flxseo: FlxSettingsFlxseoInput!) {
  flxSettingsUpdate(input: { flxseo: $flxseo }) {
    settings {
      flxseo {
        ...FlxSeoSettingsFields
      }
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSeoSettingsFields on FlxSettingsFlxseo {
  enabled
  siteName
  altName
  sep
  homeTitle
  homeDesc
  ogImage
  verifyGoogle
  verifyBing
  verifyYandex
  verifyNaver
  verifySeznam
  verifyBaidu
  indexnow
  noindexShop
  noindexSearch
  noindexAuthor
  noindexTags
  autoAlt
  orgName
  orgType
  founding
  logo
  street
  locality
  postal
  country
  phone
  email
  sameAs
  twitter
  knowsAbout
  areaServed
  donateLabel
  donateUrl
  llms
  llmsIntro
  webmcp
  markdown
  contentSignal
  csSearch
  csAiInput
  csAiTrain
  hdrFrame
  hdrCoop
  hdrCsp
  hstsSub
  hstsPreload
  auditFreq
  auditDay
  auditHour
  auditPsi
  auditMail
  auditMailTo
}`},Zr={id:`86bc810d43babcb5cefcbb0d86511dc9661c0d5431662164e77220a7f6f5e3b8`,name:`FlxSeoSettingsScreen`,kind:`query`,plugin:`flx-seo`,document:`query FlxSeoSettingsScreen {
  flxSettings {
    flxseo {
      ...FlxSeoSettingsFields
    }
  }
  flxSeoSetup {
    ...FlxSeoSetupFields
  }
}

fragment FlxSeoSettingsFields on FlxSettingsFlxseo {
  enabled
  siteName
  altName
  sep
  homeTitle
  homeDesc
  ogImage
  verifyGoogle
  verifyBing
  verifyYandex
  verifyNaver
  verifySeznam
  verifyBaidu
  indexnow
  noindexShop
  noindexSearch
  noindexAuthor
  noindexTags
  autoAlt
  orgName
  orgType
  founding
  logo
  street
  locality
  postal
  country
  phone
  email
  sameAs
  twitter
  knowsAbout
  areaServed
  donateLabel
  donateUrl
  llms
  llmsIntro
  webmcp
  markdown
  contentSignal
  csSearch
  csAiInput
  csAiTrain
  hdrFrame
  hdrCoop
  hdrCsp
  hstsSub
  hstsPreload
  auditFreq
  auditDay
  auditHour
  auditPsi
  auditMail
  auditMailTo
}

fragment FlxSeoSetupFields on FlxSeoSetup {
  otherPlugin
  aiReady
  blogName
  homeUrl
  sitemapUrl
  logoDefault
  imageDefault
  homeTitle
  homeDesc
  sitekit
  sitePublic
  indexnowKey
  indexnowUrl
  indexnowLast
  indexnowReady
  psiKeySaved
  adminEmail
  guideSections
  engines {
    key
    field
    name
    url
    link
    placeholder
    help
  }
}`},Qr={id:`b8f91c52dab2869ea091bb25833ba93b1b5ba0eb62b369c3cff557466b3478ba`,name:`FlxSetupAutoUpdateSet`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupAutoUpdateSet($input: FlxSetupAutoUpdateSetInput!) {
  flxSetupAutoUpdateSet(input: $input) {
    on
    label
    errors {
      ...FlxSetupErrors
    }
  }
}

fragment FlxSetupErrors on FlxUserError {
  code
  message
  field
}`},$r={id:`327a745eaebc60ab14f76d453be58e38f0399b212c0c15b87d2dbd1488a731c4`,name:`FlxSetupBackupsDelete`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupBackupsDelete($input: FlxSetupBackupsDeleteInput!) {
  flxSetupBackupsDelete(input: $input) {
    deleted
    retention {
      ...FlxSetupRetentionFields
    }
    errors {
      ...FlxSetupErrors
    }
  }
}

fragment FlxSetupErrors on FlxUserError {
  code
  message
  field
}

fragment FlxSetupRetentionFields on FlxSetupRetention {
  available
  days
  choices
  count
  size
  settingsUrl
}`},ei={id:`5c3dea411ce3bbfc57b8945d04dd11c857a00fe726bc27e95ea52175cd11ac3f`,name:`FlxSetupCheck`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupCheck {
  flxSetupCheck(input: {  }) {
    ok
    pendingCount
    checkedLabel
    summary
    errors {
      ...FlxSetupErrors
    }
  }
}

fragment FlxSetupErrors on FlxUserError {
  code
  message
  field
}`},ti={id:`fea5c46697571e499c36f813537e124a23f33e2a3b616d09264af5ef3fec1d26`,name:`FlxSetupHistory`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupHistory($page: Int) {
  flxSetupHistory(page: $page) {
    rows {
      time {
        iso
        label
        relative
      }
      name
      action
      success
      message
      by
      backup {
        id
        deleted
        size
        locked
        keepUntil {
          label
        }
        deletable
        selectable
        url
      }
    }
    total
    pages
    page
    canDelete
    backupPage
    retention {
      ...FlxSetupRetentionFields
    }
  }
}

fragment FlxSetupRetentionFields on FlxSetupRetention {
  available
  days
  choices
  count
  size
  settingsUrl
}`},ni={id:`6c9941eefcb59ba827d304f3336844f4a348ecb3f1b549a96d805b1c48412b6b`,name:`FlxSetupManage`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupManage($input: FlxSetupManageInput!) {
  flxSetupManage(input: $input) {
    done
    skipped
    failed
    summary
    errors {
      ...FlxSetupErrors
    }
  }
}

fragment FlxSetupErrors on FlxUserError {
  code
  message
  field
}`},ri={id:`1641f892cadf8664b2298b4af2847db998d4c838826ebc0f08572a5ea648e637`,name:`FlxSetupOverview`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupOverview($search: String) {
  flxSetupOverview {
    connection {
      ...FlxSetupConnectionFields
    }
    tabs(search: $search) {
      tab
      label
      count
      updates
    }
    updateCount
    autoCheck
    failingSources {
      name
      error
    }
    backup {
      ready
      installed
      activatable
      installable
    }
    backupUrl
    settingsUrl
    wordpressUpdatesUrl
    canManageSources
  }
}

fragment FlxSetupConnectionFields on FlxSetupConnection {
  state
  title
  message
  host
  site
  checked {
    iso
    label
    relative
  }
  checkedLabel
  backingOff
  hasKey
  keyFromConfig
  packageCount
  updateCount
}`},ii={id:`e8e1935e414a5f61d5841b22119e7874da774b8e7905d19e76333469f34eff77`,name:`FlxSetupPackages`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupPackages($tab: FlxSetupPackageTab, $search: String, $onlyUpdates: Boolean, $first: Int = 200, $after: String) {
  flxSetupPackages(
    tab: $tab
    search: $search
    onlyUpdates: $onlyUpdates
    first: $first
    after: $after
  ) {
    nodes {
      ...FlxSetupPackageRow
    }
    pageInfo {
      hasNextPage
      endCursor
    }
    totalCount
  }
}

fragment FlxSetupPackageRow on FlxSetupPackage {
  key
  group
  kind
  id
  name
  subtitle
  author
  state
  installed
  latest
  released {
    iso
    label
    relative
  }
  active
  iconUrl
  tags {
    label
    kind
  }
  category
  sourceName
  showSource
  sourceCount
  sourceNote
  requires {
    slug
    name
    state
    flux
  }
  autoUpdate {
    mode
    type
    id
    canChange
  }
  canUpdate
  canInstall
  canManage
  canChangeVersion
  versions {
    version
    older
    released {
      iso
      label
    }
  }
  description
  website
  images
  info {
    label
    value
  }
}`},ai={id:`91629093982ae065468fb3f63497bc77ac4c8950e085938617acebb155eb692e`,name:`FlxSetupPlan`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupPlan($slugs: [String!], $wpKeys: [String!], $all: Boolean, $activate: Boolean) {
  flxSetupPlan(slugs: $slugs, wpKeys: $wpKeys, all: $all, activate: $activate) {
    kind
    slug
    id
    name
    why
    version
  }
}`},oi={id:`20b2e2929728ec932fd886ce76780a3b35a35fc0045391ff82428916aa37984b`,name:`FlxSetupRetentionSave`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupRetentionSave($input: FlxSetupRetentionSaveInput!) {
  flxSetupRetentionSave(input: $input) {
    deleted
    retention {
      ...FlxSetupRetentionFields
    }
    errors {
      ...FlxSetupErrors
    }
  }
}

fragment FlxSetupErrors on FlxUserError {
  code
  message
  field
}

fragment FlxSetupRetentionFields on FlxSetupRetention {
  available
  days
  choices
  count
  size
  settingsUrl
}`},si={id:`33e46578e9a32d1bff6a6ad7f8785580eb6208cbc4b806543fc964bf1a3048f7`,name:`FlxSetupSettingsSave`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupSettingsSave($input: FlxSetupSettingsSaveInput!) {
  flxSetupSettingsSave(input: $input) {
    settings {
      ...FlxSetupSettingsFields
    }
    connection {
      ...FlxSetupConnectionFields
    }
    sources {
      ...FlxSetupSourceFields
    }
    errors {
      ...FlxSetupErrors
    }
  }
}

fragment FlxSetupConnectionFields on FlxSetupConnection {
  state
  title
  message
  host
  site
  checked {
    iso
    label
    relative
  }
  checkedLabel
  backingOff
  hasKey
  keyFromConfig
  packageCount
  updateCount
}

fragment FlxSetupErrors on FlxUserError {
  code
  message
  field
}

fragment FlxSetupSettingsFields on FlxSetupSettings {
  server
  defaultServer
  serverFromConfig
  effectiveServer
  hasKey
  keyFromConfig
  auto
  health
  usageAvailable
  usage
  legal {
    kvkk
    privacy
    terms
  }
}

fragment FlxSetupSourceFields on FlxSetupSource {
  id
  name
  url
  type
  main
  enabled
  hasKey
  state
  message
  packageCount
  canMoveUp
}`},ci={id:`d8622d9c1c248a1d4c9dbc1667856303277e0b4f7b03ae820895203ddc6aa9ee`,name:`FlxSetupSettingsScreen`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupSettingsScreen {
  flxSetupSettings {
    ...FlxSetupSettingsFields
  }
  flxSetupConnection {
    ...FlxSetupConnectionFields
  }
  flxSetupSources {
    ...FlxSetupSourceFields
  }
  flxSetupGraphqlStatus {
    state
    version
    phpVersion
    phpMin
    phpOk
    actionUrl
    actionLabel
  }
}

fragment FlxSetupConnectionFields on FlxSetupConnection {
  state
  title
  message
  host
  site
  checked {
    iso
    label
    relative
  }
  checkedLabel
  backingOff
  hasKey
  keyFromConfig
  packageCount
  updateCount
}

fragment FlxSetupSettingsFields on FlxSetupSettings {
  server
  defaultServer
  serverFromConfig
  effectiveServer
  hasKey
  keyFromConfig
  auto
  health
  usageAvailable
  usage
  legal {
    kvkk
    privacy
    terms
  }
}

fragment FlxSetupSourceFields on FlxSetupSource {
  id
  name
  url
  type
  main
  enabled
  hasKey
  state
  message
  packageCount
  canMoveUp
}`},li={id:`f8f67e0723c3045d9ba05b6454009c8f931f5f3a6a25a5680ccb8e478d3b2f3e`,name:`FlxSetupSourceAdd`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupSourceAdd($input: FlxSetupSourceAddInput!) {
  flxSetupSourceAdd(input: $input) {
    sources {
      ...FlxSetupSourceFields
    }
    errors {
      ...FlxSetupErrors
    }
  }
}

fragment FlxSetupErrors on FlxUserError {
  code
  message
  field
}

fragment FlxSetupSourceFields on FlxSetupSource {
  id
  name
  url
  type
  main
  enabled
  hasKey
  state
  message
  packageCount
  canMoveUp
}`},ui={id:`758bf6b4e9d2b43b0a4a2613eb688c94c8b73f0dd94f8e6efafd99fb74d5aa06`,name:`FlxSetupSourceChange`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupSourceChange($input: FlxSetupSourceChangeInput!) {
  flxSetupSourceChange(input: $input) {
    sources {
      ...FlxSetupSourceFields
    }
    errors {
      ...FlxSetupErrors
    }
  }
}

fragment FlxSetupErrors on FlxUserError {
  code
  message
  field
}

fragment FlxSetupSourceFields on FlxSetupSource {
  id
  name
  url
  type
  main
  enabled
  hasKey
  state
  message
  packageCount
  canMoveUp
}`},di={id:`562e895123bcf4698f2e0b64d5f322aeecdf71358e7d66ef3677e3ed3e190a89`,name:`FlxSetupStepRun`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupStepRun($input: FlxSetupStepRunInput!) {
  flxSetupStepRun(input: $input) {
    ok
    lines {
      ok
      text
    }
    pendingCount
    package {
      ...FlxSetupPackageRow
    }
    errors {
      ...FlxSetupErrors
    }
  }
}

fragment FlxSetupErrors on FlxUserError {
  code
  message
  field
}

fragment FlxSetupPackageRow on FlxSetupPackage {
  key
  group
  kind
  id
  name
  subtitle
  author
  state
  installed
  latest
  released {
    iso
    label
    relative
  }
  active
  iconUrl
  tags {
    label
    kind
  }
  category
  sourceName
  showSource
  sourceCount
  sourceNote
  requires {
    slug
    name
    state
    flux
  }
  autoUpdate {
    mode
    type
    id
    canChange
  }
  canUpdate
  canInstall
  canManage
  canChangeVersion
  versions {
    version
    older
    released {
      iso
      label
    }
  }
  description
  website
  images
  info {
    label
    value
  }
}`},fi={id:`ac5bddd734508bac19a20d5d020bdbd2ec57e3dd9b351b65d12e009a3cf72068`,name:`FlxSocialAccountDelete`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialAccountDelete($input: FlxSocialAccountDeleteInput!) {
  flxSocialAccountDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},pi={id:`9bec0a5cfe1a3cb3321b0913feb2586f8948d0fb81cdfa85c46c0d6c1d380492`,name:`FlxSocialAccountRefresh`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialAccountRefresh($input: FlxSocialAccountRefreshInput!) {
  flxSocialAccountRefresh(input: $input) {
    account {
      ...FlxSocialAccountRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSocialAccountRow on FlxSocialAccount {
  id
  databaseId
  provider
  via
  network
  name
  username
  avatar
  status
  expires {
    timestamp
  }
  kind
  premium
  lastError
  boards {
    id
    name
  }
  groups {
    id
    name
  }
  playlists {
    id
    name
  }
  caps {
    ...FlxSocialCapsFields
  }
}

fragment FlxSocialCapsFields on FlxSocialCaps {
  text
  image
  video
  gif
  maxText
  maxImages
  maxVideos
  mix
  needsMedia
  edit
  delete
  comment
  metrics
  options
}`},mi={id:`fb8b8e12765fec1841e78ad598cbabafad6ad06e26ae41571b78081081bb35d0`,name:`FlxSocialAccountReorder`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialAccountReorder($input: FlxSocialAccountReorderInput!) {
  flxSocialAccountReorder(input: $input) {
    accounts {
      ...FlxSocialAccountRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSocialAccountRow on FlxSocialAccount {
  id
  databaseId
  provider
  via
  network
  name
  username
  avatar
  status
  expires {
    timestamp
  }
  kind
  premium
  lastError
  boards {
    id
    name
  }
  groups {
    id
    name
  }
  playlists {
    id
    name
  }
  caps {
    ...FlxSocialCapsFields
  }
}

fragment FlxSocialCapsFields on FlxSocialCaps {
  text
  image
  video
  gif
  maxText
  maxImages
  maxVideos
  mix
  needsMedia
  edit
  delete
  comment
  metrics
  options
}`},hi={id:`ddde3fb7af5fc653690c8d162de6a4a70f367d608c25e10e234f43d2b2eeeecb`,name:`FlxSocialAccountUpdate`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialAccountUpdate($input: FlxSocialAccountUpdateInput!) {
  flxSocialAccountUpdate(input: $input) {
    account {
      ...FlxSocialAccountRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSocialAccountRow on FlxSocialAccount {
  id
  databaseId
  provider
  via
  network
  name
  username
  avatar
  status
  expires {
    timestamp
  }
  kind
  premium
  lastError
  boards {
    id
    name
  }
  groups {
    id
    name
  }
  playlists {
    id
    name
  }
  caps {
    ...FlxSocialCapsFields
  }
}

fragment FlxSocialCapsFields on FlxSocialCaps {
  text
  image
  video
  gif
  maxText
  maxImages
  maxVideos
  mix
  needsMedia
  edit
  delete
  comment
  metrics
  options
}`},gi={id:`3d420b1c8992d44b15a62c551e463f57a3ff6e9cb8a480d28e5ba6d956724b5a`,name:`FlxSocialAiWrite`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialAiWrite($input: FlxSocialAiWriteInput!) {
  flxSocialAiWrite(input: $input) {
    text
    texts {
      provider
      text
    }
    errors {
      code
      message
      field
    }
  }
}`},_i={id:`d152736fe8890c423eaa3d4481b02a64c2827c46b46be9b2f4111b313b3411e3`,name:`FlxSocialAppUpdate`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialAppUpdate($input: FlxSocialAppUpdateInput!) {
  flxSocialAppUpdate(input: $input) {
    apps {
      id
      ready
      fields {
        key
        label
        secret
        value
        isSet
        placeholder
        optional
      }
    }
    errors {
      code
      message
      field
    }
  }
}`},vi={id:`777e5e347a4e283420534a0c3123fcc967ba15ca3e8799e50a3dfd4105fdde68`,name:`FlxSocialBoot`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialBoot {
  flxSocialConfig {
    userId
    userName
    canPublish
    canManage
    canUpload
    ai
    studio
    approval
    signature
    xTier
    timezone
    tzOffset
    weekStart
    version
    networks {
      id
      label
      color
      caps {
        ...FlxSocialCapsFields
      }
    }
  }
  flxSocialAccounts {
    ...FlxSocialAccountRow
  }
  flxSocialCounts {
    pending
    scheduled
    failed
    due
  }
}

fragment FlxSocialAccountRow on FlxSocialAccount {
  id
  databaseId
  provider
  via
  network
  name
  username
  avatar
  status
  expires {
    timestamp
  }
  kind
  premium
  lastError
  boards {
    id
    name
  }
  groups {
    id
    name
  }
  playlists {
    id
    name
  }
  caps {
    ...FlxSocialCapsFields
  }
}

fragment FlxSocialCapsFields on FlxSocialCaps {
  text
  image
  video
  gif
  maxText
  maxImages
  maxVideos
  mix
  needsMedia
  edit
  delete
  comment
  metrics
  options
}`},yi={id:`7a97a59e91842fa3a8db49e219db99e0cc8df12bccaf04637a5cb01abd82b25f`,name:`FlxSocialConnectUrl`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialConnectUrl($for: String!) {
  flxSocialConnectUrl(for: $for)
}`},bi={id:`cefaf931508b3dfca6c7addc0f5c6e7e55091746577f16dcb1de6270fa7606ce`,name:`FlxSocialCounts`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialCounts {
  flxSocialCounts {
    pending
    scheduled
    failed
    due
  }
}`},xi={id:`05849f5c15939169ff6d203b2ef91e4c8467f528073099294c6c4799af4422fe`,name:`FlxSocialLog`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialLog {
  flxSocialLog {
    databaseId
    time {
      timestamp
    }
    level
    accountId
    message
  }
}`},Si={id:`ef5f39879b17171c3946b6fe7f72b3ec1a072e805cecc6ab5ead805f804ffd5d`,name:`FlxSocialMedia`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialMedia($ids: [Int!], $search: String, $page: Int) {
  flxSocialMedia(ids: $ids, search: $search, page: $page) {
    pages
    items {
      ...FlxSocialMediaRow
    }
  }
}

fragment FlxSocialMediaRow on FlxSocialMedia {
  databaseId
  url
  kind
  mime
  bytes
  width
  height
  duration
  thumb
  name
  alt
}`},Ci={id:`65c5355402f955e3c229ad9d455e6c81023d21f910725625c69d71b1676e95c6`,name:`FlxSocialMediaAdd`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialMediaAdd($input: FlxSocialMediaAddInput!) {
  flxSocialMediaAdd(input: $input) {
    media {
      ...FlxSocialMediaRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSocialMediaRow on FlxSocialMedia {
  databaseId
  url
  kind
  mime
  bytes
  width
  height
  duration
  thumb
  name
  alt
}`},wi={id:`6be8bb6227b0ea3f5fb3a6b4cf402977622023b1544fde917e0bd16c72506afd`,name:`FlxSocialMediaImport`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialMediaImport($input: FlxSocialMediaImportInput!) {
  flxSocialMediaImport(input: $input) {
    media {
      ...FlxSocialMediaRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSocialMediaRow on FlxSocialMedia {
  databaseId
  url
  kind
  mime
  bytes
  width
  height
  duration
  thumb
  name
  alt
}`},Ti={id:`17e002c255a2a86aa0d738a5603ec081a9c987125aa1ac32b6cbcd4ae376faff`,name:`FlxSocialPost`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialPost($id: ID!) {
  flxSocialPost(id: $id) {
    ...FlxSocialPostRow
  }
}

fragment FlxSocialCapsFields on FlxSocialCaps {
  text
  image
  video
  gif
  maxText
  maxImages
  maxVideos
  mix
  needsMedia
  edit
  delete
  comment
  metrics
  options
}

fragment FlxSocialMediaRow on FlxSocialMedia {
  databaseId
  url
  kind
  mime
  bytes
  width
  height
  duration
  thumb
  name
  alt
}

fragment FlxSocialOptionsFields on FlxSocialOptions {
  title
  link
  board
  privacy
  igType
  flxCategory
  playlist
  tiktokPrivacy
  gbpTopic
  gbpCta
  gbpCtaUrl
  waKind
  waTo
  firstComment
  liVisibility
  alt
  thumbUrl
  replySettings
  eventTitle
  eventStart
  eventEnd
  offerCode
  madeForKids
  notify
  shorts
  disableComment
  disableDuet
  disableStitch
  aiGenerated
  brandContent
  brandOrganic
  shareToFeed
  autoMusic
  tags
}

fragment FlxSocialPostRow on FlxSocialPost {
  id
  databaseId
  status
  title
  content
  tags
  repeatDays
  source
  note
  author
  authorId
  approvedBy
  media {
    ...FlxSocialMediaRow
  }
  scheduledAt {
    timestamp
    label
  }
  publishedAt {
    timestamp
    label
  }
  created {
    timestamp
  }
  updated {
    timestamp
  }
  targets {
    ...FlxSocialTargetRow
  }
}

fragment FlxSocialTargetRow on FlxSocialTarget {
  id
  databaseId
  accountId
  provider
  account
  avatar
  status
  remoteUrl
  error
  publishedAt {
    timestamp
    label
  }
  metrics {
    views
    impressions
    reach
    clicks
    likes
    comments
    shares
    saves
  }
  caps {
    ...FlxSocialCapsFields
  }
  content
  media {
    ...FlxSocialMediaRow
  }
  options {
    ...FlxSocialOptionsFields
  }
}`},Ei={id:`bad63e76be4a72da349259fd37da2b216637760e2043864da9ba72b10c722535`,name:`FlxSocialPostAct`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialPostAct($input: FlxSocialPostActInput!) {
  flxSocialPostAct(input: $input) {
    post {
      ...FlxSocialPostRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSocialCapsFields on FlxSocialCaps {
  text
  image
  video
  gif
  maxText
  maxImages
  maxVideos
  mix
  needsMedia
  edit
  delete
  comment
  metrics
  options
}

fragment FlxSocialMediaRow on FlxSocialMedia {
  databaseId
  url
  kind
  mime
  bytes
  width
  height
  duration
  thumb
  name
  alt
}

fragment FlxSocialOptionsFields on FlxSocialOptions {
  title
  link
  board
  privacy
  igType
  flxCategory
  playlist
  tiktokPrivacy
  gbpTopic
  gbpCta
  gbpCtaUrl
  waKind
  waTo
  firstComment
  liVisibility
  alt
  thumbUrl
  replySettings
  eventTitle
  eventStart
  eventEnd
  offerCode
  madeForKids
  notify
  shorts
  disableComment
  disableDuet
  disableStitch
  aiGenerated
  brandContent
  brandOrganic
  shareToFeed
  autoMusic
  tags
}

fragment FlxSocialPostRow on FlxSocialPost {
  id
  databaseId
  status
  title
  content
  tags
  repeatDays
  source
  note
  author
  authorId
  approvedBy
  media {
    ...FlxSocialMediaRow
  }
  scheduledAt {
    timestamp
    label
  }
  publishedAt {
    timestamp
    label
  }
  created {
    timestamp
  }
  updated {
    timestamp
  }
  targets {
    ...FlxSocialTargetRow
  }
}

fragment FlxSocialTargetRow on FlxSocialTarget {
  id
  databaseId
  accountId
  provider
  account
  avatar
  status
  remoteUrl
  error
  publishedAt {
    timestamp
    label
  }
  metrics {
    views
    impressions
    reach
    clicks
    likes
    comments
    shares
    saves
  }
  caps {
    ...FlxSocialCapsFields
  }
  content
  media {
    ...FlxSocialMediaRow
  }
  options {
    ...FlxSocialOptionsFields
  }
}`},Di={id:`9198337975631125c80b4d3144ff8c95c87f78bcced0a1239a914dfec0fe9053`,name:`FlxSocialPostBulkAct`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialPostBulkAct($input: FlxSocialPostBulkActInput!) {
  flxSocialPostBulkAct(input: $input) {
    done
    issues
    errors {
      code
      message
      field
    }
  }
}`},Oi={id:`6fbb3d188ae4f3ac1afb8bdb803de5afa89ad9d81f4597a97c0c5bf752c43cfc`,name:`FlxSocialPostCreate`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialPostCreate($input: FlxSocialPostCreateInput!) {
  flxSocialPostCreate(input: $input) {
    post {
      ...FlxSocialPostRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSocialCapsFields on FlxSocialCaps {
  text
  image
  video
  gif
  maxText
  maxImages
  maxVideos
  mix
  needsMedia
  edit
  delete
  comment
  metrics
  options
}

fragment FlxSocialMediaRow on FlxSocialMedia {
  databaseId
  url
  kind
  mime
  bytes
  width
  height
  duration
  thumb
  name
  alt
}

fragment FlxSocialOptionsFields on FlxSocialOptions {
  title
  link
  board
  privacy
  igType
  flxCategory
  playlist
  tiktokPrivacy
  gbpTopic
  gbpCta
  gbpCtaUrl
  waKind
  waTo
  firstComment
  liVisibility
  alt
  thumbUrl
  replySettings
  eventTitle
  eventStart
  eventEnd
  offerCode
  madeForKids
  notify
  shorts
  disableComment
  disableDuet
  disableStitch
  aiGenerated
  brandContent
  brandOrganic
  shareToFeed
  autoMusic
  tags
}

fragment FlxSocialPostRow on FlxSocialPost {
  id
  databaseId
  status
  title
  content
  tags
  repeatDays
  source
  note
  author
  authorId
  approvedBy
  media {
    ...FlxSocialMediaRow
  }
  scheduledAt {
    timestamp
    label
  }
  publishedAt {
    timestamp
    label
  }
  created {
    timestamp
  }
  updated {
    timestamp
  }
  targets {
    ...FlxSocialTargetRow
  }
}

fragment FlxSocialTargetRow on FlxSocialTarget {
  id
  databaseId
  accountId
  provider
  account
  avatar
  status
  remoteUrl
  error
  publishedAt {
    timestamp
    label
  }
  metrics {
    views
    impressions
    reach
    clicks
    likes
    comments
    shares
    saves
  }
  caps {
    ...FlxSocialCapsFields
  }
  content
  media {
    ...FlxSocialMediaRow
  }
  options {
    ...FlxSocialOptionsFields
  }
}`},ki={id:`501365bb59297864329d808e41b73469e0fca66f789c2838584ee8464787ae48`,name:`FlxSocialPostDelete`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialPostDelete($input: FlxSocialPostDeleteInput!) {
  flxSocialPostDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},Ai={id:`4043b0e0cad9ce1b24b08e084041e854742976d792fce3aed127b5e39b3d7076`,name:`FlxSocialPostDuplicate`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialPostDuplicate($input: FlxSocialPostDuplicateInput!) {
  flxSocialPostDuplicate(input: $input) {
    post {
      ...FlxSocialPostRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSocialCapsFields on FlxSocialCaps {
  text
  image
  video
  gif
  maxText
  maxImages
  maxVideos
  mix
  needsMedia
  edit
  delete
  comment
  metrics
  options
}

fragment FlxSocialMediaRow on FlxSocialMedia {
  databaseId
  url
  kind
  mime
  bytes
  width
  height
  duration
  thumb
  name
  alt
}

fragment FlxSocialOptionsFields on FlxSocialOptions {
  title
  link
  board
  privacy
  igType
  flxCategory
  playlist
  tiktokPrivacy
  gbpTopic
  gbpCta
  gbpCtaUrl
  waKind
  waTo
  firstComment
  liVisibility
  alt
  thumbUrl
  replySettings
  eventTitle
  eventStart
  eventEnd
  offerCode
  madeForKids
  notify
  shorts
  disableComment
  disableDuet
  disableStitch
  aiGenerated
  brandContent
  brandOrganic
  shareToFeed
  autoMusic
  tags
}

fragment FlxSocialPostRow on FlxSocialPost {
  id
  databaseId
  status
  title
  content
  tags
  repeatDays
  source
  note
  author
  authorId
  approvedBy
  media {
    ...FlxSocialMediaRow
  }
  scheduledAt {
    timestamp
    label
  }
  publishedAt {
    timestamp
    label
  }
  created {
    timestamp
  }
  updated {
    timestamp
  }
  targets {
    ...FlxSocialTargetRow
  }
}

fragment FlxSocialTargetRow on FlxSocialTarget {
  id
  databaseId
  accountId
  provider
  account
  avatar
  status
  remoteUrl
  error
  publishedAt {
    timestamp
    label
  }
  metrics {
    views
    impressions
    reach
    clicks
    likes
    comments
    shares
    saves
  }
  caps {
    ...FlxSocialCapsFields
  }
  content
  media {
    ...FlxSocialMediaRow
  }
  options {
    ...FlxSocialOptionsFields
  }
}`},ji={id:`96bfd9683efd78e4c882e801d09d73f2d9600d382fa9c094fc0697b135507705`,name:`FlxSocialPosts`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialPosts($first: Int, $after: String, $status: [FlxSocialPostStatus!], $accountId: Int, $provider: String, $search: String, $tag: String, $mine: Boolean, $from: Int, $to: Int, $ascending: Boolean) {
  flxSocialPosts(
    first: $first
    after: $after
    status: $status
    accountId: $accountId
    provider: $provider
    search: $search
    tag: $tag
    mine: $mine
    from: $from
    to: $to
    ascending: $ascending
  ) {
    nodes {
      ...FlxSocialPostRow
    }
    pageInfo {
      hasNextPage
      endCursor
    }
    totalCount
  }
}

fragment FlxSocialCapsFields on FlxSocialCaps {
  text
  image
  video
  gif
  maxText
  maxImages
  maxVideos
  mix
  needsMedia
  edit
  delete
  comment
  metrics
  options
}

fragment FlxSocialMediaRow on FlxSocialMedia {
  databaseId
  url
  kind
  mime
  bytes
  width
  height
  duration
  thumb
  name
  alt
}

fragment FlxSocialOptionsFields on FlxSocialOptions {
  title
  link
  board
  privacy
  igType
  flxCategory
  playlist
  tiktokPrivacy
  gbpTopic
  gbpCta
  gbpCtaUrl
  waKind
  waTo
  firstComment
  liVisibility
  alt
  thumbUrl
  replySettings
  eventTitle
  eventStart
  eventEnd
  offerCode
  madeForKids
  notify
  shorts
  disableComment
  disableDuet
  disableStitch
  aiGenerated
  brandContent
  brandOrganic
  shareToFeed
  autoMusic
  tags
}

fragment FlxSocialPostRow on FlxSocialPost {
  id
  databaseId
  status
  title
  content
  tags
  repeatDays
  source
  note
  author
  authorId
  approvedBy
  media {
    ...FlxSocialMediaRow
  }
  scheduledAt {
    timestamp
    label
  }
  publishedAt {
    timestamp
    label
  }
  created {
    timestamp
  }
  updated {
    timestamp
  }
  targets {
    ...FlxSocialTargetRow
  }
}

fragment FlxSocialTargetRow on FlxSocialTarget {
  id
  databaseId
  accountId
  provider
  account
  avatar
  status
  remoteUrl
  error
  publishedAt {
    timestamp
    label
  }
  metrics {
    views
    impressions
    reach
    clicks
    likes
    comments
    shares
    saves
  }
  caps {
    ...FlxSocialCapsFields
  }
  content
  media {
    ...FlxSocialMediaRow
  }
  options {
    ...FlxSocialOptionsFields
  }
}`},Mi={id:`b1d81348826f953c3c5c4c13a46de500f7862d6edb2e50b5d892b2b4b9f85d07`,name:`FlxSocialPostUpdate`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialPostUpdate($input: FlxSocialPostUpdateInput!) {
  flxSocialPostUpdate(input: $input) {
    post {
      ...FlxSocialPostRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSocialCapsFields on FlxSocialCaps {
  text
  image
  video
  gif
  maxText
  maxImages
  maxVideos
  mix
  needsMedia
  edit
  delete
  comment
  metrics
  options
}

fragment FlxSocialMediaRow on FlxSocialMedia {
  databaseId
  url
  kind
  mime
  bytes
  width
  height
  duration
  thumb
  name
  alt
}

fragment FlxSocialOptionsFields on FlxSocialOptions {
  title
  link
  board
  privacy
  igType
  flxCategory
  playlist
  tiktokPrivacy
  gbpTopic
  gbpCta
  gbpCtaUrl
  waKind
  waTo
  firstComment
  liVisibility
  alt
  thumbUrl
  replySettings
  eventTitle
  eventStart
  eventEnd
  offerCode
  madeForKids
  notify
  shorts
  disableComment
  disableDuet
  disableStitch
  aiGenerated
  brandContent
  brandOrganic
  shareToFeed
  autoMusic
  tags
}

fragment FlxSocialPostRow on FlxSocialPost {
  id
  databaseId
  status
  title
  content
  tags
  repeatDays
  source
  note
  author
  authorId
  approvedBy
  media {
    ...FlxSocialMediaRow
  }
  scheduledAt {
    timestamp
    label
  }
  publishedAt {
    timestamp
    label
  }
  created {
    timestamp
  }
  updated {
    timestamp
  }
  targets {
    ...FlxSocialTargetRow
  }
}

fragment FlxSocialTargetRow on FlxSocialTarget {
  id
  databaseId
  accountId
  provider
  account
  avatar
  status
  remoteUrl
  error
  publishedAt {
    timestamp
    label
  }
  metrics {
    views
    impressions
    reach
    clicks
    likes
    comments
    shares
    saves
  }
  caps {
    ...FlxSocialCapsFields
  }
  content
  media {
    ...FlxSocialMediaRow
  }
  options {
    ...FlxSocialOptionsFields
  }
}`},Ni={id:`6a244ceffd24ffbaa730873fc84b76c1943f19b81f949f48ee591584d5450a38`,name:`FlxSocialSettings`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialSettings {
  flxSocialSettings {
    approval
    signature
    utm
    metaVersion
    xTier
    xPremium
    xMonthLimit
    tiktokMode
    pinSandbox
    liOrg
    waConn
    xMonth
    tickUrl
    lastTick {
      timestamp
    }
    auto {
      enabled
      postTypes
      accounts
      template
      mode
      delay
      image
    }
    postTypes {
      id
      name
    }
    xTiers {
      id
      name
    }
    waChoices {
      id
      name
    }
  }
  flxSocialApps {
    id
    label
    console
    redirect
    help
    ready
    fields {
      key
      label
      secret
      value
      isSet
      placeholder
      optional
    }
    connect {
      for
      label
      provider
      local
    }
  }
}`},Pi={id:`dc8548114e3ee4319fb5256091aa79de47ea2bf608ebc84792773869110d7c11`,name:`FlxSocialSettingsUpdate`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialSettingsUpdate($input: FlxSocialSettingsUpdateInput!) {
  flxSocialSettingsUpdate(input: $input) {
    settings {
      approval
      signature
      xTier
      tickUrl
      auto {
        enabled
        postTypes
        accounts
        template
        mode
        delay
        image
      }
    }
    errors {
      code
      message
      field
    }
  }
}`},Fi={id:`f751908be074aa0bf4d1dd045535dd240d0474b9eb994dc13d20449a847acf40`,name:`FlxSocialStats`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialStats($days: Int) {
  flxSocialStats(days: $days) {
    days
    lastMetrics {
      timestamp
    }
    totals {
      published
      failed
      scheduled
      pending
      drafts
      engagement
      views
      followers
      followersDelta
      successRate
    }
    byDay {
      day
      networks {
        provider
        count
      }
    }
    byNetwork {
      provider
      published
      failed
      engagement
      views
    }
    followers {
      accountId
      name
      provider
      avatar
      now
      delta
      series {
        day
        count
      }
    }
    top {
      targetId
      postId
      provider
      account
      title
      engagement
      views
      url
      at {
        timestamp
      }
    }
    upcoming {
      ...FlxSocialPostRow
    }
    errors {
      targetId
      postId
      account
      provider
      error
      at {
        timestamp
      }
    }
  }
}

fragment FlxSocialCapsFields on FlxSocialCaps {
  text
  image
  video
  gif
  maxText
  maxImages
  maxVideos
  mix
  needsMedia
  edit
  delete
  comment
  metrics
  options
}

fragment FlxSocialMediaRow on FlxSocialMedia {
  databaseId
  url
  kind
  mime
  bytes
  width
  height
  duration
  thumb
  name
  alt
}

fragment FlxSocialOptionsFields on FlxSocialOptions {
  title
  link
  board
  privacy
  igType
  flxCategory
  playlist
  tiktokPrivacy
  gbpTopic
  gbpCta
  gbpCtaUrl
  waKind
  waTo
  firstComment
  liVisibility
  alt
  thumbUrl
  replySettings
  eventTitle
  eventStart
  eventEnd
  offerCode
  madeForKids
  notify
  shorts
  disableComment
  disableDuet
  disableStitch
  aiGenerated
  brandContent
  brandOrganic
  shareToFeed
  autoMusic
  tags
}

fragment FlxSocialPostRow on FlxSocialPost {
  id
  databaseId
  status
  title
  content
  tags
  repeatDays
  source
  note
  author
  authorId
  approvedBy
  media {
    ...FlxSocialMediaRow
  }
  scheduledAt {
    timestamp
    label
  }
  publishedAt {
    timestamp
    label
  }
  created {
    timestamp
  }
  updated {
    timestamp
  }
  targets {
    ...FlxSocialTargetRow
  }
}

fragment FlxSocialTargetRow on FlxSocialTarget {
  id
  databaseId
  accountId
  provider
  account
  avatar
  status
  remoteUrl
  error
  publishedAt {
    timestamp
    label
  }
  metrics {
    views
    impressions
    reach
    clicks
    likes
    comments
    shares
    saves
  }
  caps {
    ...FlxSocialCapsFields
  }
  content
  media {
    ...FlxSocialMediaRow
  }
  options {
    ...FlxSocialOptionsFields
  }
}`},Ii={id:`cec3bd8f7f67ca6ade378ae4c96239c14fb729e6acb36deaae2f619716abbff3`,name:`FlxSocialStatsRefresh`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialStatsRefresh($input: FlxSocialStatsRefreshInput!) {
  flxSocialStatsRefresh(input: $input) {
    updated
    errors {
      code
      message
      field
    }
  }
}`},Li={id:`66b1e332cd2d0ad55c6d27fe7d60d1b50aadd3cbaa44a561e8029892cd063639`,name:`FlxSocialStudioMedia`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialStudioMedia {
  flxSocialStudioMedia {
    databaseId
    kind
    url
    thumb
    title
    prompt
    attachmentId
    width
    height
  }
}`},Ri={id:`b7859e5459cd00b1b2e64810c3eaf209d3a9773569d2667851f5f82371cf11f1`,name:`FlxSocialTargetRemoteDelete`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialTargetRemoteDelete($input: FlxSocialTargetRemoteDeleteInput!) {
  flxSocialTargetRemoteDelete(input: $input) {
    post {
      ...FlxSocialPostRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSocialCapsFields on FlxSocialCaps {
  text
  image
  video
  gif
  maxText
  maxImages
  maxVideos
  mix
  needsMedia
  edit
  delete
  comment
  metrics
  options
}

fragment FlxSocialMediaRow on FlxSocialMedia {
  databaseId
  url
  kind
  mime
  bytes
  width
  height
  duration
  thumb
  name
  alt
}

fragment FlxSocialOptionsFields on FlxSocialOptions {
  title
  link
  board
  privacy
  igType
  flxCategory
  playlist
  tiktokPrivacy
  gbpTopic
  gbpCta
  gbpCtaUrl
  waKind
  waTo
  firstComment
  liVisibility
  alt
  thumbUrl
  replySettings
  eventTitle
  eventStart
  eventEnd
  offerCode
  madeForKids
  notify
  shorts
  disableComment
  disableDuet
  disableStitch
  aiGenerated
  brandContent
  brandOrganic
  shareToFeed
  autoMusic
  tags
}

fragment FlxSocialPostRow on FlxSocialPost {
  id
  databaseId
  status
  title
  content
  tags
  repeatDays
  source
  note
  author
  authorId
  approvedBy
  media {
    ...FlxSocialMediaRow
  }
  scheduledAt {
    timestamp
    label
  }
  publishedAt {
    timestamp
    label
  }
  created {
    timestamp
  }
  updated {
    timestamp
  }
  targets {
    ...FlxSocialTargetRow
  }
}

fragment FlxSocialTargetRow on FlxSocialTarget {
  id
  databaseId
  accountId
  provider
  account
  avatar
  status
  remoteUrl
  error
  publishedAt {
    timestamp
    label
  }
  metrics {
    views
    impressions
    reach
    clicks
    likes
    comments
    shares
    saves
  }
  caps {
    ...FlxSocialCapsFields
  }
  content
  media {
    ...FlxSocialMediaRow
  }
  options {
    ...FlxSocialOptionsFields
  }
}`},zi={id:`5badf33fcf805a44c7c661263f28a611ea7d53e8fd03fecfad7829a2c4c916b6`,name:`FlxSocialTargetRemoteEdit`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialTargetRemoteEdit($input: FlxSocialTargetRemoteEditInput!) {
  flxSocialTargetRemoteEdit(input: $input) {
    post {
      ...FlxSocialPostRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSocialCapsFields on FlxSocialCaps {
  text
  image
  video
  gif
  maxText
  maxImages
  maxVideos
  mix
  needsMedia
  edit
  delete
  comment
  metrics
  options
}

fragment FlxSocialMediaRow on FlxSocialMedia {
  databaseId
  url
  kind
  mime
  bytes
  width
  height
  duration
  thumb
  name
  alt
}

fragment FlxSocialOptionsFields on FlxSocialOptions {
  title
  link
  board
  privacy
  igType
  flxCategory
  playlist
  tiktokPrivacy
  gbpTopic
  gbpCta
  gbpCtaUrl
  waKind
  waTo
  firstComment
  liVisibility
  alt
  thumbUrl
  replySettings
  eventTitle
  eventStart
  eventEnd
  offerCode
  madeForKids
  notify
  shorts
  disableComment
  disableDuet
  disableStitch
  aiGenerated
  brandContent
  brandOrganic
  shareToFeed
  autoMusic
  tags
}

fragment FlxSocialPostRow on FlxSocialPost {
  id
  databaseId
  status
  title
  content
  tags
  repeatDays
  source
  note
  author
  authorId
  approvedBy
  media {
    ...FlxSocialMediaRow
  }
  scheduledAt {
    timestamp
    label
  }
  publishedAt {
    timestamp
    label
  }
  created {
    timestamp
  }
  updated {
    timestamp
  }
  targets {
    ...FlxSocialTargetRow
  }
}

fragment FlxSocialTargetRow on FlxSocialTarget {
  id
  databaseId
  accountId
  provider
  account
  avatar
  status
  remoteUrl
  error
  publishedAt {
    timestamp
    label
  }
  metrics {
    views
    impressions
    reach
    clicks
    likes
    comments
    shares
    saves
  }
  caps {
    ...FlxSocialCapsFields
  }
  content
  media {
    ...FlxSocialMediaRow
  }
  options {
    ...FlxSocialOptionsFields
  }
}`},Bi={id:`c28e9e66d529c5b571ac1c2184279c81c13ac6e28ed1d93b5bdafa8872a1354f`,name:`FlxSocialTargetRetry`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialTargetRetry($input: FlxSocialTargetRetryInput!) {
  flxSocialTargetRetry(input: $input) {
    post {
      ...FlxSocialPostRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSocialCapsFields on FlxSocialCaps {
  text
  image
  video
  gif
  maxText
  maxImages
  maxVideos
  mix
  needsMedia
  edit
  delete
  comment
  metrics
  options
}

fragment FlxSocialMediaRow on FlxSocialMedia {
  databaseId
  url
  kind
  mime
  bytes
  width
  height
  duration
  thumb
  name
  alt
}

fragment FlxSocialOptionsFields on FlxSocialOptions {
  title
  link
  board
  privacy
  igType
  flxCategory
  playlist
  tiktokPrivacy
  gbpTopic
  gbpCta
  gbpCtaUrl
  waKind
  waTo
  firstComment
  liVisibility
  alt
  thumbUrl
  replySettings
  eventTitle
  eventStart
  eventEnd
  offerCode
  madeForKids
  notify
  shorts
  disableComment
  disableDuet
  disableStitch
  aiGenerated
  brandContent
  brandOrganic
  shareToFeed
  autoMusic
  tags
}

fragment FlxSocialPostRow on FlxSocialPost {
  id
  databaseId
  status
  title
  content
  tags
  repeatDays
  source
  note
  author
  authorId
  approvedBy
  media {
    ...FlxSocialMediaRow
  }
  scheduledAt {
    timestamp
    label
  }
  publishedAt {
    timestamp
    label
  }
  created {
    timestamp
  }
  updated {
    timestamp
  }
  targets {
    ...FlxSocialTargetRow
  }
}

fragment FlxSocialTargetRow on FlxSocialTarget {
  id
  databaseId
  accountId
  provider
  account
  avatar
  status
  remoteUrl
  error
  publishedAt {
    timestamp
    label
  }
  metrics {
    views
    impressions
    reach
    clicks
    likes
    comments
    shares
    saves
  }
  caps {
    ...FlxSocialCapsFields
  }
  content
  media {
    ...FlxSocialMediaRow
  }
  options {
    ...FlxSocialOptionsFields
  }
}`},Vi={id:`f8d604a8bbf847a49e9b83a886cc1f5c1f6bc02be42716dd0ad361dacdfdd65f`,name:`FlxSocialTickKeyReveal`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialTickKeyReveal($input: FlxSocialTickKeyRevealInput!) {
  flxSocialTickKeyReveal(input: $input) {
    url
    errors {
      code
      message
      field
    }
  }
}`},Hi={id:`df8b65ba6af7222044b383bcd58eb659a7dbcfc1aef6943b475750d6f51562a2`,name:`FlxSocialTickRun`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialTickRun($input: FlxSocialTickRunInput!) {
  flxSocialTickRun(input: $input) {
    processed
    due
    errors {
      code
      message
      field
    }
  }
}`},Ui={id:`1431f1fc9a3b21f5302c2d3cb267ddc62497d15d14a8c8ea57ce7cb8070d5cd3`,name:`FlxSocialWhatsAppAdd`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialWhatsAppAdd($input: FlxSocialWhatsAppAddInput!) {
  flxSocialWhatsAppAdd(input: $input) {
    added
    accounts {
      ...FlxSocialAccountRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSocialAccountRow on FlxSocialAccount {
  id
  databaseId
  provider
  via
  network
  name
  username
  avatar
  status
  expires {
    timestamp
  }
  kind
  premium
  lastError
  boards {
    id
    name
  }
  groups {
    id
    name
  }
  playlists {
    id
    name
  }
  caps {
    ...FlxSocialCapsFields
  }
}

fragment FlxSocialCapsFields on FlxSocialCaps {
  text
  image
  video
  gif
  maxText
  maxImages
  maxVideos
  mix
  needsMedia
  edit
  delete
  comment
  metrics
  options
}`},Wi={id:`770bd0bc66b661e8dbf40c7acceacf1953312994effd37432fcfc1edf5ed5bf4`,name:`FlxSocialWhatsAppTargets`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialWhatsAppTargets {
  flxSocialWhatsAppTargets {
    extId
    name
    kind
    avatar
    exists
  }
}`},Gi={id:`c0c034497a50d8f207d50ee8d53dee71fb810f0dfd57c6163345be91a32f0a5b`,name:`FlxSpeedAutoload`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedAutoload {
  flxSpeedAutoload {
    count
    totalKb
    tone
    largest {
      name
      owner
      kb
    }
  }
}`},Ki={id:`8d270a918a41d495c99f9871ce13a133eade0ce31328d7c2bd4123e82cb5e2c6`,name:`FlxSpeedCache`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedCache {
  flxSpeedCache {
    ...FlxSpeedCacheFields
  }
}

fragment FlxSpeedCacheFields on FlxSpeedCache {
  pendingError
  litespeedServer
  extensions {
    memcached
    redis
    sqlite3
    igbinary
  }
  litespeed {
    name
    installed
    active
    version
    pageCache
    object
    objectKind
    objectHost
  }
  sqlite {
    name
    installed
    active
    version
    fileName
    fileSize
  }
  dropinExists
  dropinOwner
  dropinName
  provider
  persistent
  conflicts {
    code
    text
    repairable
  }
  test {
    at {
      ...FlxSpeedWhenFields
    }
    provider
    persistent
    ms
    error
  }
  settingsUrls {
    litespeedPage
    litespeedObject
    sqlitePlugin
  }
  canInstall
}

fragment FlxSpeedWhenFields on FlxSpeedWhen {
  timestamp
  short
  date
  relative
}`},qi={id:`d977b224915686fef097ececbb92793cf30eced524668174faa48191478ee08f`,name:`FlxSpeedCacheRun`,kind:`mutation`,plugin:`flx-speed`,document:`mutation FlxSpeedCacheRun($input: FlxSpeedCacheRunInput!) {
  flxSpeedCacheRun(input: $input) {
    message
    cache {
      ...FlxSpeedCacheFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSpeedCacheFields on FlxSpeedCache {
  pendingError
  litespeedServer
  extensions {
    memcached
    redis
    sqlite3
    igbinary
  }
  litespeed {
    name
    installed
    active
    version
    pageCache
    object
    objectKind
    objectHost
  }
  sqlite {
    name
    installed
    active
    version
    fileName
    fileSize
  }
  dropinExists
  dropinOwner
  dropinName
  provider
  persistent
  conflicts {
    code
    text
    repairable
  }
  test {
    at {
      ...FlxSpeedWhenFields
    }
    provider
    persistent
    ms
    error
  }
  settingsUrls {
    litespeedPage
    litespeedObject
    sqlitePlugin
  }
  canInstall
}

fragment FlxSpeedWhenFields on FlxSpeedWhen {
  timestamp
  short
  date
  relative
}`},Ji={id:`fcd076afcbd146a9e6ca574ba597984e1637eee1b8f35aa9b512e428916aaa43`,name:`FlxSpeedCleanup`,kind:`mutation`,plugin:`flx-speed`,document:`mutation FlxSpeedCleanup($input: FlxSpeedCleanupInput!) {
  flxSpeedCleanup(input: $input) {
    done
    errors {
      code
      message
      field
    }
  }
}`},Yi={id:`48d2ae9c4348a0b4300b2fc4b0254b07472858e0e5f65eaddd4996ce4801bb4d`,name:`FlxSpeedConfig`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedConfig {
  flxSpeedConfig {
    canManage
    recording
    classicUrl
    adminUrl
    tabs {
      key
      label
      adminOnly
    }
    flags {
      key
      label
    }
    types {
      key
      label
    }
  }
}`},Xi={id:`58ed686173cd507ef595683e7cbf4b30ed514b82612dd1caa4e75a2199f885f0`,name:`FlxSpeedCron`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedCron {
  flxSpeedCron {
    disabled
    events
    overdue
    lastSeen {
      ...FlxSpeedWhenFields
    }
    lastWarn
    timings {
      hook
      component
      runs
      avgMs
      maxMs
      lastMs
      lastAt {
        ...FlxSpeedWhenFields
      }
      cut
      slow
    }
    items {
      hook
      next {
        ...FlxSpeedWhenFields
      }
      schedule
      args
      overdue
    }
  }
}

fragment FlxSpeedWhenFields on FlxSpeedWhen {
  timestamp
  short
  date
  relative
}`},Zi={id:`8de800753b00b5e90b240028948dd2ee2a99c1c6e89ef22b03eae970f15f2a3f`,name:`FlxSpeedDatabase`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedDatabase {
  flxSpeedDatabase {
    cards {
      label
      value
      tone
    }
    sections {
      ...FlxSpeedSectionFields
    }
    jobs {
      key
      label
      count
      runnable
    }
    info {
      label
      count
    }
    tables {
      name
      engine
      rows
      data
      index
      free
      collate
    }
  }
}

fragment FlxSpeedSectionFields on FlxSpeedSection {
  key
  title
  rows {
    label
    value
  }
}`},Qi={id:`7d247ff1bb9def8192c868f601c45a17feed2dfb10ea254afa0b785be069a8c8`,name:`FlxSpeedLive`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedLive {
  flxSpeedLive {
    meters {
      key
      label
      text
      sub
      pct
    }
    note
  }
}`},$i={id:`f053efbf67cf7f284fd5c1efd1a0157d89c3beedf57981dde2a693e1b6567f7f`,name:`FlxSpeedPageSpeed`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedPageSpeed {
  flxSpeedPageSpeed {
    ...FlxSpeedPageSpeedFields
  }
}

fragment FlxSpeedPageSpeedFields on FlxSpeedPageSpeed {
  toggles {
    key
    on
    forced
  }
  cssScope
  inlineKb
  safelist
  exclude
  skipUrls
  cssFiles
  jsFiles
  cacheBytes
  homeUrl
  offUrl
  auditUrl
  animations {
    name
    props
    file
    reduced
  }
  seenFiles
  seenAt {
    ...FlxSpeedWhenFields
  }
}

fragment FlxSpeedWhenFields on FlxSpeedWhen {
  timestamp
  short
  date
  relative
}`},ea={id:`b375ed8680d809378b5e9616f1f1bc440c2e912041b84d39d6df690eb4de4c09`,name:`FlxSpeedPageSpeedClear`,kind:`mutation`,plugin:`flx-speed`,document:`mutation FlxSpeedPageSpeedClear {
  flxSpeedPageSpeedClear(input: {  }) {
    pageSpeed {
      ...FlxSpeedPageSpeedFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSpeedPageSpeedFields on FlxSpeedPageSpeed {
  toggles {
    key
    on
    forced
  }
  cssScope
  inlineKb
  safelist
  exclude
  skipUrls
  cssFiles
  jsFiles
  cacheBytes
  homeUrl
  offUrl
  auditUrl
  animations {
    name
    props
    file
    reduced
  }
  seenFiles
  seenAt {
    ...FlxSpeedWhenFields
  }
}

fragment FlxSpeedWhenFields on FlxSpeedWhen {
  timestamp
  short
  date
  relative
}`},ta={id:`e4e117cb961c1485e8fd5b4c322f34231ddec9056751ec79317e99985fca16bc`,name:`FlxSpeedPageSpeedSave`,kind:`mutation`,plugin:`flx-speed`,document:`mutation FlxSpeedPageSpeedSave($input: FlxSpeedPageSpeedSaveInput!) {
  flxSpeedPageSpeedSave(input: $input) {
    pageSpeed {
      ...FlxSpeedPageSpeedFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSpeedPageSpeedFields on FlxSpeedPageSpeed {
  toggles {
    key
    on
    forced
  }
  cssScope
  inlineKb
  safelist
  exclude
  skipUrls
  cssFiles
  jsFiles
  cacheBytes
  homeUrl
  offUrl
  auditUrl
  animations {
    name
    props
    file
    reduced
  }
  seenFiles
  seenAt {
    ...FlxSpeedWhenFields
  }
}

fragment FlxSpeedWhenFields on FlxSpeedWhen {
  timestamp
  short
  date
  relative
}`},na={id:`7dbefa1a379a4febe1f2eb7c39c6f399f2bfa417df3cd57c8cf0ede758a3b3c7`,name:`FlxSpeedProfile`,kind:`mutation`,plugin:`flx-speed`,document:`mutation FlxSpeedProfile($input: FlxSpeedProfileInput!) {
  flxSpeedProfile(input: $input) {
    recordId
    status
    url
    errors {
      code
      message
      field
    }
  }
}`},ra={id:`d43528eacd33ad43a20ded425950c72cfe41d37466f94418bbb5a91d9bd39bd1`,name:`FlxSpeedRecord`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedRecord($id: Int!) {
  flxSpeedRecord(id: $id) {
    record {
      ...FlxSpeedRecordRow
    }
    data
    labels
    panel {
      slowQueryMs
      slowMs
      strings
      scriptUrl
      styleUrl
    }
  }
}

fragment FlxSpeedRecordRow on FlxSpeedRecord {
  databaseId
  at {
    ...FlxSpeedWhenFields
  }
  url
  method
  type
  typeLabel
  status
  ms
  memKb
  queries
  queryMs
  http
  errors
  flags {
    key
    label
  }
  failed
}

fragment FlxSpeedWhenFields on FlxSpeedWhen {
  timestamp
  short
  date
  relative
}`},ia={id:`b6692580ef2fbfa27c06265ef8f344b27cc749cd14f6cfb2725953194ca7909a`,name:`FlxSpeedRecords`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedRecords($first: Int = 25, $after: String, $hours: Int, $flag: String, $type: String, $q: String, $slow: Boolean) {
  flxSpeedRecords(
    first: $first
    after: $after
    hours: $hours
    flag: $flag
    type: $type
    q: $q
    slow: $slow
  ) {
    nodes {
      ...FlxSpeedRecordRow
    }
    pageInfo {
      hasNextPage
      endCursor
    }
    totalCount
  }
}

fragment FlxSpeedRecordRow on FlxSpeedRecord {
  databaseId
  at {
    ...FlxSpeedWhenFields
  }
  url
  method
  type
  typeLabel
  status
  ms
  memKb
  queries
  queryMs
  http
  errors
  flags {
    key
    label
  }
  failed
}

fragment FlxSpeedWhenFields on FlxSpeedWhen {
  timestamp
  short
  date
  relative
}`},aa={id:`845b54851a74fba07a12deeefeb2c7b71f6ecfaaa759daa50c6041a482dddb25`,name:`FlxSpeedRecordsClear`,kind:`mutation`,plugin:`flx-speed`,document:`mutation FlxSpeedRecordsClear {
  flxSpeedRecordsClear(input: {  }) {
    cleared
    errors {
      code
      message
      field
    }
  }
}`},oa={id:`9234ad49b162d1b924b722bc341baff844c5c5fe32121c5f3c604fc0d018b89a`,name:`FlxSpeedServer`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedServer {
  flxSpeedServer {
    ...FlxSpeedSectionFields
  }
}

fragment FlxSpeedSectionFields on FlxSpeedSection {
  key
  title
  rows {
    label
    value
  }
}`},sa={id:`3ed06bef7eb6f4c9e11545d09afef2ce9ff9cbc1fd38115124ff2b649e3d630f`,name:`FlxSpeedSettings`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedSettings {
  flxSpeedSettings {
    ...FlxSpeedSettingsFields
  }
}

fragment FlxSpeedSettingsFields on FlxSpeedSettings {
  panel
  panelFront
  caps
  record
  recordAdmins
  sample
  slowMs
  slowQueryMs
  keepDays
  maxRows
  diskQuotaGb
  loginSlug
  loginBlocked
  loginSlugLocked
  loginDisabled
  loginActive
  loginUrl
  loginPrefix
  loginMovedUrl
  mcpActive
  mcpTools
}`},ca={id:`ae1ea2b03f50ac55abebc5ae0a507d77e463e14edae360feaf1646ebd2ddaac4`,name:`FlxSpeedSettingsSave`,kind:`mutation`,plugin:`flx-speed`,document:`mutation FlxSpeedSettingsSave($input: FlxSpeedSettingsSaveInput!) {
  flxSpeedSettingsSave(input: $input) {
    settings {
      ...FlxSpeedSettingsFields
    }
    loginChanged
    loginRemoved
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSpeedSettingsFields on FlxSpeedSettings {
  panel
  panelFront
  caps
  record
  recordAdmins
  sample
  slowMs
  slowQueryMs
  keepDays
  maxRows
  diskQuotaGb
  loginSlug
  loginBlocked
  loginSlugLocked
  loginDisabled
  loginActive
  loginUrl
  loginPrefix
  loginMovedUrl
  mcpActive
  mcpTools
}`},la={id:`29e3951ab3ac534d8bc6a7abc8e6aaa037842a052d95472eddaa6c714ad66e7e`,name:`FlxSpeedSummary`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedSummary($hours: Int = 24) {
  flxSpeedHints {
    status
    label
    message
    actionUrl
    actionLabel
  }
  flxSpeedSummary(hours: $hours) {
    hours
    recorded
    msAvg
    msP95
    msMax
    flags {
      key
      label
      count
      tone
    }
    byType {
      type
      label
      count
      msAvg
      msP50
      msP95
      msMax
    }
    slowestUrls {
      url
      count
      msAvg
      msMax
      errors
    }
    components {
      component
      requests
      queries
      msPerRequest
    }
    slowQueries {
      sql
      error
      component
      count
      msAvg
      msMax
      lastId
    }
    phpErrors {
      level
      message
      file
      component
      requests
      lastId
    }
    httpCalls {
      endpoint
      component
      count
      failed
      msAvg
      lastError
    }
  }
}`},ua={id:`a6ad626fabff2f9904a2f88aec77a49b4dfd900c575ea7fd357b0234aca73d82`,name:`FlxSpeedSystem`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedSystem {
  flxSpeedSystem {
    checks {
      id
      source
      group
      groupLabel
      label
      status
      message
      actionUrl
      actionLabel
    }
    summary {
      ok
      warning
      error
      info
    }
    fixes {
      at {
        ...FlxSpeedWhenFields
      }
      user
      label
      value
      before
      after
      ok
      message
    }
    hasFixes
  }
}

fragment FlxSpeedWhenFields on FlxSpeedWhen {
  timestamp
  short
  date
  relative
}`},da={id:`7e536f64c2f2071ee080da4c303e3ff3df331d4ee5454e2d32e6ca2cfa558491`,name:`FlxStudioBrainstorm`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioBrainstorm($input: FlxStudioBrainstormInput!) {
  flxStudioBrainstorm(input: $input) {
    batch
    ideas {
      title
      prompt
    }
    errors {
      code
      message
      field
    }
  }
}`},fa={id:`164e3eaaab653626f4560a3b3c33be0334bea85986ca1777daa2d7b6bd689aab`,name:`FlxStudioCancel`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioCancel($input: FlxStudioCancelInput!) {
  flxStudioCancel(input: $input) {
    items {
      ...FlxStudioItemRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxStudioItemRow on FlxStudioItem {
  id
  databaseId
  kind
  state
  job
  batch
  parentId
  folderId
  title
  prompt
  model
  provider
  params {
    aspect
    resolution
    quality
    count
    duration
    audio
    progress
    draft
    edit
    enhanced
    idea
    protected
    refs {
      type
      id
      role
      name
      thumb
      photos
    }
  }
  url
  thumb
  mime
  width
  height
  bytes
  cost
  error
  favorite
  attachmentId
  ownerId
  ownerName
  createdAt {
    timestamp
  }
  updatedAt {
    timestamp
  }
}`},pa={id:`412a1cf0eb420e33030acdc395810cb1317af6d7530bddff047baab4845ff908`,name:`FlxStudioEnhance`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioEnhance($input: FlxStudioEnhanceInput!) {
  flxStudioEnhance(input: $input) {
    prompt
    errors {
      code
      message
      field
    }
  }
}`},ma={id:`93c9aa7f8a19fc0358f9c0f5fce98107b3cb6406d5c75a3e23d35bafc2030270`,name:`FlxStudioFolderCreate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioFolderCreate($input: FlxStudioFolderCreateInput!) {
  flxStudioFolderCreate(input: $input) {
    folder {
      ...FlxStudioFolderRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxStudioFolderRow on FlxStudioFolder {
  databaseId
  parentId
  name
  shared
  ownerId
}`},ha={id:`9ecd95b4c0e16c01a049c04b82f79013ad067a1dd99cd5a8186be641a6f36288`,name:`FlxStudioFolderDelete`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioFolderDelete($input: FlxStudioFolderDeleteInput!) {
  flxStudioFolderDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},ga={id:`ac9f3ba4ecb20b0da721b057651d3f6d4670fade63fa567ffff21a55bbfa1e4f`,name:`FlxStudioFolderUpdate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioFolderUpdate($input: FlxStudioFolderUpdateInput!) {
  flxStudioFolderUpdate(input: $input) {
    folder {
      ...FlxStudioFolderRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxStudioFolderRow on FlxStudioFolder {
  databaseId
  parentId
  name
  shared
  ownerId
}`},_a={id:`9bc13e2c994ded586d4eee73ff381a8d1e21c82a079689d5e02053485353496a`,name:`FlxStudioGenerate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioGenerate($input: FlxStudioGenerateInput!) {
  flxStudioGenerate(input: $input) {
    items {
      ...FlxStudioItemRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxStudioItemRow on FlxStudioItem {
  id
  databaseId
  kind
  state
  job
  batch
  parentId
  folderId
  title
  prompt
  model
  provider
  params {
    aspect
    resolution
    quality
    count
    duration
    audio
    progress
    draft
    edit
    enhanced
    idea
    protected
    refs {
      type
      id
      role
      name
      thumb
      photos
    }
  }
  url
  thumb
  mime
  width
  height
  bytes
  cost
  error
  favorite
  attachmentId
  ownerId
  ownerName
  createdAt {
    timestamp
  }
  updatedAt {
    timestamp
  }
}`},va={id:`639f7d0675693c31cb95b48aabe5b674671e4f6df5db262ee98e9354c4f2941f`,name:`FlxStudioItems`,kind:`query`,plugin:`flx-studio`,document:`query FlxStudioItems($first: Int = 60, $after: String, $folderId: Int, $kind: FlxStudioKind, $favorite: Boolean, $batch: String, $search: String, $allOwners: Boolean) {
  flxStudioItems(
    first: $first
    after: $after
    folderId: $folderId
    kind: $kind
    favorite: $favorite
    batch: $batch
    search: $search
    allOwners: $allOwners
  ) {
    ...FlxStudioItemPage
  }
}

fragment FlxStudioItemPage on FlxStudioItemConnection {
  nodes {
    ...FlxStudioItemRow
  }
  pageInfo {
    hasNextPage
    endCursor
  }
}

fragment FlxStudioItemRow on FlxStudioItem {
  id
  databaseId
  kind
  state
  job
  batch
  parentId
  folderId
  title
  prompt
  model
  provider
  params {
    aspect
    resolution
    quality
    count
    duration
    audio
    progress
    draft
    edit
    enhanced
    idea
    protected
    refs {
      type
      id
      role
      name
      thumb
      photos
    }
  }
  url
  thumb
  mime
  width
  height
  bytes
  cost
  error
  favorite
  attachmentId
  ownerId
  ownerName
  createdAt {
    timestamp
  }
  updatedAt {
    timestamp
  }
}`},ya={id:`5c12139fff325a5d541ffe31a43bbdab23d6b3e372fff9323d164547a5d0c353`,name:`FlxStudioItemsDelete`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioItemsDelete($input: FlxStudioItemsDeleteInput!) {
  flxStudioItemsDelete(input: $input) {
    deletedIds
    errors {
      code
      message
      field
    }
  }
}`},ba={id:`ed4c508fa39d29a80a706703e8aecf3b0830064acc79dc3824204f955ee94ae1`,name:`FlxStudioItemsMove`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioItemsMove($input: FlxStudioItemsMoveInput!) {
  flxStudioItemsMove(input: $input) {
    movedIds
    errors {
      code
      message
      field
    }
  }
}`},xa={id:`b6814f6729f348c9e0b952a2b4487db6976a118af605e0a4a6f300163c33332e`,name:`FlxStudioItemToLibrary`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioItemToLibrary($input: FlxStudioItemToLibraryInput!) {
  flxStudioItemToLibrary(input: $input) {
    library {
      attachmentId
      editUrl
      url
    }
    errors {
      code
      message
      field
    }
  }
}`},Sa={id:`82ed1fcbf3ca9509636cc0cc2917dc945f9c446c42ee0461571e8e6826727144`,name:`FlxStudioItemUpdate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioItemUpdate($input: FlxStudioItemUpdateInput!) {
  flxStudioItemUpdate(input: $input) {
    item {
      ...FlxStudioItemRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxStudioItemRow on FlxStudioItem {
  id
  databaseId
  kind
  state
  job
  batch
  parentId
  folderId
  title
  prompt
  model
  provider
  params {
    aspect
    resolution
    quality
    count
    duration
    audio
    progress
    draft
    edit
    enhanced
    idea
    protected
    refs {
      type
      id
      role
      name
      thumb
      photos
    }
  }
  url
  thumb
  mime
  width
  height
  bytes
  cost
  error
  favorite
  attachmentId
  ownerId
  ownerName
  createdAt {
    timestamp
  }
  updatedAt {
    timestamp
  }
}`},Ca={id:`df8d9048b9a909150a5b758644ccfaae13f920a46ff85df0a5de0a5edef196d5`,name:`FlxStudioPersonCreate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioPersonCreate($input: FlxStudioPersonCreateInput!) {
  flxStudioPersonCreate(input: $input) {
    person {
      ...FlxStudioPersonRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxStudioPersonRow on FlxStudioPerson {
  databaseId
  name
  ownerId
  photos {
    ...FlxStudioRefRow
  }
}

fragment FlxStudioRefRow on FlxStudioRef {
  databaseId
  personId
  name
  role
  url
  thumb
  mime
  width
  height
  saved
}`},wa={id:`715fd6d84c8d95994b9cf336dfa63288931d4f28adc31c3b5e0074cba19aa55c`,name:`FlxStudioPersonDelete`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioPersonDelete($input: FlxStudioPersonDeleteInput!) {
  flxStudioPersonDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},Ta={id:`b929a0c4365da430782991c15105207e5722bfaa9c23583e7535c6a823c9b8a8`,name:`FlxStudioPersonRename`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioPersonRename($input: FlxStudioPersonRenameInput!) {
  flxStudioPersonRename(input: $input) {
    person {
      ...FlxStudioPersonRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxStudioPersonRow on FlxStudioPerson {
  databaseId
  name
  ownerId
  photos {
    ...FlxStudioRefRow
  }
}

fragment FlxStudioRefRow on FlxStudioRef {
  databaseId
  personId
  name
  role
  url
  thumb
  mime
  width
  height
  saved
}`},Ea={id:`8592ab98d0e723bac97426d1c9853126e94192f67de8787c77cf8a65a5707321`,name:`FlxStudioPoll`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioPoll($input: FlxStudioPollInput!) {
  flxStudioPoll(input: $input) {
    items {
      ...FlxStudioItemRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxStudioItemRow on FlxStudioItem {
  id
  databaseId
  kind
  state
  job
  batch
  parentId
  folderId
  title
  prompt
  model
  provider
  params {
    aspect
    resolution
    quality
    count
    duration
    audio
    progress
    draft
    edit
    enhanced
    idea
    protected
    refs {
      type
      id
      role
      name
      thumb
      photos
    }
  }
  url
  thumb
  mime
  width
  height
  bytes
  cost
  error
  favorite
  attachmentId
  ownerId
  ownerName
  createdAt {
    timestamp
  }
  updatedAt {
    timestamp
  }
}`},Da={id:`076f0f83b765ad61ba0d1034777af88df4d15742683110a1390fc3320e815248`,name:`FlxStudioRefCreate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioRefCreate($input: FlxStudioRefCreateInput!) {
  flxStudioRefCreate(input: $input) {
    ref {
      ...FlxStudioRefRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxStudioRefRow on FlxStudioRef {
  databaseId
  personId
  name
  role
  url
  thumb
  mime
  width
  height
  saved
}`},Oa={id:`4cc7a8b993dd67b840279ee0ecdb4ad0059d3de9fb975053551e0907c9b35778`,name:`FlxStudioRefDelete`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioRefDelete($input: FlxStudioRefDeleteInput!) {
  flxStudioRefDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},ka={id:`7bcbc27e22f195e5ce14ab38eedd554807ce1e4f5d27c4bd6837a7f823fed355`,name:`FlxStudioRefUpdate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioRefUpdate($input: FlxStudioRefUpdateInput!) {
  flxStudioRefUpdate(input: $input) {
    ref {
      ...FlxStudioRefRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxStudioRefRow on FlxStudioRef {
  databaseId
  personId
  name
  role
  url
  thumb
  mime
  width
  height
  saved
}`},Aa={id:`5658cd768af0cfcae70f66f69eab5a1f47657f446c922a166dcb4249182de2e6`,name:`FlxStudioSettingsUpdate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioSettingsUpdate($input: FlxStudioSettingsUpdateInput!) {
  flxStudioSettingsUpdate(input: $input) {
    settings {
      ...FlxStudioSettingsRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxStudioSettingsRow on FlxStudioSettings {
  imageModel
  videoModel
  textModel
  maxCount
  maxBatch
  sharedPeople
}`},ja={id:`2b2f30d9b3b3929647767a5232da353836f4ffcc9bab3945162b4237ad8aa50a`,name:`FlxStudioStats`,kind:`query`,plugin:`flx-studio`,document:`query FlxStudioStats($days: Int = 30) {
  flxStudioStats(days: $days) {
    days
    image
    video
    failed
    cancelled
    pending
    users
    bytes
    spendTotal
    spendMedia
    spendText
    spendEstimated
    spendFrom
    logUrl
    daily {
      date
      image
      video
      failed
      cost
    }
    models {
      id
      label
      kind
      done
      failed
      cost
    }
    providers {
      id
      label
      done
      failed
      cost
    }
    people {
      userId
      name
      avatar
      image
      video
      cost
      lastAt {
        timestamp
      }
    }
    errors {
      itemId
      model
      provider
      error
      createdAt {
        timestamp
      }
    }
    budget {
      ...FlxStudioBudgetRow
    }
  }
}

fragment FlxStudioBudgetRow on FlxStudioBudget {
  period
  label
  limit
  spent
  pct
  over
}`},Ma={id:`f3d78587f86d7f71592a658e92d7281ccb4626ba12a84ac511a444c7cd8df6ba`,name:`FlxStudioTemplateCreate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioTemplateCreate($input: FlxStudioTemplateCreateInput!) {
  flxStudioTemplateCreate(input: $input) {
    templates {
      ...FlxStudioTemplateRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxStudioTemplateRow on FlxStudioTemplate {
  databaseId
  name
  prompt
  shared
  ownerId
  params {
    kind
    model
    aspect
    resolution
    quality
    size
    count
    duration
    audio
    mode
    refs {
      type
      id
      role
      name
      thumb
      photos
    }
  }
}`},Na={id:`ddb8a4be1c12b292124e41747f467cc316dbb805cd3949d366715dabf5be628e`,name:`FlxStudioTemplateDelete`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioTemplateDelete($input: FlxStudioTemplateDeleteInput!) {
  flxStudioTemplateDelete(input: $input) {
    templates {
      ...FlxStudioTemplateRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxStudioTemplateRow on FlxStudioTemplate {
  databaseId
  name
  prompt
  shared
  ownerId
  params {
    kind
    model
    aspect
    resolution
    quality
    size
    count
    duration
    audio
    mode
    refs {
      type
      id
      role
      name
      thumb
      photos
    }
  }
}`},Pa={id:`2bacebe6d3f0fa55575f79f7abb0d22d42c7def0e3bf004c11657ff248dd3bcc`,name:`FlxStudioWorkspace`,kind:`query`,plugin:`flx-studio`,document:`query FlxStudioWorkspace {
  flxStudio {
    version
    userId
    userName
    canManage
    canLibrary
    aiMedia
    aiText
    setupUrl
    catalog {
      defaultImage
      defaultVideo
      providers {
        id
        label
        ready
      }
      models {
        id
        label
        kind
        routes
        providers
        auto
        aspects
        resolutions
        qualities
        durationMin
        durationMax
        durationDefault
        maxRefs
        maxCount
        mask
        prices {
          resolution
          usd
        }
      }
    }
    settings {
      ...FlxStudioSettingsRow
    }
    folders {
      ...FlxStudioFolderRow
    }
    people {
      ...FlxStudioPersonRow
    }
    refs {
      ...FlxStudioRefRow
    }
    templates {
      ...FlxStudioTemplateRow
    }
    budget {
      ...FlxStudioBudgetRow
    }
  }
}

fragment FlxStudioBudgetRow on FlxStudioBudget {
  period
  label
  limit
  spent
  pct
  over
}

fragment FlxStudioFolderRow on FlxStudioFolder {
  databaseId
  parentId
  name
  shared
  ownerId
}

fragment FlxStudioPersonRow on FlxStudioPerson {
  databaseId
  name
  ownerId
  photos {
    ...FlxStudioRefRow
  }
}

fragment FlxStudioRefRow on FlxStudioRef {
  databaseId
  personId
  name
  role
  url
  thumb
  mime
  width
  height
  saved
}

fragment FlxStudioSettingsRow on FlxStudioSettings {
  imageModel
  videoModel
  textModel
  maxCount
  maxBatch
  sharedPeople
}

fragment FlxStudioTemplateRow on FlxStudioTemplate {
  databaseId
  name
  prompt
  shared
  ownerId
  params {
    kind
    model
    aspect
    resolution
    quality
    size
    count
    duration
    audio
    mode
    refs {
      type
      id
      role
      name
      thumb
      photos
    }
  }
}`},Fa={id:`fca51d9372f393736cd5c7f3cc3a6c6f76bc42c714907f05360f59ce43283d8b`,name:`FlxTaskCommentCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCommentCreate($input: FlxTaskCommentCreateInput!) {
  flxTaskCommentCreate(input: $input) {
    comment {
      ...FlxTaskCommentRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxTaskCommentRow on FlxTaskComment {
  databaseId
  taskId
  authorId
  body
  createdAt {
    iso
  }
  editedAt {
    iso
  }
  files {
    ...FlxTaskFileRow
  }
}

fragment FlxTaskFileRow on FlxTaskFile {
  databaseId
  taskId
  commentId
  fieldId
  context
  uploaderId
  name
  mime
  size
  image
  hasThumb
  width
  height
  createdAt {
    iso
  }
}`},Ia={id:`f4712b3032ffaf4580393cf0e6234154fa1eb66399b12784aa29f29b625d34bd`,name:`FlxTaskCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCreate($input: FlxTaskCreateInput!) {
  flxTaskCreate(input: $input) {
    task {
      ...FlxTaskRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxTaskRow on FlxTask {
  databaseId
  title
  kind
  status
  priority
  listId
  parentId
  startDate
  dueDate
  position
  creatorId
  createdAt {
    iso
  }
  updatedAt {
    iso
  }
  completedAt {
    iso
  }
  assigneeIds
  tagIds
  fieldValues
  commentCount
  fileCount
  subtaskCount
  agendaCount
  decisionCount
  coverFileId
  archived
  hasDescription
}`},La={id:`157053946bdf55aa4bf5f22a676119ec48e7bbe04c053867b7e0eedb11e04ec2`,name:`FlxTaskDelete`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskDelete($input: FlxTaskDeleteInput!) {
  flxTaskDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},Ra={id:`fa1d3f71ca31ab2a7b72c1a987e295f209d6564e8a68e3643dee354a44544ecf`,name:`FlxTaskDetail`,kind:`query`,plugin:`flx-tasks`,document:`query FlxTaskDetail($id: ID!) {
  flxTask(id: $id) {
    ...FlxTaskScreen
  }
}

fragment FlxTaskCommentRow on FlxTaskComment {
  databaseId
  taskId
  authorId
  body
  createdAt {
    iso
  }
  editedAt {
    iso
  }
  files {
    ...FlxTaskFileRow
  }
}

fragment FlxTaskFileRow on FlxTaskFile {
  databaseId
  taskId
  commentId
  fieldId
  context
  uploaderId
  name
  mime
  size
  image
  hasThumb
  width
  height
  createdAt {
    iso
  }
}

fragment FlxTaskRow on FlxTask {
  databaseId
  title
  kind
  status
  priority
  listId
  parentId
  startDate
  dueDate
  position
  creatorId
  createdAt {
    iso
  }
  updatedAt {
    iso
  }
  completedAt {
    iso
  }
  assigneeIds
  tagIds
  fieldValues
  commentCount
  fileCount
  subtaskCount
  agendaCount
  decisionCount
  coverFileId
  archived
  hasDescription
}

fragment FlxTaskScreen on FlxTask {
  ...FlxTaskRow
  description
  role
  watching
  watcherIds
  depth
  depthLimit
  parent {
    databaseId
    title
  }
  comments {
    ...FlxTaskCommentRow
  }
  activity {
    databaseId
    userId
    action
    data
    createdAt {
      iso
    }
  }
  attachments {
    ...FlxTaskFileRow
  }
  children {
    ...FlxTaskRow
    description
  }
  subtree {
    ...FlxTaskRow
  }
  childFiles {
    taskId
    files {
      ...FlxTaskFileRow
    }
  }
}`},za={id:`d357c8fdb6c372508b17bb93fa22ec80224c0ff43854104149816d70a9e80870`,name:`FlxTaskNotificationsRead`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskNotificationsRead($input: FlxTaskNotificationsReadInput!) {
  flxTaskNotificationsRead(input: $input) {
    unread
    errors {
      code
      message
      field
    }
  }
}`},Ba={id:`004e85b9237b995957f75c2cfe2a996f5d2de6ca98b31c98511cb403df800ae3`,name:`FlxTaskUpdate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdate($input: FlxTaskUpdateInput!) {
  flxTaskUpdate(input: $input) {
    task {
      ...FlxTaskRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxTaskRow on FlxTask {
  databaseId
  title
  kind
  status
  priority
  listId
  parentId
  startDate
  dueDate
  position
  creatorId
  createdAt {
    iso
  }
  updatedAt {
    iso
  }
  completedAt {
    iso
  }
  assigneeIds
  tagIds
  fieldValues
  commentCount
  fileCount
  subtaskCount
  agendaCount
  decisionCount
  coverFileId
  archived
  hasDescription
}`},Va={id:`7c644e53f296f0dbf859b7bfcb4748e8c5be3ef891474fb47a262a2cd993be7a`,name:`FlxTaskUpdateDetail`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdateDetail($input: FlxTaskUpdateInput!) {
  flxTaskUpdate(input: $input) {
    task {
      ...FlxTaskScreen
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxTaskCommentRow on FlxTaskComment {
  databaseId
  taskId
  authorId
  body
  createdAt {
    iso
  }
  editedAt {
    iso
  }
  files {
    ...FlxTaskFileRow
  }
}

fragment FlxTaskFileRow on FlxTaskFile {
  databaseId
  taskId
  commentId
  fieldId
  context
  uploaderId
  name
  mime
  size
  image
  hasThumb
  width
  height
  createdAt {
    iso
  }
}

fragment FlxTaskRow on FlxTask {
  databaseId
  title
  kind
  status
  priority
  listId
  parentId
  startDate
  dueDate
  position
  creatorId
  createdAt {
    iso
  }
  updatedAt {
    iso
  }
  completedAt {
    iso
  }
  assigneeIds
  tagIds
  fieldValues
  commentCount
  fileCount
  subtaskCount
  agendaCount
  decisionCount
  coverFileId
  archived
  hasDescription
}

fragment FlxTaskScreen on FlxTask {
  ...FlxTaskRow
  description
  role
  watching
  watcherIds
  depth
  depthLimit
  parent {
    databaseId
    title
  }
  comments {
    ...FlxTaskCommentRow
  }
  activity {
    databaseId
    userId
    action
    data
    createdAt {
      iso
    }
  }
  attachments {
    ...FlxTaskFileRow
  }
  children {
    ...FlxTaskRow
    description
  }
  subtree {
    ...FlxTaskRow
  }
  childFiles {
    taskId
    files {
      ...FlxTaskFileRow
    }
  }
}`},Ha={id:`a2c919c298da71f1634726a7c85c4344cf85f3602cbf54e4175bd8e45578e9b5`,name:`FlxTaskWatch`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskWatch($input: FlxTaskWatchInput!) {
  flxTaskWatch(input: $input) {
    watcherIds
    errors {
      code
      message
      field
    }
  }
}`},Ua={id:`82a77154cc559fdb176a8880985bf3a64706b404515780458ee98ec4b66f3870`,name:`FlxThemeMigrate`,kind:`mutation`,plugin:`fluxwp`,document:`mutation FlxThemeMigrate($input: FlxThemeMigrateInput!) {
  flxThemeMigrate(input: $input) {
    log
    errors {
      code
      message
      field
    }
  }
}`},Wa={id:`fc5031368340a38a66f0982b81b94cbed677d41d75d84f75da83d39cbf71af4e`,name:`FlxThemeMigrationSources`,kind:`query`,plugin:`fluxwp`,document:`query FlxThemeMigrationSources {
  flxThemeMigrationSources {
    stylesheet
    lastRun {
      label
    }
  }
}`},Ga={id:`3ed6b0ac119ee2d69851b315aa6bf0e730f23ff9b8a1b58d8df96549912a99fb`,name:`FlxThemePayLogosSave`,kind:`mutation`,plugin:`fluxwp`,document:`mutation FlxThemePayLogosSave($input: FlxThemePayLogosSaveInput!) {
  flxThemePayLogosSave(input: $input) {
    logos {
      ...FlxThemePayLogosFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxThemePayLogoRow on FlxThemePayLogo {
  key
  label
  url
  chip
}

fragment FlxThemePayLogosFields on FlxThemePayLogos {
  automatic
  current {
    ...FlxThemePayLogoRow
  }
  builtin {
    ...FlxThemePayLogoRow
  }
  automaticList {
    ...FlxThemePayLogoRow
  }
}`},Ka={id:`37837540607fffefd13b6fbddd55ddff4839e6df404bedaaef4da41084b3352f`,name:`FlxThemeSettings`,kind:`query`,plugin:`fluxwp`,document:`query FlxThemeSettings {
  flxThemeSettings {
    ...FlxThemeSettingsFields
  }
  pages(
    first: 100
    where: { status: PUBLISH, orderby: { field: TITLE, order: ASC } }
  ) {
    nodes {
      databaseId
      title
    }
  }
  flxThemePayLogos {
    ...FlxThemePayLogosFields
  }
}

fragment FlxThemePayLogoRow on FlxThemePayLogo {
  key
  label
  url
  chip
}

fragment FlxThemePayLogosFields on FlxThemePayLogos {
  automatic
  current {
    ...FlxThemePayLogoRow
  }
  builtin {
    ...FlxThemePayLogoRow
  }
  automaticList {
    ...FlxThemePayLogoRow
  }
}

fragment FlxThemeSettingsFields on FlxThemeSettings {
  visibility
  comingSoonTitle
  comingSoonText
  storeNoticeTitle
  storeNoticeText
  storeNoticeTitleDefault
  storeNoticeTextDefault
  contactEmail
  siteType
  pattern
  tableHead
  newsIntro
  darkMode
  animations
  pagination
  activeCard
  activeCampaign
  cookieEnabled
  cookieTitle
  cookieText
  cookieAccept
  cookieReject
  cookieMore
  cookiePage
  cookiePosition
  cookieRevision
  ga4Id
  gaMode
  consentScripts
  bankHolder
  bankIban
  bankInUse
  woocommerceActive
  activeCardAvailable
  canEditScripts
  presetsUrl
  campaigns {
    databaseId
    title
  }
}`},qa={id:`e8bf1a5cfdcfc9f66d6c5707ad00151b20e558b30ea400989b9547e3acac1ae6`,name:`FlxThemeSettingsSave`,kind:`mutation`,plugin:`fluxwp`,document:`mutation FlxThemeSettingsSave($input: FlxThemeSettingsSaveInput!) {
  flxThemeSettingsSave(input: $input) {
    settings {
      ...FlxThemeSettingsFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxThemeSettingsFields on FlxThemeSettings {
  visibility
  comingSoonTitle
  comingSoonText
  storeNoticeTitle
  storeNoticeText
  storeNoticeTitleDefault
  storeNoticeTextDefault
  contactEmail
  siteType
  pattern
  tableHead
  newsIntro
  darkMode
  animations
  pagination
  activeCard
  activeCampaign
  cookieEnabled
  cookieTitle
  cookieText
  cookieAccept
  cookieReject
  cookieMore
  cookiePage
  cookiePosition
  cookieRevision
  ga4Id
  gaMode
  consentScripts
  bankHolder
  bankIban
  bankInUse
  woocommerceActive
  activeCardAvailable
  canEditScripts
  presetsUrl
  campaigns {
    databaseId
    title
  }
}`},Ja={id:`2319101e3a871af11052c6fe8dcae1de118834cc9f13b232bd573001daca537c`,name:`FlxThemeSystem`,kind:`query`,plugin:`fluxwp`,document:`query FlxThemeSystem {
  flxThemeSystem {
    checkedAt {
      iso
      timestamp
      label
    }
    checks {
      id
      source
      sourceLabel
      group
      label
      status
      message
      actionUrl
      actionLabel
    }
    fixLogAvailable
    fixLog {
      time {
        timestamp
        label
      }
      user
      label
      value
      before
      after
      success
      message
    }
  }
}`},Ya={id:`a722ff87a9ced30ef1b3cd860906049dfb55ce9de492e09647f12fa6702e6897`,name:`FlxWikiGuidesImport`,kind:`mutation`,plugin:`flx-wiki`,document:`mutation FlxWikiGuidesImport {
  flxWikiGuidesImport(input: {  }) {
    result {
      created
      updated
      skipped
    }
    errors {
      code
      message
      field
    }
  }
}`},Xa={id:`d2e33bfc675984497dcf47d59db70aa05a8a9704a6c16ab10dc7b460f4253382`,name:`FlxWikiPageEdit`,kind:`query`,plugin:`flx-wiki`,document:`query FlxWikiPageEdit($id: ID!) {
  flxWikiPage(id: $id) {
    ...FlxWikiPageRow
    markdown
  }
}

fragment FlxWikiPageRow on FlxWikiPage {
  id
  databaseId
  title
  slug
  path
  status
  summary
  excerpt
  parentId
  menuOrder
  section {
    ...FlxWikiSectionRow
  }
  url
  markdownUrl
  modified {
    iso
    label
    relative
  }
  readMinutes
  editUrl
  canEdit
}

fragment FlxWikiSectionRow on FlxWikiSection {
  databaseId
  slug
  name
  description
  order
  pageCount
  url
}`},Za={id:`438ecf2d6813eff8606ac244eed7b25027e8df9f8126c21e56be54b6f7342f95`,name:`FlxWikiPages`,kind:`query`,plugin:`flx-wiki`,document:`query FlxWikiPages($first: Int = 20, $after: String, $search: String, $statuses: [FlxWikiPageStatus!], $section: String) {
  flxWikiPages(
    first: $first
    after: $after
    search: $search
    statuses: $statuses
    section: $section
  ) {
    nodes {
      ...FlxWikiPageRow
    }
    pageInfo {
      hasNextPage
      endCursor
    }
    totalCount
  }
}

fragment FlxWikiPageRow on FlxWikiPage {
  id
  databaseId
  title
  slug
  path
  status
  summary
  excerpt
  parentId
  menuOrder
  section {
    ...FlxWikiSectionRow
  }
  url
  markdownUrl
  modified {
    iso
    label
    relative
  }
  readMinutes
  editUrl
  canEdit
}

fragment FlxWikiSectionRow on FlxWikiSection {
  databaseId
  slug
  name
  description
  order
  pageCount
  url
}`},Qa={id:`1663d5b4943b6f754178ebe061036a5405310c8c28eb1b12fe49d3975da909af`,name:`FlxWikiPageSave`,kind:`mutation`,plugin:`flx-wiki`,document:`mutation FlxWikiPageSave($input: FlxWikiPageSaveInput!) {
  flxWikiPageSave(input: $input) {
    page {
      ...FlxWikiPageRow
      markdown
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxWikiPageRow on FlxWikiPage {
  id
  databaseId
  title
  slug
  path
  status
  summary
  excerpt
  parentId
  menuOrder
  section {
    ...FlxWikiSectionRow
  }
  url
  markdownUrl
  modified {
    iso
    label
    relative
  }
  readMinutes
  editUrl
  canEdit
}

fragment FlxWikiSectionRow on FlxWikiSection {
  databaseId
  slug
  name
  description
  order
  pageCount
  url
}`},$a={id:`45ad148e9015ca73a0a7a6b29bdfcd06d601810ba382f318ba7ca618f3bc781b`,name:`FlxWikiPageTrash`,kind:`mutation`,plugin:`flx-wiki`,document:`mutation FlxWikiPageTrash($input: FlxWikiPageTrashInput!) {
  flxWikiPageTrash(input: $input) {
    trashedId
    errors {
      code
      message
      field
    }
  }
}`},eo={id:`0b8b7593708daa39f4935d849e0dcbba9c50bc5f2ebd1400ac7c45646f80a0ac`,name:`FlxWikiPreview`,kind:`mutation`,plugin:`flx-wiki`,document:`mutation FlxWikiPreview($input: FlxWikiPreviewInput!) {
  flxWikiPreview(input: $input) {
    rendered {
      html
      toc {
        level
        id
        text
      }
    }
    errors {
      code
      message
      field
    }
  }
}`},to={id:`a81958793c487ce071f1c2c4f7f0bd8dc214be127573eb1b85123934c08cebbd`,name:`FlxWikiScreen`,kind:`query`,plugin:`flx-wiki`,document:`query FlxWikiScreen {
  flxWikiOverview {
    homeUrl
    siteUrl
    base
    llmsUrl
    llmsFullUrl
    searchApi
    mcpEnabled
    mcpTools
    aiInstalled
    aiReady
    guideCount
    publishedCount
    defaultTitle
    defaultIntro
    newPageUrl
  }
  flxSettings {
    wiki {
      ...FlxWikiSettingsFields
    }
  }
  flxWikiSections {
    ...FlxWikiSectionRow
  }
}

fragment FlxWikiSectionRow on FlxWikiSection {
  databaseId
  slug
  name
  description
  order
  pageCount
  url
}

fragment FlxWikiSettingsFields on FlxSettingsWiki {
  enabled
  title
  intro
  base
  aiLinks
  editLink
  accent
  width
  nav
  toc
  ask
  askLimit
}`},no={id:`e960c92aac1edd40038d31da2506df2a0ac83b057a92d2fd3cae3af316118156`,name:`FlxWikiSectionDelete`,kind:`mutation`,plugin:`flx-wiki`,document:`mutation FlxWikiSectionDelete($input: FlxWikiSectionDeleteInput!) {
  flxWikiSectionDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},ro={id:`8f83641d5dd64bd14dedc7cb6097a02bdc1daa0e13c7b34dfda0f50d8cefc258`,name:`FlxWikiSectionSave`,kind:`mutation`,plugin:`flx-wiki`,document:`mutation FlxWikiSectionSave($input: FlxWikiSectionSaveInput!) {
  flxWikiSectionSave(input: $input) {
    section {
      ...FlxWikiSectionRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxWikiSectionRow on FlxWikiSection {
  databaseId
  slug
  name
  description
  order
  pageCount
  url
}`},io={id:`e95a30e9de1775e201bc99018b23d52eb77faed64ff768b865e5ad647509110f`,name:`FlxWikiSettingsSave`,kind:`mutation`,plugin:`flx-wiki`,document:`mutation FlxWikiSettingsSave($input: FlxSettingsUpdateInput!) {
  flxSettingsUpdate(input: $input) {
    settings {
      wiki {
        ...FlxWikiSettingsFields
      }
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxWikiSettingsFields on FlxSettingsWiki {
  enabled
  title
  intro
  base
  aiLinks
  editLink
  accent
  width
  nav
  toc
  ask
  askLimit
}`},ao={id:`69a3c84c1667538d92545f46e1c9ca1c50bee5fbb3154e584c804edbfb5b839e`,name:`FlxWikiTree`,kind:`query`,plugin:`flx-wiki`,document:`query FlxWikiTree {
  flxWikiTree {
    section {
      ...FlxWikiSectionRow
    }
    pages {
      depth
      page {
        databaseId
        title
        path
        excerpt
        url
      }
    }
  }
}

fragment FlxWikiSectionRow on FlxWikiSection {
  databaseId
  slug
  name
  description
  order
  pageCount
  url
}`};export{de as $,da as $a,di as $i,fn as $n,dr as $r,dt as $t,z as A,Hi as Aa,Hr as Ai,Ht as An,Ha as Ao,Un as Ar,He as At,Y as B,Qi as Ba,Qr as Bi,Qt as Bn,$a as Bo,Qn as Br,Xe as Bt,M as C,Fi as Ca,Fr as Ci,Ft as Cn,Fa as Co,Ln as Cr,Fe as Ct,I as D,zi as Da,zr as Di,zt as Dn,za as Do,Bn as Dr,ze as Dt,F as E,Ri as Ea,Rr as Ei,Rt as En,Ra as Eo,zn as Er,Re as Et,W as F,qi as Fa,qr as Fi,qt as Fn,qa as Fo,$ as Fr,Ye as Ft,re as G,ra as Ga,ri as Gi,rn as Gn,ro as Go,rr as Gr,it as Gt,Z as H,ea as Ha,ei as Hi,en as Hn,eo as Ho,er as Hr,et as Ht,G as I,Ji as Ia,Jr as Ii,Jt as In,Ja as Io,Jn as Ir,Ge as It,oe as J,oa as Ja,oi as Ji,sn as Jn,u as Jo,or as Jr,ot as Jt,ie as K,ia as Ka,ii as Ki,an as Kn,io as Ko,ir as Kr,nt as Kt,K as L,Yi as La,Yr as Li,Yt as Ln,Ya as Lo,Yn as Lr,qe as Lt,V as M,Wi as Ma,Wr as Mi,Wt as Mn,Wa as Mo,Gn as Mr,We as Mt,H as N,Gi as Na,Gr as Ni,Gt as Nn,Ga as No,Kn as Nr,Ke as Nt,L as O,Bi as Oa,Br as Oi,Bt as On,Ba as Oo,Vn as Or,Be as Ot,U as P,Ki as Pa,Kr as Pi,Kt as Pn,Ka as Po,qn as Pr,Je as Pt,ue as Q,ua as Qa,ui as Qi,dn as Qn,ur as Qr,ut as Qt,q as R,Xi as Ra,Xr as Ri,Xt as Rn,Xa as Ro,Xn as Rr,Ze as Rt,j as S,Pi as Sa,Pr as Si,Pt as Sn,Pa as So,In as Sr,Pe as St,P as T,Li as Ta,Lr as Ti,Lt as Tn,La as To,Rn as Tr,Le as Tt,Q as U,ta as Ua,ti as Ui,tn as Un,to as Uo,tr as Ur,tt as Ut,X as V,$i as Va,$r as Vi,$t as Vn,Za as Vo,$n as Vr,$e as Vt,ne as W,na as Wa,ni as Wi,nn as Wn,no as Wo,nr as Wr,rt as Wt,ce as X,ca as Xa,ci as Xi,ln as Xn,a as Xo,cr as Xr,ct as Xt,se as Y,sa as Ya,si as Yi,cn as Yn,i as Yo,sr as Yr,st as Yt,le as Z,la as Za,li as Zi,un as Zn,lr as Zr,lt as Zt,E as _,ki as _a,kr as _i,kt as _n,ka as _o,An as _r,ke as _t,m as a,_i as aa,_r as ai,_t as an,_a as ao,vn as ar,_e as at,k as b,ji as ba,Mr as bi,Mt as bn,Ma as bo,Nn as br,Me as bt,_ as c,bi as ca,br as ci,xt as cn,va as co,xn as cr,xe as ct,x as d,Ci as da,Cr as di,Ct as dn,Ca as do,wn as dr,Ce as dt,fi as ea,fr as ei,ft as en,fa as eo,pn as er,fe as et,b as f,wi as fa,wr as fi,wt as fn,wa as fo,Tn as fr,we as ft,w as g,Oi as ga,Or as gi,Ot as gn,Oa as go,On as gr,Oe as gt,T as h,Di as ha,Dr as hi,Dt as hn,Da as ho,kn as hr,De as ht,p as i,gi as ia,gr as ii,gt as in,ga as io,_n as ir,ge as it,B as j,Ui as ja,Ur as ji,Ut as jn,Ua as jo,Wn as jr,Ue as jt,R as k,Vi as ka,Vr as ki,Vt as kn,Va as ko,Hn as kr,Ve as kt,v as l,xi as la,xr as li,bt as ln,ya as lo,Sn as lr,Se as lt,C as m,Ei as ma,Er as mi,Et as mn,Ea as mo,Dn as mr,Ee as mt,f as n,mi as na,mr as ni,mt as nn,ma as no,hn as nr,me as nt,h as o,vi as oa,vr as oi,vt as on,xa as oo,yn as or,ve as ot,S as p,Ti as pa,Tr as pi,Tt as pn,Ta as po,En as pr,Te as pt,ae as q,aa as qa,ai as qi,on as qn,ao as qo,ar as qr,at as qt,te as r,hi as ra,hr as ri,ht as rn,ha as ro,gn as rr,he as rt,g as s,yi as sa,yr as si,yt as sn,Sa as so,bn as sr,be as st,d as t,pi as ta,pr as ti,pt as tn,pa as to,mn as tr,pe as tt,y as u,Si as ua,Sr as ui,St as un,ba as uo,Cn as ur,ye as ut,D as v,Ai as va,Ar as vi,At as vn,Aa as vo,jn as vr,je as vt,N as w,Ii as wa,Ir as wi,It as wn,Ia as wo,Fn as wr,Ie as wt,A as x,Ni as xa,Nr as xi,Nt as xn,Na as xo,Pn as xr,Ne as xt,O as y,Mi as ya,jr as yi,jt as yn,ja as yo,Mn as yr,Ae as yt,J as z,Zi as za,Zr as zi,Zt as zn,Qa as zo,Zn as zr,Qe as zt};