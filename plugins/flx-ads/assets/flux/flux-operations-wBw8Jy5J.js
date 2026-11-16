import{$ as e,tt as t}from"./flux-host-Cs8oN1tZ.js";var n=new Set([`UNAUTHENTICATED`,`FORBIDDEN`,`NOT_FOUND`,`INVALID_INPUT`,`CONFLICT`,`RATE_LIMITED`,`INTERNAL`,`PERSISTED_QUERY_NOT_FOUND`,`QUERY_NOT_ALLOWED`,`NETWORK`,`BAD_RESPONSE`]),r={401:`UNAUTHENTICATED`,403:`FORBIDDEN`,404:`NOT_FOUND`,429:`RATE_LIMITED`},i=class extends Error{constructor(e,i,a,o){let s=a[0];super(s?.message||o||`GraphQL request failed`),t(this,`code`,void 0),t(this,`field`,void 0),t(this,`errors`,void 0),t(this,`status`,void 0),t(this,`operation`,void 0),this.name=`FlxGraphQLError`,this.operation=e,this.status=i,this.errors=a;let c=s?.extensions?.code;this.code=c&&n.has(c)?c:o??r[i]??`INTERNAL`,this.field=typeof s?.extensions?.field==`string`?s.extensions.field:void 0}},a=e=>e instanceof i,o=`../graphql`,s=2e3,c=e=>Array.isArray(e)&&e.every(e=>typeof e==`object`&&!!e&&typeof e.message==`string`);function l(t){let n=t.persisted!==!1;async function r(e,n,r,i){let a=n&&typeof n==`object`&&Object.keys(n).length?n:void 0,c={raw:!0,throwOnError:!1,signal:i.signal,headers:{"X-Flux-GraphQL-Operation":e.name,...i.headers}};if(e.kind===`query`&&!r){let n={queryId:e.id,operationName:e.name,variables:a?JSON.stringify(a):void 0};if(Object.values(n).reduce((e,t)=>e+(t?encodeURIComponent(t).length+16:0),0)<s)return t.api(o,{...c,method:`GET`,query:n})}let l=r?{query:e.document,operationName:e.name,variables:a}:{queryId:e.id,operationName:e.name,variables:a};return t.api(o,{...c,method:`POST`,body:l})}async function a(t,...a){let o=a[0],s=a[1]??{},l;try{l=await r(t,o,!n,s),n&&l.data?.errors?.some(e=>e.extensions?.code===`PERSISTED_QUERY_NOT_FOUND`)&&(l=await r(t,o,!0,s))}catch(n){throw n instanceof e?new i(t.name,n.status,[{message:n.message}],n.code===`invalid_json`?`BAD_RESPONSE`:`NETWORK`):n}let u=l.data,d=c(u?.errors)?u.errors:[],f=u?.data;if(typeof f!=`object`||!f)throw new i(t.name,l.status,d.length?d:[{message:`Empty GraphQL response`}],d.length?void 0:`BAD_RESPONSE`);if(d.length){let e=new Set(d.map(e=>e.path?.[0]).filter(e=>typeof e==`string`));if([...e].some(e=>f[e]===null)||!e.size)throw new i(t.name,l.status,d)}return f}return{request:a}}var u={id:`15576c276018086831114da1ff501c31ce16011c5c4d42a30db693cc098ad75b`,name:`FlxAdsAudienceCreate`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsAudienceCreate($input: FlxAdsAudienceCreateInput!) {
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
}`},te={id:`ff4ef120d9023d952ce2d8437b3aa7f42ba9445c11452521e7a9f657ed37cdf0`,name:`FlxAdsAudienceSync`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsAudienceSync($input: FlxAdsAudienceSyncInput!) {
  flxAdsAudienceSync(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},p={id:`703996eb7a33f7182b5e7467dd8a5c6b40efe23f63ceda425587a57cbcf9f643`,name:`FlxAdsCampaign`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsCampaign($id: String!, $range: String) {
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
}`},m={id:`7ecd311b8a7e72f9192857c757cd218bc834ec7ffc3d6a498f99fde98c124c54`,name:`FlxAdsCampaignBudget`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsCampaignBudget($input: FlxAdsCampaignBudgetInput!) {
  flxAdsCampaignBudget(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},h={id:`53a30b5fbd31c8b762b44748f6ff3bc66fd2501fceece1d2d19f4bfefc14296a`,name:`FlxAdsCampaigns`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsCampaigns($range: String) {
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
}`},g={id:`f4fc0945c371238c1d09e1e55c05d2104f3c6ac94ba5ad3df1ebb799eb5ae1bd`,name:`FlxAdsCampaignStatus`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsCampaignStatus($input: FlxAdsCampaignStatusInput!) {
  flxAdsCampaignStatus(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},_={id:`9366271e73db1168ecb85a047953c4758cbe2dd3a4c91b3cd7fdcbd2f3f79e18`,name:`FlxAdsChangeHistory`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsChangeHistory($limit: Int) {
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
}`},v={id:`c862bf2e2dfad06e84f85be950d8696400d6faa25ba3480c36283bcface3e422`,name:`FlxAdsConnectionTest`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsConnectionTest($input: FlxAdsConnectionTestInput!) {
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
}`},y={id:`683c3cf442655c7a16dcb6d4f80e8e34076f2d19cc410f7bf1b53f473be0c5d6`,name:`FlxAdsContext`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsContext {
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
}`},b={id:`6c177b16c9b3bc709cd8b09885247c84de6832111cc6cf5bf2c628028e13a325`,name:`FlxAdsDraft`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsDraft($id: String!) {
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
}`},x={id:`c997b63c709ba8710ac23ba497313e41cd5eee794dfc2325e7a260ec4c663148`,name:`FlxAdsDraftDelete`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsDraftDelete($input: FlxAdsDraftDeleteInput!) {
  flxAdsDraftDelete(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},S={id:`89515abbbb3f4bf1d730ff46dab90bd993a25a75f787d3d80828f561f64922e2`,name:`FlxAdsDraftDuplicate`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsDraftDuplicate($input: FlxAdsDraftDuplicateInput!) {
  flxAdsDraftDuplicate(input: $input) {
    id
    errors {
      code
      message
      field
    }
  }
}`},C={id:`5ceedef8952188c75b1fb5538e26d371371a6d0f40200f3828a72655d1927b49`,name:`FlxAdsDraftGenerate`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsDraftGenerate($input: FlxAdsDraftGenerateInput!) {
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
}`},w={id:`7879833baa361069f8eca473c5b924722775b589b7abfeaf8b404ca2a3cb76e0`,name:`FlxAdsDraftSave`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsDraftSave($input: FlxAdsDraftSaveInput!) {
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
}`},T={id:`0430cfde4021f60d470591cea01d3f32d9018718afc438e364d879f2b9ce05a3`,name:`FlxAdsDraftSend`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsDraftSend($input: FlxAdsDraftSendInput!) {
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
}`},E={id:`80e7b7ac54b372c91d5f722cc70b41eabf6ea670420a9cfd1f51b79ae2ea7fee`,name:`FlxAdsExperimentChoices`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsExperimentChoices {
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
}`},D={id:`033461a44cc988fdecba7a868f94beeae673e05e99e6a0f651dcc6f8ddf15cde`,name:`FlxAdsExperimentCreate`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsExperimentCreate($input: FlxAdsExperimentCreateInput!) {
  flxAdsExperimentCreate(input: $input) {
    id
    errors {
      code
      message
      field
    }
  }
}`},O={id:`b4802892f86bf8733e5fc2ecb0b9d2ac6424cb559f383444df60206dbea03941`,name:`FlxAdsExperimentDelete`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsExperimentDelete($input: FlxAdsExperimentDeleteInput!) {
  flxAdsExperimentDelete(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},k={id:`c6afed85d499801f21da2e4e8d69e4a9c7369bcfe7a5f3559725e49ec45c846b`,name:`FlxAdsExperimentFinish`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsExperimentFinish($input: FlxAdsExperimentFinishInput!) {
  flxAdsExperimentFinish(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},A={id:`69240b9776427bfac198af5f47566fdccc7671ccac938c9659fc647594b8f82d`,name:`FlxAdsExperimentPauseLoser`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsExperimentPauseLoser($input: FlxAdsExperimentPauseLoserInput!) {
  flxAdsExperimentPauseLoser(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},j={id:`a3f180f5122d49e555d7df40fa228d92e2b708ca41c4a91320a077bdeda9b4fa`,name:`FlxAdsExperiments`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsExperiments {
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
}`},M={id:`5d9d75833ba1fb533a368cd8154d472de9ffe0d605789f804ccc81ebf680b89c`,name:`FlxAdsHeartbeat`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsHeartbeat($input: FlxAdsHeartbeatInput!) {
  flxAdsHeartbeat(input: $input) {
    ok
    openSuggestions
    errors {
      code
      message
      field
    }
  }
}`},N={id:`e0f9c37d4069b16c70279171e21bb762df8274cb70cf6d2e92170a46624d8530`,name:`FlxAdsLinkBuilder`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsLinkBuilder {
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
}`},P={id:`19a468a8f6f5ffb5c6da168b2982608ae7d88d44e17d13e372769a1281a300ea`,name:`FlxAdsReport`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsReport($filter: FlxAdsFilterInput) {
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
}`},F={id:`4d8b9511f7b03e83474289c5bc7ad01f1a223b20e72587dd16d1c9f2cb5f5c5f`,name:`FlxAdsSeasonAdd`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSeasonAdd($input: FlxAdsSeasonAddInput!) {
  flxAdsSeasonAdd(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},I={id:`78a9c32a2cdfe9c5913dbb26ef15562d0b4ccd3a9415e2fe663a555a09d4b631`,name:`FlxAdsSeasonDelete`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSeasonDelete($input: FlxAdsSeasonDeleteInput!) {
  flxAdsSeasonDelete(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},L={id:`dc07f0cade5a2084311ae381d5099ea62c1550aa997fcd471197ccba702f25b8`,name:`FlxAdsSeasons`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsSeasons {
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
}`},R={id:`c9356d278116835ba38b6650ff12d25e1e61e19d6fae07a5c67f5fc6a7e258dd`,name:`FlxAdsSendLog`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsSendLog {
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
}`},z={id:`f249dbddc4f9f35a4d800d5b883d9f9b403621c899975f4afc4b8868c9f98f0b`,name:`FlxAdsSendRetry`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSendRetry($input: FlxAdsSendRetryInput!) {
  flxAdsSendRetry(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},B={id:`79cffe4095d5387aec2dc85c9ee353e8d208749df66181b7062c27e869c1f3ab`,name:`FlxAdsSettings`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsSettings {
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
}`},Q={id:`1dd252522e2e7e352cbe31ebb7ef763661f7f52d407b0023f418e1365e0d5aa8`,name:`FlxAppAccess`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAccess($all: Boolean) {
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
}`},ne={id:`0410fe39ca78d5cd046a033c326dd6bd357096aaab36212f8c0f44f9056de2c1`,name:`FlxAppAccessRevoke`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAccessRevoke($input: FlxAppAccessRevokeInput!) {
  flxAppAccessRevoke(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},re={id:`1860c58a8fdc3e9a90238fafaef5f1ecdc4bcf298ce323ef674dd35eefbe2e6c`,name:`FlxAppAdminAppearance`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminAppearance {
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
}`},ie={id:`762de9d5115d73d8f01452c7b5f8a8ac15213df2102f3986d735018483a7670b`,name:`FlxAppAdminAppearanceSave`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminAppearanceSave($input: FlxAppAdminAppearanceSaveInput!) {
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
}`},ae={id:`14ba5b9cfa34c05e5bef9a80d5087b56019d5b4c4a5a22ce7810d12dd6089f8c`,name:`FlxAppAdminClientCreate`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminClientCreate($input: FlxAppAdminClientCreateInput!) {
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
}`},oe={id:`0acad378aa0aa39d0d878475c0e4c2c5e8a5a09045e3bd946641c722c6b0f44b`,name:`FlxAppAdminClientDelete`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminClientDelete($input: FlxAppAdminClientDeleteInput!) {
  flxAppAdminClientDelete(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},se={id:`0aa8eaf58f40a410644147b5f052aa2441f94a7c247af742f281bd5590f5b61f`,name:`FlxAppAdminClients`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminClients {
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
}`},ce={id:`67b0edec2f4ef8fb57c5f796df9e32ec167f35dae7bec4196df4edcdff650a82`,name:`FlxAppAdminMenu`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminMenu {
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
}`},le={id:`e27de38feec02d07f97aa0a6cee1f3782bf39e4b41f0fd3cf84edb52d04a7d74`,name:`FlxAppAdminMobile`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminMobile {
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
}`},ue={id:`3140882c7e64b03a33a9bdeaab1dc3b5eb7c50ba5709a2a95778672679795ae2`,name:`FlxAppAdminMobileSave`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminMobileSave($input: FlxAppAdminMobileSaveInput!) {
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
}`},de={id:`23953eab015437dc0f727a62ee3d73cb1e6cb45be22fc7f6b79e101318571d10`,name:`FlxAppAdminModules`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminModules {
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
}`},fe={id:`a7aad76ad18dbda2567f29ad4d8b488efe4043659ae782a8d0f92eefba54349c`,name:`FlxAppAdminModulesSave`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminModulesSave($input: FlxAppAdminModulesSaveInput!) {
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
}`},pe={id:`b9dddc6a4c12070645fd30a6fbdf39863d2b58ea1cbe71f7ebb5ee2db6bc9b3a`,name:`FlxAppAdminPushTest`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminPushTest {
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
}`},me={id:`b7d4bf6833511227a38948aef18cf38f05a29043d4dbfc3b04e49fcc1878ec06`,name:`FlxAppAdminSettings`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminSettings {
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
}`},he={id:`fc53aca192620408e9133dd653741e741a10c7d19ada18e38845f088b88e1788`,name:`FlxAppAdminSettingsSave`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminSettingsSave($input: FlxAppAdminSettingsSaveInput!) {
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
}`},ge={id:`9f61478d3bc50857048da2499c97ed5de0bdc51362012d2153e2363175cb7d37`,name:`FlxAppDeviceRevoke`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppDeviceRevoke($input: FlxAppDeviceRevokeInput!) {
  flxAppDeviceRevoke(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},_e={id:`49577b1d3b994160a8f4f89898086f3ffb70db6242a809e48b3515c7268d6e21`,name:`FlxAppKeyCreate`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppKeyCreate($input: FlxAppKeyCreateInput!) {
  flxAppKeyCreate(input: $input) {
    key
    errors {
      code
      message
      field
    }
  }
}`},ve={id:`0cdd28e36785f9a36c07bcff67a19c55e28b55316009f469fc97857134e4a2b4`,name:`FlxBlocksBlockTypeSetActive`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksBlockTypeSetActive($input: FlxBlocksBlockTypeSetActiveInput!) {
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
}`},ye={id:`a707f69c55e13b00163c2d5f835704b3a9234102fefdc59f7e9bf0e1861e5d87`,name:`FlxBlocksDashboard`,kind:`query`,plugin:`flx-blocks`,document:`query FlxBlocksDashboard {
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
}`},be={id:`ee8041abf4f646b0db2b506f7ede107c7bf199332d81ac3023f8a0e204408ad3`,name:`FlxBlocksLibraryPatterns`,kind:`query`,plugin:`flx-blocks`,document:`query FlxBlocksLibraryPatterns($source: ID, $category: String, $search: String, $first: Int = 24, $after: String, $refresh: Boolean) {
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
}`},xe={id:`340869fd203dcf45e0db366bded93136560b5e3c7b8ee4e7f0efce9a99a933d1`,name:`FlxBlocksLibraryShare`,kind:`query`,plugin:`flx-blocks`,document:`query FlxBlocksLibraryShare {
  flxBlocksLibraryShare {
    ...FlxBlocksShareFields
  }
}

fragment FlxBlocksShareFields on FlxBlocksLibraryShare {
  enabled
  key
  url
}`},Se={id:`a3ae64b495e2c2a1dc0d327b08e2df372ccea65c7c1827f676f6445bd7356177`,name:`FlxBlocksLibraryShareUpdate`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksLibraryShareUpdate($input: FlxBlocksLibraryShareUpdateInput!) {
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
}`},Ce={id:`aab0d512355bb050e77a897874c870786b849b10ce29b327b1d353869d17292c`,name:`FlxBlocksLibrarySourceAdd`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksLibrarySourceAdd($input: FlxBlocksLibrarySourceAddInput!) {
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
}`},we={id:`5406d45161cd50d86861128e261f0716f087150902c42c151688c7ad997ab317`,name:`FlxBlocksLibrarySourceRemove`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksLibrarySourceRemove($input: FlxBlocksLibrarySourceRemoveInput!) {
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
}`},Te={id:`cebc38b2c4a42ddcdabe0145d488d587190ee259688daac8f0b2ea1e7599d82e`,name:`FlxBlocksPatternPreview`,kind:`query`,plugin:`flx-blocks`,document:`query FlxBlocksPatternPreview($id: String!, $source: ID) {
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
}`},Ee={id:`c22bde71875f8c21a1d1477eb43dad65257b059c911c8ad41284ffe3ba8c18e8`,name:`FlxBlocksSettingsUpdate`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksSettingsUpdate($input: FlxBlocksSettingsUpdateInput!) {
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
}`},De={id:`7a2703962a923dbd66bbb68ae1b67a23777d1f64cd8eebb3f7f7e3ac839725c7`,name:`FlxCrmContact`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmContact($id: ID!) {
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
}`},Oe={id:`d64b39e87439228efb39cf454ec4b8156cf3e0fd2704751e0c30f58a4d4e8698`,name:`FlxCrmContactBulk`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactBulk($input: FlxContactBulkInput!) {
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
}`},ke={id:`1ddbb8969d399e686efebc66dfd2669494b2a20c18beffe637a9d26bba6c9e94`,name:`FlxCrmContactCreate`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactCreate($input: FlxContactCreateInput!) {
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
}`},Ae={id:`e5ac09b6da29cd8a29870ad1eeecceb4e0930a083afacb5cb0f0823727443497`,name:`FlxCrmContactDelete`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactDelete($input: FlxContactDeleteInput!) {
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
}`},je={id:`5b37df020f5fcf7eb644d59e7d43255a10daf12b3e1e81becfe80e6be2b63666`,name:`FlxCrmContactMerge`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactMerge($input: FlxContactMergeInput!) {
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
}`},Me={id:`78fef294f41cc0264573687c40208fb3d18ade9ed42cf1d4328c25414ceb2aa3`,name:`FlxCrmContacts`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmContacts($filter: FlxContactFilterInput, $orderBy: FlxContactOrderBy, $order: FlxContactOrder, $first: Int = 25, $after: String) {
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
}`},Ne={id:`5c13b0d3b065e0dd85f87fc9e53cbb77e20f0338223bcdd15c5a57a504623083`,name:`FlxCrmContactUpdate`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactUpdate($input: FlxContactUpdateInput!) {
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
}`},Pe={id:`d8cbff1ab3075cba9ae5ea057a6d68195e6aaa27a724bbf9c924efa5974873cd`,name:`FlxCrmDuplicates`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmDuplicates {
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
}`},Fe={id:`fe10d4a31eedbbd448e3769a47a190a8872c2bdad2256def5f7e54cdf091eae0`,name:`FlxCrmEventAdd`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmEventAdd($input: FlxContactEventAddInput!) {
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
}`},Ie={id:`a76a713193a0c8d25b06c56932ac7a945251d1c2966e1e35351c2b154124d10d`,name:`FlxCrmEventDelete`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmEventDelete($input: FlxContactEventDeleteInput!) {
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
}`},Le={id:`e4410099ca450172284928b787d6653e3a20bb9fea759f3405dcc65b4b2d60fc`,name:`FlxCrmJourneyDelete`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmJourneyDelete($input: FlxJourneyDeleteInput!) {
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
}`},Re={id:`81f33cb7df2b01fe473af742d3664bc98d855fff2d8d6c6bd10bf813b3126a00`,name:`FlxCrmJourneyFromTemplate`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmJourneyFromTemplate($input: FlxJourneyFromTemplateInput!) {
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
}`},ze={id:`1d65e68f34e737d57ba69b8f75f693f31df5df46147478680767d4a886eba9a4`,name:`FlxCrmJourneys`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmJourneys {
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
}`},Be={id:`bb7940aae0aa4487e6596f7310dfe9d6c66cd05f801d9d2571a2fbd024003e89`,name:`FlxCrmJourneySave`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmJourneySave($input: FlxJourneySaveInput!) {
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
}`},Ve={id:`271eb15db3d24fbf7acc5848820ed761c6fdcece95a02526b41ff2a7316997a2`,name:`FlxCrmJourneySetStatus`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmJourneySetStatus($input: FlxJourneySetStatusInput!) {
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
}`},He={id:`172de029371f50368a15f9bd18e6643d27d6d370593aeac6979a894b17a7bf8b`,name:`FlxCrmOverview`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmOverview {
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
}`},Ue={id:`e0edd7dec5bacd4fa0efa1061b492dfd4514405725805c3d2dad8e334f1fa625`,name:`FlxCrmScoreRun`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmScoreRun($input: FlxCrmScoreRunInput!) {
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
}`},We={id:`2f13b0c4d23cfcf18bb040c35a545c3899b8be9c777bef71b9a3a1889584e38c`,name:`FlxCrmSegmentDelete`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmSegmentDelete($input: FlxSegmentDeleteInput!) {
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
}`},Ge={id:`acc491cf70a4b00af7fce340efd2fd26dc087fe84690b01a364966abf7dea880`,name:`FlxCrmSegmentEditor`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmSegmentEditor {
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
}`},Ke={id:`564b5e761395c08e8045dbc65f96f00b7f4fa14bc2dcfa48e28a0beff5edd00e`,name:`FlxCrmSegmentPreview`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmSegmentPreview($match: FlxSegmentMatch, $rules: [FlxSegmentRuleInput!]!) {
  flxSegmentPreview(match: $match, rules: $rules) {
    count
    summary
    sample {
      databaseId
      name
    }
  }
}`},qe={id:`6035e1dbb5301cebeb87fa3481cf44003f54cdd356668d7d22ff87b96ec87866`,name:`FlxCrmSegmentSave`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmSegmentSave($input: FlxSegmentSaveInput!) {
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
}`},Je={id:`4e800655079b39d7b0c8f5dca828111815df5e95cd0e91406785150732efd4a3`,name:`FlxCrmSettings`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmSettings {
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
}`},Ye={id:`6a79c573c70d3bbbdef5121997576cc4a22dfbd6b0a88b874a7029fa85cccbea`,name:`FlxCrmSettingsSave`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmSettingsSave($input: FlxCrmSettingsSaveInput!) {
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
}`},Xe={id:`3a59d8e4195bf7ba635933d4e7baafd37f013da4a16f4d4cfc49b7501f3a7de4`,name:`FlxCrmShell`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmShell {
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
}`},Ze={id:`91833bc806371dcec389dec7ee846bdc3806edd84b27da2b516ee064b794c6a5`,name:`FlxCrmSyncStep`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmSyncStep($input: FlxCrmSyncStepInput!) {
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
}`},Qe={id:`803c73840e66e14fc6312eb733e95eb00bb1261c0fcdcaa93508c07d05751c35`,name:`FlxCrmTagAdd`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmTagAdd($input: FlxContactTagAddInput!) {
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
}`},$e={id:`68836030d68ed1f45df0595812ec3fbbf361855944537c066a08b8ea641057fa`,name:`FlxCrmTagRemove`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmTagRemove($input: FlxContactTagRemoveInput!) {
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
}`},et={id:`b019ec38b06648c194238f86b99019a21dcdd4ae0092c44cddbc896a1421275c`,name:`FlxCrmTimeline`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmTimeline($id: ID!, $filter: String, $first: Int = 20, $after: String) {
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
}`},tt={id:`14ae18c34134e93d807ec1e3d21907a4c002555d4d327011a6cbdd05c13743ab`,name:`FlxLanguageSettingsScreen`,kind:`query`,plugin:`flx-lang`,document:`query FlxLanguageSettingsScreen {
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
}`},nt={id:`0da4e1f193deec851174ea768e4699d956a94e7670b172e44dafe5bf9f788a52`,name:`FlxLanguageSettingsUpdate`,kind:`mutation`,plugin:`flx-lang`,document:`mutation FlxLanguageSettingsUpdate($input: FlxLanguageSettingsUpdateInput!) {
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
}`},rt={id:`21a336969a0dc41b8caaa1be0a20b9bd740359347ff57bc8cb72f70ca6254f9e`,name:`FlxRoleCreate`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleCreate($input: FlxRoleCreateInput!) {
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
}`},it={id:`2bd5579ac1188eadb0dff5235840b66098c68b1b0d9e5d9a86c1a07b54d3acea`,name:`FlxRoleDelete`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleDelete($input: FlxRoleDeleteInput!) {
  flxRoleDelete(input: $input) {
    deletedKey
    errors {
      code
      message
      field
    }
  }
}`},at={id:`af09ea3f7594b6e37e1aa272f116e57dd7079ca275a754a8107fc230ce3014df`,name:`FlxRoleMemberAdd`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleMemberAdd($input: FlxRoleMemberAddInput!) {
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
}`},ot={id:`6eab4d6f1f97457a8679a9b1dd92d47dc2e01a7f41163eff439dc854213b2a7c`,name:`FlxRoleMemberRemove`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleMemberRemove($input: FlxRoleMemberRemoveInput!) {
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
}`},st={id:`067ae709ce0675a0f8e8ab3ab7d21d722afa9f7449f358767be373133c98de6f`,name:`FlxRoleMembers`,kind:`query`,plugin:`flx-roles`,document:`query FlxRoleMembers($key: String!, $first: Int = 50, $after: String) {
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
}`},ct={id:`20e6cdbb88f7639a8749e74cf75ef58a4ab704143f0cf0fa3474dbb2366cb1d0`,name:`FlxRoleReset`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleReset($input: FlxRoleResetInput!) {
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
}`},lt={id:`0a4da13faad85546b92fab5b45716dab5b06e87791475a9dfaa4664888375658`,name:`FlxRolesSave`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRolesSave($input: FlxRolesSaveInput!) {
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
}`},ut={id:`2c22565866bcebf0aaed510b75bec73444fc8257c8d2b327873e9c2963c59030`,name:`FlxRolesScreen`,kind:`query`,plugin:`flx-roles`,document:`query FlxRolesScreen($members: Int = 50) {
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
}`},dt={id:`24ef9e0380385f71b2af51a8163f15a8362c7a02a3fee076051efdd289a4d7a4`,name:`FlxSeoAuditRun`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoAuditRun($input: FlxSeoAuditRunInput!) {
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
}`},ft={id:`25585531a77819b9e9b681a85e767cb408d01dcab0ecb5e5e3717d30b20fb89e`,name:`FlxSeoAuditScreen`,kind:`query`,plugin:`flx-seo`,document:`query FlxSeoAuditScreen($history: Int = 30) {
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
}`},pt={id:`97d49670dd4df105502f5013764c377eaadec82d4f58ba3d9d0f8d3a00566bd2`,name:`FlxSeoContentCheck`,kind:`query`,plugin:`flx-seo`,document:`query FlxSeoContentCheck($limit: Int = 300) {
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
}`},mt={id:`aca5c530650ff0a558a94968a069e5d1d183315910cf69556396462fb63d557a`,name:`FlxSeoFixApply`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoFixApply($input: FlxSeoFixApplyInput!) {
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
}`},ht={id:`fa7f2e42636c7fd69983e7637be5625f01102cc336c76b7e751dfc6b25299aea`,name:`FlxSeoGuide`,kind:`query`,plugin:`flx-seo`,document:`query FlxSeoGuide($lang: FlxSeoGuideLang) {
  flxSeoGuide(lang: $lang) {
    lang
    markdown
    sections
  }
}`},gt={id:`84dfec5a3b859e284998fc8124abae04e2fdd4419dac91ab6cb5971882673a9d`,name:`FlxSeoIndexNowSubmit`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoIndexNowSubmit($input: FlxSeoIndexNowSubmitInput!) {
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
}`},_t={id:`59e6780cadb6bc0d4dcecc09e482056c52aeb00879b5b1c00fa35049f815dea2`,name:`FlxSeoPsiKeySave`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoPsiKeySave($input: FlxSeoPsiKeySaveInput!) {
  flxSeoPsiKeySave(input: $input) {
    saved
    errors {
      code
      message
      field
    }
  }
}`},vt={id:`ffd79b34482e7fd6c534446f2a81d044658a4eea3fafffa2d65eb14c8b4101fd`,name:`FlxSeoSettingsSave`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoSettingsSave($flxseo: FlxSettingsFlxseoInput!) {
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
}`},yt={id:`86bc810d43babcb5cefcbb0d86511dc9661c0d5431662164e77220a7f6f5e3b8`,name:`FlxSeoSettingsScreen`,kind:`query`,plugin:`flx-seo`,document:`query FlxSeoSettingsScreen {
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
}`},bt={id:`b8f91c52dab2869ea091bb25833ba93b1b5ba0eb62b369c3cff557466b3478ba`,name:`FlxSetupAutoUpdateSet`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupAutoUpdateSet($input: FlxSetupAutoUpdateSetInput!) {
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
}`},xt={id:`327a745eaebc60ab14f76d453be58e38f0399b212c0c15b87d2dbd1488a731c4`,name:`FlxSetupBackupsDelete`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupBackupsDelete($input: FlxSetupBackupsDeleteInput!) {
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
}`},St={id:`5c3dea411ce3bbfc57b8945d04dd11c857a00fe726bc27e95ea52175cd11ac3f`,name:`FlxSetupCheck`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupCheck {
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
}`},Ct={id:`fea5c46697571e499c36f813537e124a23f33e2a3b616d09264af5ef3fec1d26`,name:`FlxSetupHistory`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupHistory($page: Int) {
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
}`},wt={id:`6c9941eefcb59ba827d304f3336844f4a348ecb3f1b549a96d805b1c48412b6b`,name:`FlxSetupManage`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupManage($input: FlxSetupManageInput!) {
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
}`},Tt={id:`1641f892cadf8664b2298b4af2847db998d4c838826ebc0f08572a5ea648e637`,name:`FlxSetupOverview`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupOverview($search: String) {
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
}`},Et={id:`e8e1935e414a5f61d5841b22119e7874da774b8e7905d19e76333469f34eff77`,name:`FlxSetupPackages`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupPackages($tab: FlxSetupPackageTab, $search: String, $onlyUpdates: Boolean, $first: Int = 200, $after: String) {
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
}`},$={id:`91629093982ae065468fb3f63497bc77ac4c8950e085938617acebb155eb692e`,name:`FlxSetupPlan`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupPlan($slugs: [String!], $wpKeys: [String!], $all: Boolean, $activate: Boolean) {
  flxSetupPlan(slugs: $slugs, wpKeys: $wpKeys, all: $all, activate: $activate) {
    kind
    slug
    id
    name
    why
    version
  }
}`},Dt={id:`20b2e2929728ec932fd886ce76780a3b35a35fc0045391ff82428916aa37984b`,name:`FlxSetupRetentionSave`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupRetentionSave($input: FlxSetupRetentionSaveInput!) {
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
}`},Ot={id:`33e46578e9a32d1bff6a6ad7f8785580eb6208cbc4b806543fc964bf1a3048f7`,name:`FlxSetupSettingsSave`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupSettingsSave($input: FlxSetupSettingsSaveInput!) {
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
}`},kt={id:`d8622d9c1c248a1d4c9dbc1667856303277e0b4f7b03ae820895203ddc6aa9ee`,name:`FlxSetupSettingsScreen`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupSettingsScreen {
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
}`},At={id:`f8f67e0723c3045d9ba05b6454009c8f931f5f3a6a25a5680ccb8e478d3b2f3e`,name:`FlxSetupSourceAdd`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupSourceAdd($input: FlxSetupSourceAddInput!) {
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
}`},jt={id:`758bf6b4e9d2b43b0a4a2613eb688c94c8b73f0dd94f8e6efafd99fb74d5aa06`,name:`FlxSetupSourceChange`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupSourceChange($input: FlxSetupSourceChangeInput!) {
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
}`},Mt={id:`562e895123bcf4698f2e0b64d5f322aeecdf71358e7d66ef3677e3ed3e190a89`,name:`FlxSetupStepRun`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupStepRun($input: FlxSetupStepRunInput!) {
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
}`},Nt={id:`fca51d9372f393736cd5c7f3cc3a6c6f76bc42c714907f05360f59ce43283d8b`,name:`FlxTaskCommentCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCommentCreate($input: FlxTaskCommentCreateInput!) {
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
}`},Pt={id:`f4712b3032ffaf4580393cf0e6234154fa1eb66399b12784aa29f29b625d34bd`,name:`FlxTaskCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCreate($input: FlxTaskCreateInput!) {
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
}`},Ft={id:`157053946bdf55aa4bf5f22a676119ec48e7bbe04c053867b7e0eedb11e04ec2`,name:`FlxTaskDelete`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskDelete($input: FlxTaskDeleteInput!) {
  flxTaskDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},It={id:`fa1d3f71ca31ab2a7b72c1a987e295f209d6564e8a68e3643dee354a44544ecf`,name:`FlxTaskDetail`,kind:`query`,plugin:`flx-tasks`,document:`query FlxTaskDetail($id: ID!) {
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
}`},Lt={id:`d357c8fdb6c372508b17bb93fa22ec80224c0ff43854104149816d70a9e80870`,name:`FlxTaskNotificationsRead`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskNotificationsRead($input: FlxTaskNotificationsReadInput!) {
  flxTaskNotificationsRead(input: $input) {
    unread
    errors {
      code
      message
      field
    }
  }
}`},Rt={id:`004e85b9237b995957f75c2cfe2a996f5d2de6ca98b31c98511cb403df800ae3`,name:`FlxTaskUpdate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdate($input: FlxTaskUpdateInput!) {
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
}`},zt={id:`7c644e53f296f0dbf859b7bfcb4748e8c5be3ef891474fb47a262a2cd993be7a`,name:`FlxTaskUpdateDetail`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdateDetail($input: FlxTaskUpdateInput!) {
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
}`},Bt={id:`a2c919c298da71f1634726a7c85c4344cf85f3602cbf54e4175bd8e45578e9b5`,name:`FlxTaskWatch`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskWatch($input: FlxTaskWatchInput!) {
  flxTaskWatch(input: $input) {
    watcherIds
    errors {
      code
      message
      field
    }
  }
}`};export{le as $,lt as $t,L as A,zt as An,Ve as At,q as B,Xe as Bt,A as C,Mt as Cn,Me as Ct,P as D,It as Dn,Le as Dt,N as E,Ft as En,Ie as Et,H as F,Ge as Ft,Q as G,tt as Gt,Y as H,Qe as Ht,U as I,Ke as It,ie as J,it as Jt,ne as K,nt as Kt,W as L,qe as Lt,z as M,l as Mn,He as Mt,B as N,i as Nn,Ue as Nt,F as O,Lt as On,Re as Ot,V as P,a as Pn,We as Pt,ce as Q,ct as Qt,G as R,Je as Rt,k as S,jt as Sn,Ne as St,M as T,Pt as Tn,Fe as Tt,X as U,$e as Ut,J as V,Ze as Vt,Z as W,et as Wt,oe as X,ot as Xt,ae as Y,at as Yt,se as Z,st as Zt,w as _,$ as _n,De as _t,ee as a,ht as an,he as at,D as b,kt as bn,Ae as bt,g as c,vt as cn,ve as ct,v as d,xt as dn,xe as dt,ut as en,ue as et,y as f,St as fn,Se as ft,C as g,Et as gn,Ee as gt,S as h,Tt as hn,Te as ht,te as i,mt as in,me as it,R as j,Bt as jn,ze as jt,I as k,Rt as kn,Be as kt,h as l,yt as ln,ye as lt,x as m,wt as mn,we as mt,d as n,ft as nn,fe as nt,p as o,gt as on,ge as ot,b as p,Ct as pn,Ce as pt,re as q,rt as qt,f as r,pt as rn,pe as rt,m as s,_t as sn,_e as st,u as t,dt as tn,de as tt,_ as u,bt as un,be as ut,T as v,Dt as vn,Oe as vt,j as w,Nt as wn,Pe as wt,O as x,At as xn,je as xt,E as y,Ot as yn,ke as yt,K as z,Ye as zt};