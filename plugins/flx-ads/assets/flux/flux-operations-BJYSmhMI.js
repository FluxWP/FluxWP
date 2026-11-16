import{J as e,K as t}from"./flux-host-CfpxPfYo.js";var n=new Set([`UNAUTHENTICATED`,`FORBIDDEN`,`NOT_FOUND`,`INVALID_INPUT`,`CONFLICT`,`RATE_LIMITED`,`INTERNAL`,`PERSISTED_QUERY_NOT_FOUND`,`QUERY_NOT_ALLOWED`,`NETWORK`,`BAD_RESPONSE`]),r={401:`UNAUTHENTICATED`,403:`FORBIDDEN`,404:`NOT_FOUND`,429:`RATE_LIMITED`},i=class extends Error{constructor(t,i,a,o){let s=a[0];super(s?.message||o||`GraphQL request failed`),e(this,`code`,void 0),e(this,`field`,void 0),e(this,`errors`,void 0),e(this,`status`,void 0),e(this,`operation`,void 0),this.name=`FlxGraphQLError`,this.operation=t,this.status=i,this.errors=a;let c=s?.extensions?.code;this.code=c&&n.has(c)?c:o??r[i]??`INTERNAL`,this.field=typeof s?.extensions?.field==`string`?s.extensions.field:void 0}},a=e=>e instanceof i,o=`../graphql`,s=2e3,c=e=>Array.isArray(e)&&e.every(e=>typeof e==`object`&&!!e&&typeof e.message==`string`);function l(e){let n=e.persisted!==!1;async function r(t,n,r,i){let a=n&&typeof n==`object`&&Object.keys(n).length?n:void 0,c={raw:!0,throwOnError:!1,signal:i.signal,headers:{"X-Flux-GraphQL-Operation":t.name,...i.headers}};if(t.kind===`query`&&!r){let n={queryId:t.id,operationName:t.name,variables:a?JSON.stringify(a):void 0};if(Object.values(n).reduce((e,t)=>e+(t?encodeURIComponent(t).length+16:0),0)<s)return e.api(o,{...c,method:`GET`,query:n})}let l=r?{query:t.document,operationName:t.name,variables:a}:{queryId:t.id,operationName:t.name,variables:a};return e.api(o,{...c,method:`POST`,body:l})}async function a(e,...a){let o=a[0],s=a[1]??{},l;try{l=await r(e,o,!n,s),n&&l.data?.errors?.some(e=>e.extensions?.code===`PERSISTED_QUERY_NOT_FOUND`)&&(l=await r(e,o,!0,s))}catch(n){throw n instanceof t?new i(e.name,n.status,[{message:n.message}],n.code===`invalid_json`?`BAD_RESPONSE`:`NETWORK`):n}let u=l.data,d=c(u?.errors)?u.errors:[],f=u?.data;if(typeof f!=`object`||!f)throw new i(e.name,l.status,d.length?d:[{message:`Empty GraphQL response`}],d.length?void 0:`BAD_RESPONSE`);if(d.length){let t=new Set(d.map(e=>e.path?.[0]).filter(e=>typeof e==`string`));if([...t].some(e=>f[e]===null)||!t.size)throw new i(e.name,l.status,d)}return f}return{request:a}}var u={id:`15576c276018086831114da1ff501c31ce16011c5c4d42a30db693cc098ad75b`,name:`FlxAdsAudienceCreate`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsAudienceCreate($input: FlxAdsAudienceCreateInput!) {
  flxAdsAudienceCreate(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},d={id:`8e00b2b06e97bddea533211028e4625fbe614cdeff5df60b79721a1bf3a447f8`,name:`FlxAdsAudienceDelete`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsAudienceDelete($input: FlxAdsAudienceDeleteInput!) {
  flxAdsAudienceDelete(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},f={id:`e331debbfac30bc1a1933d4c25decd870765d7d48be844c20f11584aa18854c4`,name:`FlxAdsAudienceLookalike`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsAudienceLookalike($input: FlxAdsAudienceLookalikeInput!) {
  flxAdsAudienceLookalike(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},ee={id:`37c074f16556d30dd0de810739258178dbf8ebd7621a828ae9f001843a4b600c`,name:`FlxAdsAudiences`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsAudiences {
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
}`},p={id:`ff4ef120d9023d952ce2d8437b3aa7f42ba9445c11452521e7a9f657ed37cdf0`,name:`FlxAdsAudienceSync`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsAudienceSync($input: FlxAdsAudienceSyncInput!) {
  flxAdsAudienceSync(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},m={id:`703996eb7a33f7182b5e7467dd8a5c6b40efe23f63ceda425587a57cbcf9f643`,name:`FlxAdsCampaign`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsCampaign($id: String!, $range: String) {
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
}`},h={id:`7ecd311b8a7e72f9192857c757cd218bc834ec7ffc3d6a498f99fde98c124c54`,name:`FlxAdsCampaignBudget`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsCampaignBudget($input: FlxAdsCampaignBudgetInput!) {
  flxAdsCampaignBudget(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},g={id:`53a30b5fbd31c8b762b44748f6ff3bc66fd2501fceece1d2d19f4bfefc14296a`,name:`FlxAdsCampaigns`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsCampaigns($range: String) {
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
}`},_={id:`f4fc0945c371238c1d09e1e55c05d2104f3c6ac94ba5ad3df1ebb799eb5ae1bd`,name:`FlxAdsCampaignStatus`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsCampaignStatus($input: FlxAdsCampaignStatusInput!) {
  flxAdsCampaignStatus(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},v={id:`9366271e73db1168ecb85a047953c4758cbe2dd3a4c91b3cd7fdcbd2f3f79e18`,name:`FlxAdsChangeHistory`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsChangeHistory($limit: Int) {
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
}`},y={id:`c862bf2e2dfad06e84f85be950d8696400d6faa25ba3480c36283bcface3e422`,name:`FlxAdsConnectionTest`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsConnectionTest($input: FlxAdsConnectionTestInput!) {
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
}`},b={id:`683c3cf442655c7a16dcb6d4f80e8e34076f2d19cc410f7bf1b53f473be0c5d6`,name:`FlxAdsContext`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsContext {
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
}`},x={id:`6c177b16c9b3bc709cd8b09885247c84de6832111cc6cf5bf2c628028e13a325`,name:`FlxAdsDraft`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsDraft($id: String!) {
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
}`},S={id:`c997b63c709ba8710ac23ba497313e41cd5eee794dfc2325e7a260ec4c663148`,name:`FlxAdsDraftDelete`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsDraftDelete($input: FlxAdsDraftDeleteInput!) {
  flxAdsDraftDelete(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},C={id:`89515abbbb3f4bf1d730ff46dab90bd993a25a75f787d3d80828f561f64922e2`,name:`FlxAdsDraftDuplicate`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsDraftDuplicate($input: FlxAdsDraftDuplicateInput!) {
  flxAdsDraftDuplicate(input: $input) {
    id
    errors {
      code
      message
      field
    }
  }
}`},w={id:`5ceedef8952188c75b1fb5538e26d371371a6d0f40200f3828a72655d1927b49`,name:`FlxAdsDraftGenerate`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsDraftGenerate($input: FlxAdsDraftGenerateInput!) {
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
}`},T={id:`7879833baa361069f8eca473c5b924722775b589b7abfeaf8b404ca2a3cb76e0`,name:`FlxAdsDraftSave`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsDraftSave($input: FlxAdsDraftSaveInput!) {
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
}`},E={id:`0430cfde4021f60d470591cea01d3f32d9018718afc438e364d879f2b9ce05a3`,name:`FlxAdsDraftSend`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsDraftSend($input: FlxAdsDraftSendInput!) {
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
}`},D={id:`80e7b7ac54b372c91d5f722cc70b41eabf6ea670420a9cfd1f51b79ae2ea7fee`,name:`FlxAdsExperimentChoices`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsExperimentChoices {
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
}`},O={id:`033461a44cc988fdecba7a868f94beeae673e05e99e6a0f651dcc6f8ddf15cde`,name:`FlxAdsExperimentCreate`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsExperimentCreate($input: FlxAdsExperimentCreateInput!) {
  flxAdsExperimentCreate(input: $input) {
    id
    errors {
      code
      message
      field
    }
  }
}`},k={id:`b4802892f86bf8733e5fc2ecb0b9d2ac6424cb559f383444df60206dbea03941`,name:`FlxAdsExperimentDelete`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsExperimentDelete($input: FlxAdsExperimentDeleteInput!) {
  flxAdsExperimentDelete(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},A={id:`c6afed85d499801f21da2e4e8d69e4a9c7369bcfe7a5f3559725e49ec45c846b`,name:`FlxAdsExperimentFinish`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsExperimentFinish($input: FlxAdsExperimentFinishInput!) {
  flxAdsExperimentFinish(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},j={id:`69240b9776427bfac198af5f47566fdccc7671ccac938c9659fc647594b8f82d`,name:`FlxAdsExperimentPauseLoser`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsExperimentPauseLoser($input: FlxAdsExperimentPauseLoserInput!) {
  flxAdsExperimentPauseLoser(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},M={id:`a3f180f5122d49e555d7df40fa228d92e2b708ca41c4a91320a077bdeda9b4fa`,name:`FlxAdsExperiments`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsExperiments {
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
}`},N={id:`5d9d75833ba1fb533a368cd8154d472de9ffe0d605789f804ccc81ebf680b89c`,name:`FlxAdsHeartbeat`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsHeartbeat($input: FlxAdsHeartbeatInput!) {
  flxAdsHeartbeat(input: $input) {
    ok
    openSuggestions
    errors {
      code
      message
      field
    }
  }
}`},P={id:`e0f9c37d4069b16c70279171e21bb762df8274cb70cf6d2e92170a46624d8530`,name:`FlxAdsLinkBuilder`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsLinkBuilder {
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
}`},F={id:`19a468a8f6f5ffb5c6da168b2982608ae7d88d44e17d13e372769a1281a300ea`,name:`FlxAdsReport`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsReport($filter: FlxAdsFilterInput) {
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
}`},I={id:`4d8b9511f7b03e83474289c5bc7ad01f1a223b20e72587dd16d1c9f2cb5f5c5f`,name:`FlxAdsSeasonAdd`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSeasonAdd($input: FlxAdsSeasonAddInput!) {
  flxAdsSeasonAdd(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},L={id:`78a9c32a2cdfe9c5913dbb26ef15562d0b4ccd3a9415e2fe663a555a09d4b631`,name:`FlxAdsSeasonDelete`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSeasonDelete($input: FlxAdsSeasonDeleteInput!) {
  flxAdsSeasonDelete(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},R={id:`dc07f0cade5a2084311ae381d5099ea62c1550aa997fcd471197ccba702f25b8`,name:`FlxAdsSeasons`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsSeasons {
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
}`},z={id:`c9356d278116835ba38b6650ff12d25e1e61e19d6fae07a5c67f5fc6a7e258dd`,name:`FlxAdsSendLog`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsSendLog {
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
}`},B={id:`f249dbddc4f9f35a4d800d5b883d9f9b403621c899975f4afc4b8868c9f98f0b`,name:`FlxAdsSendRetry`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSendRetry($input: FlxAdsSendRetryInput!) {
  flxAdsSendRetry(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},te={id:`79cffe4095d5387aec2dc85c9ee353e8d208749df66181b7062c27e869c1f3ab`,name:`FlxAdsSettings`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsSettings {
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
}`},V={id:`24a9e8646c7d2467c1f9ef0daf4c100a2a4df6971c33343e297b4ecf7582d281`,name:`FlxAdsSettingsSave`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSettingsSave($input: FlxAdsSettingsSaveInput!) {
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
}`},H={id:`b7ca184a98d1ee03a35d28f0ff539562795c566ae0e48eec7bc07669e9363747`,name:`FlxAdsSpend`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsSpend($filter: FlxAdsFilterInput) {
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
}`},U={id:`3bd3940ea9c4e06b7dbf33031290ab8d9e6ed4fec4b0be7297d064b37f525b0c`,name:`FlxAdsSpendAdd`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSpendAdd($input: FlxAdsSpendAddInput!) {
  flxAdsSpendAdd(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},W={id:`4c7a66d6698c203e77643a5486d518bfaab14776558bfa6a7c67ec9a695c2768`,name:`FlxAdsSpendDelete`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSpendDelete($input: FlxAdsSpendDeleteInput!) {
  flxAdsSpendDelete(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},G={id:`321a15204f3a2eeafee102500c9f05985fc678551b77b27bb41ef41ea67ca0c2`,name:`FlxAdsSpendSync`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSpendSync($input: FlxAdsSpendSyncInput!) {
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
}`},K={id:`6402f8d915ab7a970d1096ef12af62931ed8c50029a1f5490a106a629e625e4b`,name:`FlxAdsStudio`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsStudio {
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
}`},q={id:`7d8a60a058ce4b48e7047831db86f4ece1d4aa76a90a5598362e7ffdb1830669`,name:`FlxAdsSuggestionApply`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSuggestionApply($input: FlxAdsSuggestionApplyInput!) {
  flxAdsSuggestionApply(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},J={id:`f69a419a23d70261401dc4bc190e759a71413b427c8c58e9e490e32502d1c49a`,name:`FlxAdsSuggestionDismiss`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSuggestionDismiss($input: FlxAdsSuggestionDismissInput!) {
  flxAdsSuggestionDismiss(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},Y={id:`0d11b8f455f8a16515111047721b1dfebd7b70f52034cc85ad2c98efdd781972`,name:`FlxAdsSuggestions`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsSuggestions {
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
}`},X={id:`3311e69615d4f9a6891a6a199712bac121e9397e58daee03f7e4935d5caec43d`,name:`FlxAdsSuggestionsRefresh`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSuggestionsRefresh($input: FlxAdsSuggestionsRefreshInput!) {
  flxAdsSuggestionsRefresh(input: $input) {
    newCount
    errors {
      code
      message
      field
    }
  }
}`},Z={id:`e89bbd8ee4c7bf09dcfb37612cbe5859fc64666e6e91b3d6ac45fe9826e684aa`,name:`FlxAdsValue`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsValue($filter: FlxAdsFilterInput) {
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
}`},Q={id:`14ae18c34134e93d807ec1e3d21907a4c002555d4d327011a6cbdd05c13743ab`,name:`FlxLanguageSettingsScreen`,kind:`query`,plugin:`flx-lang`,document:`query FlxLanguageSettingsScreen {
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
}`},ne={id:`0da4e1f193deec851174ea768e4699d956a94e7670b172e44dafe5bf9f788a52`,name:`FlxLanguageSettingsUpdate`,kind:`mutation`,plugin:`flx-lang`,document:`mutation FlxLanguageSettingsUpdate($input: FlxLanguageSettingsUpdateInput!) {
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
}`},re={id:`21a336969a0dc41b8caaa1be0a20b9bd740359347ff57bc8cb72f70ca6254f9e`,name:`FlxRoleCreate`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleCreate($input: FlxRoleCreateInput!) {
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
}`},ie={id:`2bd5579ac1188eadb0dff5235840b66098c68b1b0d9e5d9a86c1a07b54d3acea`,name:`FlxRoleDelete`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleDelete($input: FlxRoleDeleteInput!) {
  flxRoleDelete(input: $input) {
    deletedKey
    errors {
      code
      message
      field
    }
  }
}`},ae={id:`af09ea3f7594b6e37e1aa272f116e57dd7079ca275a754a8107fc230ce3014df`,name:`FlxRoleMemberAdd`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleMemberAdd($input: FlxRoleMemberAddInput!) {
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
}`},oe={id:`6eab4d6f1f97457a8679a9b1dd92d47dc2e01a7f41163eff439dc854213b2a7c`,name:`FlxRoleMemberRemove`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleMemberRemove($input: FlxRoleMemberRemoveInput!) {
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
}`},se={id:`067ae709ce0675a0f8e8ab3ab7d21d722afa9f7449f358767be373133c98de6f`,name:`FlxRoleMembers`,kind:`query`,plugin:`flx-roles`,document:`query FlxRoleMembers($key: String!, $first: Int = 50, $after: String) {
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
}`},ce={id:`20e6cdbb88f7639a8749e74cf75ef58a4ab704143f0cf0fa3474dbb2366cb1d0`,name:`FlxRoleReset`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleReset($input: FlxRoleResetInput!) {
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
}`},le={id:`0a4da13faad85546b92fab5b45716dab5b06e87791475a9dfaa4664888375658`,name:`FlxRolesSave`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRolesSave($input: FlxRolesSaveInput!) {
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
}`},ue={id:`2c22565866bcebf0aaed510b75bec73444fc8257c8d2b327873e9c2963c59030`,name:`FlxRolesScreen`,kind:`query`,plugin:`flx-roles`,document:`query FlxRolesScreen($members: Int = 50) {
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
}`},de={id:`fca51d9372f393736cd5c7f3cc3a6c6f76bc42c714907f05360f59ce43283d8b`,name:`FlxTaskCommentCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCommentCreate($input: FlxTaskCommentCreateInput!) {
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
}`},fe={id:`f4712b3032ffaf4580393cf0e6234154fa1eb66399b12784aa29f29b625d34bd`,name:`FlxTaskCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCreate($input: FlxTaskCreateInput!) {
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
}`},pe={id:`157053946bdf55aa4bf5f22a676119ec48e7bbe04c053867b7e0eedb11e04ec2`,name:`FlxTaskDelete`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskDelete($input: FlxTaskDeleteInput!) {
  flxTaskDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},$={id:`fa1d3f71ca31ab2a7b72c1a987e295f209d6564e8a68e3643dee354a44544ecf`,name:`FlxTaskDetail`,kind:`query`,plugin:`flx-tasks`,document:`query FlxTaskDetail($id: ID!) {
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
}`},me={id:`d357c8fdb6c372508b17bb93fa22ec80224c0ff43854104149816d70a9e80870`,name:`FlxTaskNotificationsRead`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskNotificationsRead($input: FlxTaskNotificationsReadInput!) {
  flxTaskNotificationsRead(input: $input) {
    unread
    errors {
      code
      message
      field
    }
  }
}`},he={id:`004e85b9237b995957f75c2cfe2a996f5d2de6ca98b31c98511cb403df800ae3`,name:`FlxTaskUpdate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdate($input: FlxTaskUpdateInput!) {
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
}`},ge={id:`7c644e53f296f0dbf859b7bfcb4748e8c5be3ef891474fb47a262a2cd993be7a`,name:`FlxTaskUpdateDetail`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdateDetail($input: FlxTaskUpdateInput!) {
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
}`},_e={id:`a2c919c298da71f1634726a7c85c4344cf85f3602cbf54e4175bd8e45578e9b5`,name:`FlxTaskWatch`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskWatch($input: FlxTaskWatchInput!) {
  flxTaskWatch(input: $input) {
    watcherIds
    errors {
      code
      message
      field
    }
  }
}`};export{le as $,R as A,q as B,j as C,F as D,P as E,H as F,Q as G,Y as H,U as I,ie as J,ne as K,W as L,B as M,te as N,I as O,V as P,ce as Q,G as R,A as S,N as T,X as U,J as V,Z as W,oe as X,ae as Y,se as Z,T as _,ee as a,me as at,O as b,_ as c,_e as ct,y as d,a as dt,ue as et,b as f,w as g,C as h,p as i,$ as it,z as j,L as k,g as l,l as lt,S as m,d as n,fe as nt,m as o,he as ot,x as p,re as q,f as r,pe as rt,h as s,ge as st,u as t,de as tt,v as u,i as ut,E as v,M as w,k as x,D as y,K as z};