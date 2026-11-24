var e={id:`1ca49b34f117b7ac552c929bde0588899c760959aa9b79609f629786e0b10362`,name:`FlxAdminCapsRestore`,kind:`mutation`,plugin:`flx-admin`,document:`mutation FlxAdminCapsRestore($input: FlxAdminCapsRestoreInput!) {
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
}`},t={id:`e13320fd86a143dffd7f3d635d5c1d71e5fb878e8704d8d973d71573220d6105`,name:`FlxAdminCapsSave`,kind:`mutation`,plugin:`flx-admin`,document:`mutation FlxAdminCapsSave($input: FlxAdminCapsSaveInput!) {
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
}`},n={id:`0466b867177e171b259ec3667c8becb5d880f4519688510e3af1bbb8e6a7a427`,name:`FlxAdminCapsScreen`,kind:`query`,plugin:`flx-admin`,document:`query FlxAdminCapsScreen {
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
}`},r={id:`b8757243aa99ee7e2a5a102a2badaeb0fa69f51a6e630fba153831919da6e3d5`,name:`FlxAdminMenuLayoutsSave`,kind:`mutation`,plugin:`flx-admin`,document:`mutation FlxAdminMenuLayoutsSave($input: FlxAdminMenuLayoutsSaveInput!) {
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
}`},i={id:`6abf2fee616f4018f122022f2fe89daeeae06431b8ef159b3aa0c5e88a85d27d`,name:`FlxAdminMenuScreen`,kind:`query`,plugin:`flx-admin`,document:`query FlxAdminMenuScreen {
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
}`},a={id:`c0e62c44430bcb441296f9b3f7f887fbebcb206f051781dcdec9d4ee6fab3265`,name:`FlxAdminSettingsScreen`,kind:`query`,plugin:`flx-admin`,document:`query FlxAdminSettingsScreen {
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
}`},o={id:`c262d266ac9488a61594ff3814564ee4fa1f1acde705b4fed210d59eca7046bc`,name:`FlxAdminSettingsUpdate`,kind:`mutation`,plugin:`flx-admin`,document:`mutation FlxAdminSettingsUpdate($input: FlxAdminSettingsUpdateInput!) {
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
}`},s={id:`15576c276018086831114da1ff501c31ce16011c5c4d42a30db693cc098ad75b`,name:`FlxAdsAudienceCreate`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsAudienceCreate($input: FlxAdsAudienceCreateInput!) {
  flxAdsAudienceCreate(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},c={id:`8e00b2b06e97bddea533211028e4625fbe614cdeff5df60b79721a1bf3a447f8`,name:`FlxAdsAudienceDelete`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsAudienceDelete($input: FlxAdsAudienceDeleteInput!) {
  flxAdsAudienceDelete(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},l={id:`e331debbfac30bc1a1933d4c25decd870765d7d48be844c20f11584aa18854c4`,name:`FlxAdsAudienceLookalike`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsAudienceLookalike($input: FlxAdsAudienceLookalikeInput!) {
  flxAdsAudienceLookalike(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},u={id:`37c074f16556d30dd0de810739258178dbf8ebd7621a828ae9f001843a4b600c`,name:`FlxAdsAudiences`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsAudiences {
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
}`},d={id:`ff4ef120d9023d952ce2d8437b3aa7f42ba9445c11452521e7a9f657ed37cdf0`,name:`FlxAdsAudienceSync`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsAudienceSync($input: FlxAdsAudienceSyncInput!) {
  flxAdsAudienceSync(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},f={id:`703996eb7a33f7182b5e7467dd8a5c6b40efe23f63ceda425587a57cbcf9f643`,name:`FlxAdsCampaign`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsCampaign($id: String!, $range: String) {
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
}`},p={id:`7ecd311b8a7e72f9192857c757cd218bc834ec7ffc3d6a498f99fde98c124c54`,name:`FlxAdsCampaignBudget`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsCampaignBudget($input: FlxAdsCampaignBudgetInput!) {
  flxAdsCampaignBudget(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},m={id:`53a30b5fbd31c8b762b44748f6ff3bc66fd2501fceece1d2d19f4bfefc14296a`,name:`FlxAdsCampaigns`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsCampaigns($range: String) {
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
}`},h={id:`f4fc0945c371238c1d09e1e55c05d2104f3c6ac94ba5ad3df1ebb799eb5ae1bd`,name:`FlxAdsCampaignStatus`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsCampaignStatus($input: FlxAdsCampaignStatusInput!) {
  flxAdsCampaignStatus(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},g={id:`9366271e73db1168ecb85a047953c4758cbe2dd3a4c91b3cd7fdcbd2f3f79e18`,name:`FlxAdsChangeHistory`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsChangeHistory($limit: Int) {
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
}`},_={id:`c862bf2e2dfad06e84f85be950d8696400d6faa25ba3480c36283bcface3e422`,name:`FlxAdsConnectionTest`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsConnectionTest($input: FlxAdsConnectionTestInput!) {
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
}`},v={id:`683c3cf442655c7a16dcb6d4f80e8e34076f2d19cc410f7bf1b53f473be0c5d6`,name:`FlxAdsContext`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsContext {
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
}`},y={id:`6c177b16c9b3bc709cd8b09885247c84de6832111cc6cf5bf2c628028e13a325`,name:`FlxAdsDraft`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsDraft($id: String!) {
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
}`},b={id:`c997b63c709ba8710ac23ba497313e41cd5eee794dfc2325e7a260ec4c663148`,name:`FlxAdsDraftDelete`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsDraftDelete($input: FlxAdsDraftDeleteInput!) {
  flxAdsDraftDelete(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},x={id:`89515abbbb3f4bf1d730ff46dab90bd993a25a75f787d3d80828f561f64922e2`,name:`FlxAdsDraftDuplicate`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsDraftDuplicate($input: FlxAdsDraftDuplicateInput!) {
  flxAdsDraftDuplicate(input: $input) {
    id
    errors {
      code
      message
      field
    }
  }
}`},S={id:`5ceedef8952188c75b1fb5538e26d371371a6d0f40200f3828a72655d1927b49`,name:`FlxAdsDraftGenerate`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsDraftGenerate($input: FlxAdsDraftGenerateInput!) {
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
}`},C={id:`7879833baa361069f8eca473c5b924722775b589b7abfeaf8b404ca2a3cb76e0`,name:`FlxAdsDraftSave`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsDraftSave($input: FlxAdsDraftSaveInput!) {
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
}`},w={id:`0430cfde4021f60d470591cea01d3f32d9018718afc438e364d879f2b9ce05a3`,name:`FlxAdsDraftSend`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsDraftSend($input: FlxAdsDraftSendInput!) {
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
}`},T={id:`80e7b7ac54b372c91d5f722cc70b41eabf6ea670420a9cfd1f51b79ae2ea7fee`,name:`FlxAdsExperimentChoices`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsExperimentChoices {
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
}`},E={id:`033461a44cc988fdecba7a868f94beeae673e05e99e6a0f651dcc6f8ddf15cde`,name:`FlxAdsExperimentCreate`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsExperimentCreate($input: FlxAdsExperimentCreateInput!) {
  flxAdsExperimentCreate(input: $input) {
    id
    errors {
      code
      message
      field
    }
  }
}`},D={id:`b4802892f86bf8733e5fc2ecb0b9d2ac6424cb559f383444df60206dbea03941`,name:`FlxAdsExperimentDelete`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsExperimentDelete($input: FlxAdsExperimentDeleteInput!) {
  flxAdsExperimentDelete(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},O={id:`c6afed85d499801f21da2e4e8d69e4a9c7369bcfe7a5f3559725e49ec45c846b`,name:`FlxAdsExperimentFinish`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsExperimentFinish($input: FlxAdsExperimentFinishInput!) {
  flxAdsExperimentFinish(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},k={id:`69240b9776427bfac198af5f47566fdccc7671ccac938c9659fc647594b8f82d`,name:`FlxAdsExperimentPauseLoser`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsExperimentPauseLoser($input: FlxAdsExperimentPauseLoserInput!) {
  flxAdsExperimentPauseLoser(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},A={id:`a3f180f5122d49e555d7df40fa228d92e2b708ca41c4a91320a077bdeda9b4fa`,name:`FlxAdsExperiments`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsExperiments {
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
}`},j={id:`5d9d75833ba1fb533a368cd8154d472de9ffe0d605789f804ccc81ebf680b89c`,name:`FlxAdsHeartbeat`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsHeartbeat($input: FlxAdsHeartbeatInput!) {
  flxAdsHeartbeat(input: $input) {
    ok
    openSuggestions
    errors {
      code
      message
      field
    }
  }
}`},M={id:`e0f9c37d4069b16c70279171e21bb762df8274cb70cf6d2e92170a46624d8530`,name:`FlxAdsLinkBuilder`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsLinkBuilder {
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
}`},N={id:`19a468a8f6f5ffb5c6da168b2982608ae7d88d44e17d13e372769a1281a300ea`,name:`FlxAdsReport`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsReport($filter: FlxAdsFilterInput) {
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
}`},P={id:`4d8b9511f7b03e83474289c5bc7ad01f1a223b20e72587dd16d1c9f2cb5f5c5f`,name:`FlxAdsSeasonAdd`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSeasonAdd($input: FlxAdsSeasonAddInput!) {
  flxAdsSeasonAdd(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},F={id:`78a9c32a2cdfe9c5913dbb26ef15562d0b4ccd3a9415e2fe663a555a09d4b631`,name:`FlxAdsSeasonDelete`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSeasonDelete($input: FlxAdsSeasonDeleteInput!) {
  flxAdsSeasonDelete(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},I={id:`dc07f0cade5a2084311ae381d5099ea62c1550aa997fcd471197ccba702f25b8`,name:`FlxAdsSeasons`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsSeasons {
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
}`},L={id:`c9356d278116835ba38b6650ff12d25e1e61e19d6fae07a5c67f5fc6a7e258dd`,name:`FlxAdsSendLog`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsSendLog {
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
}`},R={id:`f249dbddc4f9f35a4d800d5b883d9f9b403621c899975f4afc4b8868c9f98f0b`,name:`FlxAdsSendRetry`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSendRetry($input: FlxAdsSendRetryInput!) {
  flxAdsSendRetry(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},z={id:`79cffe4095d5387aec2dc85c9ee353e8d208749df66181b7062c27e869c1f3ab`,name:`FlxAdsSettings`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsSettings {
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
}`},B={id:`24a9e8646c7d2467c1f9ef0daf4c100a2a4df6971c33343e297b4ecf7582d281`,name:`FlxAdsSettingsSave`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSettingsSave($input: FlxAdsSettingsSaveInput!) {
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
}`},V={id:`b7ca184a98d1ee03a35d28f0ff539562795c566ae0e48eec7bc07669e9363747`,name:`FlxAdsSpend`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsSpend($filter: FlxAdsFilterInput) {
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
}`},H={id:`3bd3940ea9c4e06b7dbf33031290ab8d9e6ed4fec4b0be7297d064b37f525b0c`,name:`FlxAdsSpendAdd`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSpendAdd($input: FlxAdsSpendAddInput!) {
  flxAdsSpendAdd(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},U={id:`4c7a66d6698c203e77643a5486d518bfaab14776558bfa6a7c67ec9a695c2768`,name:`FlxAdsSpendDelete`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSpendDelete($input: FlxAdsSpendDeleteInput!) {
  flxAdsSpendDelete(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},W={id:`321a15204f3a2eeafee102500c9f05985fc678551b77b27bb41ef41ea67ca0c2`,name:`FlxAdsSpendSync`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSpendSync($input: FlxAdsSpendSyncInput!) {
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
}`},G={id:`6402f8d915ab7a970d1096ef12af62931ed8c50029a1f5490a106a629e625e4b`,name:`FlxAdsStudio`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsStudio {
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
}`},K={id:`7d8a60a058ce4b48e7047831db86f4ece1d4aa76a90a5598362e7ffdb1830669`,name:`FlxAdsSuggestionApply`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSuggestionApply($input: FlxAdsSuggestionApplyInput!) {
  flxAdsSuggestionApply(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},q={id:`f69a419a23d70261401dc4bc190e759a71413b427c8c58e9e490e32502d1c49a`,name:`FlxAdsSuggestionDismiss`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSuggestionDismiss($input: FlxAdsSuggestionDismissInput!) {
  flxAdsSuggestionDismiss(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},J={id:`0d11b8f455f8a16515111047721b1dfebd7b70f52034cc85ad2c98efdd781972`,name:`FlxAdsSuggestions`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsSuggestions {
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
}`},Y={id:`3311e69615d4f9a6891a6a199712bac121e9397e58daee03f7e4935d5caec43d`,name:`FlxAdsSuggestionsRefresh`,kind:`mutation`,plugin:`flx-ads`,document:`mutation FlxAdsSuggestionsRefresh($input: FlxAdsSuggestionsRefreshInput!) {
  flxAdsSuggestionsRefresh(input: $input) {
    newCount
    errors {
      code
      message
      field
    }
  }
}`},X={id:`e89bbd8ee4c7bf09dcfb37612cbe5859fc64666e6e91b3d6ac45fe9826e684aa`,name:`FlxAdsValue`,kind:`query`,plugin:`flx-ads`,document:`query FlxAdsValue($filter: FlxAdsFilterInput) {
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
}`},Z={id:`6d130e09eecfd7058aed48ff1e920186a016d4213b6a6db97977b95cd0eadc09`,name:`FlxAiApprovalDecide`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiApprovalDecide($input: FlxAiApprovalDecideInput!) {
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
}`},Q={id:`c094dd97296a5f7fae585f6813e3e91ba9655b8eebc116746747db54c0c4a842`,name:`FlxAiApprovals`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiApprovals {
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
}`},$={id:`2ce74187ffc690342b4345a3cde10aefb9efc6e12ea495d8531228dc9647e8fe`,name:`FlxAiBilling`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiBilling($month: String) {
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
}`},ee={id:`b3a750b6fd0443498787e2f4e0d3d13b90d91b045d92cac228b455185e51effd`,name:`FlxAiBrand`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiBrand {
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
}`},te={id:`da632a2d2612577c5ac2b5f6dab4e8bf8aca22f001cff20cdb512cada14694a7`,name:`FlxAiBrandSave`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiBrandSave($input: FlxAiBrandSaveInput!) {
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
}`},ne={id:`fd799eb314797b602e77f1fee7607668d0d0f868cf45d58fd8f39ffdb0f933a2`,name:`FlxAiChangeDecide`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiChangeDecide($input: FlxAiChangeDecideInput!) {
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
}`},re={id:`54302d7cfd5c4d9f53601034c11816dce8c32f05881e3e21189bdc37238338fd`,name:`FlxAiChat`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiChat($id: Int!) {
  flxAiChat(id: $id) {
    model
    total
    summary {
      ...FlxAiChatSummaryFields
    }
    messages {
      index
      role
      text
      tool
      ok
      error
      tools
      time {
        timestamp
        label
      }
    }
  }
}

fragment FlxAiChatSummaryFields on FlxAiChatSummary {
  id
  title
  archived
  status
  snippet
  task
  updated {
    timestamp
    label
    relative
  }
}`},ie={id:`924cf78a19de6841a211e2205ad29b95fb1a6cb841afa5285fa321722a3b2b14`,name:`FlxAiChatDelete`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiChatDelete($input: FlxAiChatDeleteInput!) {
  flxAiChatDelete(input: $input) {
    deleted
    errors {
      code
      message
      field
    }
  }
}`},ae={id:`9fe03653b92ecde2869570d83b5970a3ae8716bfec173d7030aba5c85e7c2f19`,name:`FlxAiChats`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiChats($q: String, $archived: Int, $limit: Int, $offset: Int) {
  flxAiChats(q: $q, archived: $archived, limit: $limit, offset: $offset) {
    hasMore
    items {
      ...FlxAiChatSummaryFields
    }
  }
}

fragment FlxAiChatSummaryFields on FlxAiChatSummary {
  id
  title
  archived
  status
  snippet
  task
  updated {
    timestamp
    label
    relative
  }
}`},oe={id:`4319c748d7d5f857b60e935a2bd00d4fe9e83a7443bc0cf439d8cb926d04b429`,name:`FlxAiChatUpdate`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiChatUpdate($input: FlxAiChatUpdateInput!) {
  flxAiChatUpdate(input: $input) {
    chat {
      ...FlxAiChatSummaryFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxAiChatSummaryFields on FlxAiChatSummary {
  id
  title
  archived
  status
  snippet
  task
  updated {
    timestamp
    label
    relative
  }
}`},se={id:`c584fcb50fda45269d3a90fa9de3e103387b2ac5e43fac141cded87bfad1e27b`,name:`FlxAiConnectionDelete`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiConnectionDelete($input: FlxAiConnectionDeleteInput!) {
  flxAiConnectionDelete(input: $input) {
    deleted
    errors {
      code
      message
      field
    }
  }
}`},ce={id:`39852a224f2180bcf910ea090988691e4000f622a8c247d1ce2a75441d414d79`,name:`FlxAiConnectionPermissions`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiConnectionPermissions($input: FlxAiConnectionPermissionsInput!) {
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
}`},le={id:`be1ebb411a3c87ae6a095d86587c7615125f12ac4bf5e678f37d40046c5e9622`,name:`FlxAiConnectionRefresh`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiConnectionRefresh($input: FlxAiConnectionRefreshInput!) {
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
}`},ue={id:`19d7d049543a095326161e79e4304c65afc3dd52749dae9f123ae489315439e9`,name:`FlxAiConnections`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiConnections {
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
}`},de={id:`d08c9aeabac78f5a9134006e481bf4d5630b50827c0554077d9b4aa9c11ced72`,name:`FlxAiConnectionSave`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiConnectionSave($input: FlxAiConnectionSaveInput!) {
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
}`},fe={id:`fec0e46a5655ac1d54df6a74c7d278d9c2f6cb55589b8ac91de909d6e13060d1`,name:`FlxAiConnectionSignIn`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiConnectionSignIn($input: FlxAiConnectionSignInInput!) {
  flxAiConnectionSignIn(input: $input) {
    url
    errors {
      code
      message
      field
    }
  }
}`},pe={id:`a68c54ea203579ba00071037f70a0a24d7d6c1e0d759d854afc7c26683ce66f1`,name:`FlxAiConnectionSignOut`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiConnectionSignOut($input: FlxAiConnectionSignOutInput!) {
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
}`},me={id:`ecfbbe459dc33b5d5788763a9b369b151e2c6fb1b03dcffe9f31e1fa29785ab3`,name:`FlxAiCreditsRefresh`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiCreditsRefresh {
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
}`},he={id:`580801a095e875c9d47b645c9cb73f0f6fdb1846df5ad9d786d2efa129e9a5c8`,name:`FlxAiLogBody`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiLogBody($id: Int!) {
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
}`},ge={id:`eebe6c676c9d99c5200a53263539c163c3c4f838ca7e6fe125ce4b190711d0b5`,name:`FlxAiLogs`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiLogs($first: Int = 50, $after: String, $days: Int = 7, $kind: FlxAiLogKind, $source: String, $errorsOnly: Boolean = false) {
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
}`},_e={id:`260f5986cb85f04bab258711264790f696601d549e356726f15b2dc3f3e7579e`,name:`FlxAiMcp`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiMcp {
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
}`},ve={id:`f97f6605a6fa484382293f98d1469ce8edcb3dce4278728593da69f09438acba`,name:`FlxAiMcpKeyCreate`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiMcpKeyCreate($input: FlxAiMcpKeyCreateInput!) {
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
}`},ye={id:`8813b1716185294ae6ba81f92817bdc89453f604190bc64b887cce9b3eb46e2c`,name:`FlxAiMcpKeyRevoke`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiMcpKeyRevoke($input: FlxAiMcpKeyRevokeInput!) {
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
}`},be={id:`0ae41a85219ce4680c23392ebfeae7e05876d73a907aca2e57384f8b43c1dfb8`,name:`FlxAiMcpServerUpdate`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiMcpServerUpdate($input: FlxAiMcpServerUpdateInput!) {
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
}`},xe={id:`ca6dc8ad4a997533257ffb1be1de3b6e892908b13ebd29cf8132953a97a62a87`,name:`FlxAiModels`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiModels($search: String, $first: Int = 50) {
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
}`},Se={id:`26493d21bc4cbbc74a727b78e026b640105e8157477eefcbfb36f7ba46caa432`,name:`FlxAiModelTest`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiModelTest($input: FlxAiModelTestInput!) {
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
}`},Ce={id:`a016eb829167be15c5f406279dc7b461d1610db9bd6287d84a489d71f9cc1a17`,name:`FlxAiOverview`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiOverview($days: Int = 30, $source: String) {
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
}`},we={id:`97cc803140314bc8487bb7b36e05aa5709b31e5727793e322f8fb0a42a63077c`,name:`FlxAiRecipients`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiRecipients($search: String) {
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
}`},Te={id:`6b7262310f5715f6eaf6d3d7d1864540ba7597a648899ddce2cda734997e1e95`,name:`FlxAiRenderTest`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiRenderTest($input: FlxAiRenderTestInput!) {
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
}`},Ee={id:`fcbb3cef6e71e12ccbf8e9ff024a957a7ec475336556e2ef9136bb3784c3bec2`,name:`FlxAiSettingsScreen`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiSettingsScreen {
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
}`},De={id:`142806ed14f0d030b028c91f2ebfcb6be9b808394ff42f165b17ee726731c132`,name:`FlxAiSettingsUpdate`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSettingsUpdate($input: FlxAiSettingsUpdateInput!) {
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
}`},Oe={id:`0487ce458b717192e900f7caef75ddca5d4567a5a6e50fcc2cdff15b640f9bc7`,name:`FlxAiSkill`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiSkill($slug: String!) {
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
}`},ke={id:`e09f255e7a9396b3fc199130ff80d8fe83b8d433c238f9979b9f8698ba8ee13d`,name:`FlxAiSkillDelete`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSkillDelete($input: FlxAiSkillDeleteInput!) {
  flxAiSkillDelete(input: $input) {
    deleted
    errors {
      code
      message
      field
    }
  }
}`},Ae={id:`372eef6b1c9464e87ea02994b5fda77f806be1bf15e9b6910fe555ec19080362`,name:`FlxAiSkillEvalsSave`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSkillEvalsSave($input: FlxAiSkillEvalsSaveInput!) {
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
}`},je={id:`a6a472f970cb6325218460eaec9caffcc51c157174dbcb64dd6031019f2e07b7`,name:`FlxAiSkillFile`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiSkillFile($slug: String!, $path: String!) {
  flxAiSkillFile(slug: $slug, path: $path) {
    path
    size
    text
    content
    note
  }
}`},Me={id:`d373e520e30964f085df84a2ea0884ff1454c19bb21f4fba45a02b72ddf170c5`,name:`FlxAiSkillFileDelete`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSkillFileDelete($input: FlxAiSkillFileDeleteInput!) {
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
}`},Ne={id:`09c37d0e71eb7657215d6bc698bc8d2db1029394076b3d229df3c9bbd790fe20`,name:`FlxAiSkillFileSave`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSkillFileSave($input: FlxAiSkillFileSaveInput!) {
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
}`},Pe={id:`ebd8574ac829b2962c165a2746255c9f5ce6cf1e021e1ff970e262bc4da2d3a7`,name:`FlxAiSkillFileUpload`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSkillFileUpload($input: FlxAiSkillFileUploadInput!) {
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
}`},Fe={id:`d9191efde70835ba5cfed6ceb69090c4efade488a245e71ab5ebd320914d2ad0`,name:`FlxAiSkillImport`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSkillImport($input: FlxAiSkillImportInput!) {
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
}`},Ie={id:`cf20bd001ca38dfb6cfde68b6cf3c3338b3ec8ab2f1c32c3839bb6e986b2d82f`,name:`FlxAiSkills`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiSkills {
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
}`},Le={id:`6cde51e04a2e3a47ba5121f5df5cbdc44ff904a6612f8aeabc8e9a962f4a1d90`,name:`FlxAiSkillSave`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSkillSave($input: FlxAiSkillSaveInput!) {
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
}`},Re={id:`da67faeb795417a5c9c511c64f539a783b84b00959f301cdf4bcb51ca83159dd`,name:`FlxAiSkillsSettings`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSkillsSettings($input: FlxAiSkillsSettingsInput!) {
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
}`},ze={id:`b1420ef58530cffd638bfb94181dc30468180e48a8d5d28faa50553961fd57d1`,name:`FlxAiSkillTestRun`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSkillTestRun($input: FlxAiSkillTestRunInput!) {
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
}`},Be={id:`fdb8bd020db940528756b520ebf649d8516754ea03b16bd1d4cf43216eb556f5`,name:`FlxAiSkillToggle`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSkillToggle($input: FlxAiSkillToggleInput!) {
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
}`},Ve={id:`97e294dc31a61739f94e276e07f3a784c69619c14137143d7fa6c20e6ffd6ab5`,name:`FlxAiSystems`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiSystems {
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
}`},He={id:`d4ed3c9a54a944e4f3f13663d24756babda1e6af03f49a52568a6eae6f2b4230`,name:`FlxAiSystemSettings`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiSystemSettings($source: String!) {
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
}`},Ue={id:`407dab4bb03fd8c1d73c33fc465338a17cbaeb37b57f8dc3caa479c173a9c284`,name:`FlxAiSystemSettingsUpdate`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSystemSettingsUpdate($input: FlxAiSystemSettingsUpdateInput!) {
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
}`},We={id:`73431ff2a62e32d7f2e91309552ed1c18343d40e544dd7ad09c09618c1c845fb`,name:`FlxAiSystemsUpdate`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSystemsUpdate($input: FlxAiSystemsUpdateInput!) {
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
}`},Ge={id:`968bc851fc41b9038e26b9f469b50c6b8abc9aa6a0231c99c42f65810f2f65da`,name:`FlxAiTaskDelete`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiTaskDelete($input: FlxAiTaskDeleteInput!) {
  flxAiTaskDelete(input: $input) {
    deleted
    errors {
      code
      message
      field
    }
  }
}`},Ke={id:`a716b6d24e46024257e51c1690e1e1283a388f1806a112617551e4ad58a552bb`,name:`FlxAiTaskRun`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiTaskRun($input: FlxAiTaskRunInput!) {
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
}`},qe={id:`430fa2417957a3261f5b5cad236830f3d186555265e97f90c244bea62694201b`,name:`FlxAiTasks`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiTasks {
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
}`},Je={id:`1f4f0cf7a4e19e42faa4145528018626aa1c712f6d77cb4c87781073e74ab24d`,name:`FlxAiTaskSave`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiTaskSave($input: FlxAiTaskSaveInput!) {
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
}`},Ye={id:`534e50822d3b85dbba646ca9b3fe4266802f3a320c8df8cdbc649d8abf8777be`,name:`FlxAiTaskSetActive`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiTaskSetActive($input: FlxAiTaskSetActiveInput!) {
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
}`},Xe={id:`5b14958f712eea70ed3c52f18f2399b72ff469ae34b95246c31fdc9b2481caad`,name:`FlxAiTasksToggle`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiTasksToggle($input: FlxAiTasksToggleInput!) {
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
}`},Ze={id:`89cc3100bddc0b80ca45c79713741b1e5c52fbff15fcc12aa33aa648bfa6b3cd`,name:`FlxAiToolPick`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiToolPick($input: FlxAiToolPickInput!) {
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
}`},Qe={id:`2ac9ecc70a8c53e97b50f2b9b4761536f293a681d6e3e0c4fe65ae7b83a2cacb`,name:`FlxAiTools`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiTools {
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
}`},$e={id:`fa6d026ab04301f37b80092e734c35cea8adfdf785d61057e8a0d53f71e4fdae`,name:`FlxAiToolsUpdate`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiToolsUpdate($input: FlxAiToolsUpdateInput!) {
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
}`},et={id:`38c66e1db90a0c7f811614cb50f0f82d679a21ff93ebe0cbf3b021b9e5a9f7af`,name:`FlxAnalyticsAggregate`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsAggregate($input: FlxAnalyticsAggregateInput!) {
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
}`},tt={id:`6b342c00c7aeb5b90485e9ccd04debf159a54692615072abccfe121ae406f79f`,name:`FlxAnalyticsBreakdown`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsBreakdown($filter: FlxAnalyticsFilterInput, $kind: FlxAnalyticsDimKind!, $first: Int, $after: String) {
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
}`},nt={id:`2023f590bac125700fa5bcb6ddf42b8117fe52804f68d372a0a34d4868d6832b`,name:`FlxAnalyticsConfig`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsConfig {
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
}`},rt={id:`5576bd9bb02666b281e1c009983a7c270480a77cf90231fd8ba61016a7056037`,name:`FlxAnalyticsEvents`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsEvents($filter: FlxAnalyticsFilterInput, $name: String, $first: Int, $after: String) {
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
}`},it={id:`79d1dccd00bee3f2f3c4de1ed9fa6ff89fd2056b81518e5f7e92466bdbf3a388`,name:`FlxAnalyticsFastTest`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsFastTest($input: FlxAnalyticsFastTestInput!) {
  flxAnalyticsFastTest(input: $input) {
    method
    errors {
      code
      message
      field
    }
  }
}`},at={id:`ea6bd1bf4b9cbda61bd0c6e6053256c45c01af2682b85d64981868aa0b5d82f8`,name:`FlxAnalyticsFunnel`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsFunnel($filter: FlxAnalyticsFilterInput, $id: Int, $by: FlxAnalyticsFunnelBy) {
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
}`},ot={id:`712f94387c03d252e5e19ad5cc11d4f20cc35fa4b778aba666cae6a27dff36a5`,name:`FlxAnalyticsJourneys`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsJourneys($filter: FlxAnalyticsFilterInput, $options: FlxAnalyticsJourneysInput) {
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
}`},st={id:`e7a13b823796a603132470f9f248230c22cedfd4d2391e05e1a1a6626ecbd35a`,name:`FlxAnalyticsKokoImport`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsKokoImport($input: FlxAnalyticsKokoImportInput!) {
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
}`},ct={id:`8d1c4f7574619f7eb39e4b908cdd7ff3e11a3b13bc255f0e2b3f8efd83489839`,name:`FlxAnalyticsPage`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsPage($filter: FlxAnalyticsFilterInput, $target: String!) {
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
}`},lt={id:`ebbac7ddcb08b32fb8b2c0eb5b67c80284fa09be3165b30eebeae0a282cee423`,name:`FlxAnalyticsPages`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsPages($filter: FlxAnalyticsFilterInput, $q: String, $only404: Boolean, $first: Int, $after: String) {
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
}`},ut={id:`db042cdd9f459d72081431f7df6d8a9e1081c5230eef59de2e263c30dcb9d698`,name:`FlxAnalyticsRealtime`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsRealtime($filter: FlxAnalyticsFilterInput, $minutes: Int, $kpi: Boolean) {
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
}`},dt={id:`3c8464c3ca4e71ec015a9a16728f8a3696bdbd22a4e4eecc19312c893c3d4db1`,name:`FlxAnalyticsReferrers`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsReferrers($filter: FlxAnalyticsFilterInput, $q: String, $first: Int, $after: String) {
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
}`},ft={id:`59ee013d5260056c879e17dc79e67240198450d0cb156e6425159922a2d37744`,name:`FlxAnalyticsRefSave`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsRefSave($input: FlxAnalyticsRefSaveInput!) {
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
}`},pt={id:`8379b10f9853966238a2d54a9b2b4dd22175b692573038145ee03bf5b3501f45`,name:`FlxAnalyticsReport`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsReport($filter: FlxAnalyticsFilterInput) {
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
}`},mt={id:`ce33713a41cfcf183974cb43003436209c4bedc460a35228a0fd27982819f466`,name:`FlxAnalyticsReset`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsReset($input: FlxAnalyticsResetInput!) {
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
}`},ht={id:`129cfba2b194904d644f1f6c1a89065b2c3534dd0982d6aa88d30aa855920c9f`,name:`FlxAnalyticsSettings`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsSettings {
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
}`},gt={id:`fcf4e0148b6d01ec7f05e578011d35764659fdef2afb0d3cd5438e14707c4c8f`,name:`FlxAnalyticsSettingsSave`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsSettingsSave($input: FlxAnalyticsSettingsSaveInput!) {
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
}`},_t={id:`eb5453fc995071cd78ac870527d7da1090a3441f298f6113f2af7cfe9fde45f4`,name:`FlxAnalyticsTelemetry`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsTelemetry($days: Int, $country: String) {
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
}`},vt={id:`98c319fa61824c0c4714f4d86466d15123634d74291edb45f9001c7d7b6b7fbb`,name:`FlxAnalyticsTelemetryErrors`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsTelemetryErrors($days: Int, $country: String, $plugin: String, $type: String) {
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
}`},yt={id:`d64ae79eb5491a540154bd58029d69fc0b5e212e8c46414279d4ca0eaa940e1d`,name:`FlxAnalyticsTelemetryForget`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsTelemetryForget($input: FlxAnalyticsTelemetryForgetInput!) {
  flxAnalyticsTelemetryForget(input: $input) {
    removed
    errors {
      code
      message
      field
    }
  }
}`},bt={id:`1dd252522e2e7e352cbe31ebb7ef763661f7f52d407b0023f418e1365e0d5aa8`,name:`FlxAppAccess`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAccess($all: Boolean) {
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
}`},xt={id:`0410fe39ca78d5cd046a033c326dd6bd357096aaab36212f8c0f44f9056de2c1`,name:`FlxAppAccessRevoke`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAccessRevoke($input: FlxAppAccessRevokeInput!) {
  flxAppAccessRevoke(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},St={id:`1860c58a8fdc3e9a90238fafaef5f1ecdc4bcf298ce323ef674dd35eefbe2e6c`,name:`FlxAppAdminAppearance`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminAppearance {
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
}`},Ct={id:`762de9d5115d73d8f01452c7b5f8a8ac15213df2102f3986d735018483a7670b`,name:`FlxAppAdminAppearanceSave`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminAppearanceSave($input: FlxAppAdminAppearanceSaveInput!) {
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
}`},wt={id:`14ba5b9cfa34c05e5bef9a80d5087b56019d5b4c4a5a22ce7810d12dd6089f8c`,name:`FlxAppAdminClientCreate`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminClientCreate($input: FlxAppAdminClientCreateInput!) {
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
}`},Tt={id:`0acad378aa0aa39d0d878475c0e4c2c5e8a5a09045e3bd946641c722c6b0f44b`,name:`FlxAppAdminClientDelete`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminClientDelete($input: FlxAppAdminClientDeleteInput!) {
  flxAppAdminClientDelete(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},Et={id:`0aa8eaf58f40a410644147b5f052aa2441f94a7c247af742f281bd5590f5b61f`,name:`FlxAppAdminClients`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminClients {
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
}`},Dt={id:`67b0edec2f4ef8fb57c5f796df9e32ec167f35dae7bec4196df4edcdff650a82`,name:`FlxAppAdminMenu`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminMenu {
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
}`},Ot={id:`e27de38feec02d07f97aa0a6cee1f3782bf39e4b41f0fd3cf84edb52d04a7d74`,name:`FlxAppAdminMobile`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminMobile {
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
}`},kt={id:`3140882c7e64b03a33a9bdeaab1dc3b5eb7c50ba5709a2a95778672679795ae2`,name:`FlxAppAdminMobileSave`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminMobileSave($input: FlxAppAdminMobileSaveInput!) {
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
}`},At={id:`23953eab015437dc0f727a62ee3d73cb1e6cb45be22fc7f6b79e101318571d10`,name:`FlxAppAdminModules`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminModules {
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
}`},jt={id:`a7aad76ad18dbda2567f29ad4d8b488efe4043659ae782a8d0f92eefba54349c`,name:`FlxAppAdminModulesSave`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminModulesSave($input: FlxAppAdminModulesSaveInput!) {
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
}`},Mt={id:`b9dddc6a4c12070645fd30a6fbdf39863d2b58ea1cbe71f7ebb5ee2db6bc9b3a`,name:`FlxAppAdminPushTest`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminPushTest {
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
}`},Nt={id:`b7d4bf6833511227a38948aef18cf38f05a29043d4dbfc3b04e49fcc1878ec06`,name:`FlxAppAdminSettings`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminSettings {
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
}`},Pt={id:`fc53aca192620408e9133dd653741e741a10c7d19ada18e38845f088b88e1788`,name:`FlxAppAdminSettingsSave`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminSettingsSave($input: FlxAppAdminSettingsSaveInput!) {
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
}`},Ft={id:`9f61478d3bc50857048da2499c97ed5de0bdc51362012d2153e2363175cb7d37`,name:`FlxAppDeviceRevoke`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppDeviceRevoke($input: FlxAppDeviceRevokeInput!) {
  flxAppDeviceRevoke(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},It={id:`49577b1d3b994160a8f4f89898086f3ffb70db6242a809e48b3515c7268d6e21`,name:`FlxAppKeyCreate`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppKeyCreate($input: FlxAppKeyCreateInput!) {
  flxAppKeyCreate(input: $input) {
    key
    errors {
      code
      message
      field
    }
  }
}`},Lt={id:`8488b51d8ac2b33ecbd21791e9f59b8915e6afc07bd6bb903a5c002010852316`,name:`FlxBackupCancel`,kind:`mutation`,plugin:`flx-backup`,document:`mutation FlxBackupCancel {
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
}`},Rt={id:`5e0c2cbc788253a2687dbd388201e2a17fd45703bb4e80d90ee27db885b7a7dd`,name:`FlxBackupDashboard`,kind:`query`,plugin:`flx-backup`,document:`query FlxBackupDashboard {
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
}`},zt={id:`762db37c8115f91fa0708dd983e1d5a6497ff9e471a1d48d4736e39b966030c8`,name:`FlxBackupDelete`,kind:`mutation`,plugin:`flx-backup`,document:`mutation FlxBackupDelete($input: FlxBackupDeleteInput!) {
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
}`},Bt={id:`7efa2a37904f7bc808c0c94dd88aad63935b3f1b89f6d6f859769c0ffbfedfe8`,name:`FlxBackupDetail`,kind:`query`,plugin:`flx-backup`,document:`query FlxBackupDetail($id: ID!) {
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
}`},Vt={id:`3441ccc32a645f6092c405c3bea324de1e67815de578be76bebfeb991c2c0567`,name:`FlxBackupJobRead`,kind:`query`,plugin:`flx-backup`,document:`query FlxBackupJobRead {
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
}`},Ht={id:`444fe54b9b0e5cdeba4d419732fc6004b26926ea3e5ae242b820ccafc8b0d12c`,name:`FlxBackupList`,kind:`query`,plugin:`flx-backup`,document:`query FlxBackupList($first: Int = 20, $after: String, $type: FlxBackupType, $status: FlxBackupStatus) {
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
}`},Ut={id:`48e64891298acc8e11a7b7988f76d522414a779aa2ded6cac1b81642c7bd6d87`,name:`FlxBackupLock`,kind:`mutation`,plugin:`flx-backup`,document:`mutation FlxBackupLock($input: FlxBackupLockInput!) {
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
}`},Wt={id:`1f63d67d4775c30a38621420617d5fe48b180e7fcb37ed2548169f8185236331`,name:`FlxBackupRestoreStart`,kind:`mutation`,plugin:`flx-backup`,document:`mutation FlxBackupRestoreStart($input: FlxBackupRestoreStartInput!) {
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
}`},Gt={id:`fc5a400fc1bcd3cc9add10409ab93b7e08cff77540a6c3e4c425d4aba82bcd5e`,name:`FlxBackupSettingsSave`,kind:`mutation`,plugin:`flx-backup`,document:`mutation FlxBackupSettingsSave($input: FlxBackupSettingsSaveInput!) {
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
}`},Kt={id:`0c1e33d45c14005f514bea1ded294ed69a0d03119f779346d180c9953fb404d5`,name:`FlxBackupSettingsScreen`,kind:`query`,plugin:`flx-backup`,document:`query FlxBackupSettingsScreen {
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
}`},qt={id:`6b6e92e138ef921340c8a8bd7a2c1f56d1fef85f289f28d8552e1a13c2f5afd2`,name:`FlxBackupStart`,kind:`mutation`,plugin:`flx-backup`,document:`mutation FlxBackupStart($input: FlxBackupStartInput!) {
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
}`},Jt={id:`3ca258ac5c8de7419cf97041ab75861522f24988dd7c92e5b855a4a785a25b1d`,name:`FlxBackupStep`,kind:`mutation`,plugin:`flx-backup`,document:`mutation FlxBackupStep {
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
}`},Yt={id:`0cdd28e36785f9a36c07bcff67a19c55e28b55316009f469fc97857134e4a2b4`,name:`FlxBlocksBlockTypeSetActive`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksBlockTypeSetActive($input: FlxBlocksBlockTypeSetActiveInput!) {
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
}`},Xt={id:`a707f69c55e13b00163c2d5f835704b3a9234102fefdc59f7e9bf0e1861e5d87`,name:`FlxBlocksDashboard`,kind:`query`,plugin:`flx-blocks`,document:`query FlxBlocksDashboard {
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
}`},Zt={id:`ee8041abf4f646b0db2b506f7ede107c7bf199332d81ac3023f8a0e204408ad3`,name:`FlxBlocksLibraryPatterns`,kind:`query`,plugin:`flx-blocks`,document:`query FlxBlocksLibraryPatterns($source: ID, $category: String, $search: String, $first: Int = 24, $after: String, $refresh: Boolean) {
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
}`},Qt={id:`340869fd203dcf45e0db366bded93136560b5e3c7b8ee4e7f0efce9a99a933d1`,name:`FlxBlocksLibraryShare`,kind:`query`,plugin:`flx-blocks`,document:`query FlxBlocksLibraryShare {
  flxBlocksLibraryShare {
    ...FlxBlocksShareFields
  }
}

fragment FlxBlocksShareFields on FlxBlocksLibraryShare {
  enabled
  key
  url
}`},$t={id:`a3ae64b495e2c2a1dc0d327b08e2df372ccea65c7c1827f676f6445bd7356177`,name:`FlxBlocksLibraryShareUpdate`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksLibraryShareUpdate($input: FlxBlocksLibraryShareUpdateInput!) {
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
}`},en={id:`aab0d512355bb050e77a897874c870786b849b10ce29b327b1d353869d17292c`,name:`FlxBlocksLibrarySourceAdd`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksLibrarySourceAdd($input: FlxBlocksLibrarySourceAddInput!) {
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
}`},tn={id:`5406d45161cd50d86861128e261f0716f087150902c42c151688c7ad997ab317`,name:`FlxBlocksLibrarySourceRemove`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksLibrarySourceRemove($input: FlxBlocksLibrarySourceRemoveInput!) {
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
}`},nn={id:`cebc38b2c4a42ddcdabe0145d488d587190ee259688daac8f0b2ea1e7599d82e`,name:`FlxBlocksPatternPreview`,kind:`query`,plugin:`flx-blocks`,document:`query FlxBlocksPatternPreview($id: String!, $source: ID) {
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
}`},rn={id:`c22bde71875f8c21a1d1477eb43dad65257b059c911c8ad41284ffe3ba8c18e8`,name:`FlxBlocksSettingsUpdate`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksSettingsUpdate($input: FlxBlocksSettingsUpdateInput!) {
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
}`},an={id:`38480d70ee075594e901b6bf3a4fa61b09539c106816f485dbb5dfe5648228fe`,name:`FlxCertificateAttachFiles`,kind:`mutation`,plugin:`flx-certificates`,document:`mutation FlxCertificateAttachFiles($input: FlxCertificateAttachFilesInput!) {
  flxCertificateAttachFiles(input: $input) {
    certificate {
      id
      pdfUrl
      imageUrl
    }
    errors {
      code
      field
      message
    }
  }
}`},on={id:`78bc82a5924200bb71f1eba0ecd98140aa11d208f74bf2be7c79c6fc54846e11`,name:`FlxCertificateBulk`,kind:`mutation`,plugin:`flx-certificates`,document:`mutation FlxCertificateBulk($input: FlxCertificateBulkInput!) {
  flxCertificateBulk(input: $input) {
    updated
    queued
    redirect
    errors {
      code
      field
      message
    }
  }
}`},sn={id:`7d262976a853415b7a9f8b1d1b45d3aa6ca456b448e5174ac4c75c1824d3ffef`,name:`FlxCertificateCreate`,kind:`mutation`,plugin:`flx-certificates`,document:`mutation FlxCertificateCreate($input: FlxCertificateCreateInput!) {
  flxCertificateCreate(input: $input) {
    certificate {
      ...FlxCertificateRow
    }
    person {
      id
      databaseId
      name
    }
    notes
    errors {
      code
      field
      message
    }
  }
}

fragment FlxCertificateRow on FlxCertificate {
  id
  databaseId
  number
  status
  title
  holderName
  code
  pageUrl
  pdfUrl
  imageUrl
  views
  backdated
  createdAt {
    label
  }
  eventId
  eventName
  templateId
  templateName
  referrerId
  referrerName
  createdByName
  canEdit
  values
  person {
    id
    databaseId
    name
    email
    phone {
      e164
      digits
      display
    }
  }
  deliveries {
    channel
    sent
    failed
    waiting
  }
}`},cn={id:`54b1ee14f59be117c1a59009d8f014d246f67b22a5976bb6910cecece354657b`,name:`FlxCertificateCreateConfig`,kind:`query`,plugin:`flx-certificates`,document:`query FlxCertificateCreateConfig($certificateId: Int) {
  flxCertificateCreateConfig(certificateId: $certificateId) {
    canManage
    today
    me
    lastTemplateId
    rendererScripts
    rendererConfig
    templates {
      id
      name
      active
      config
    }
    events {
      id
      name
      status
    }
    referrers {
      id
      name
    }
    providers {
      channel
      id
      name
    }
  }
}`},ln={id:`d445cfb1bb001b3e7ace765907ba5e19c2d0730b29fda4d77535bd73c19725ef`,name:`FlxCertificateDirectory`,kind:`query`,plugin:`flx-certificates`,document:`query FlxCertificateDirectory($search: String, $first: Int!, $after: String) {
  flxCertificateDirectory(search: $search, first: $first, after: $after) {
    nodes {
      id
      databaseId
      name
      email
      phone {
        e164
        digits
        display
      }
      note
      certificateCount
      createdAt {
        label
      }
      createdByName
    }
    pageInfo {
      hasNextPage
      endCursor
    }
    totalCount
  }
}`},un={id:`5202dd61eff74bf72a989d2e9fddce08d68cc94a41cc1b8a0e6fb82860c6ae05`,name:`FlxCertificateOne`,kind:`query`,plugin:`flx-certificates`,document:`query FlxCertificateOne($id: Int!) {
  flxCertificate(id: $id) {
    ...FlxCertificateRow
  }
}

fragment FlxCertificateRow on FlxCertificate {
  id
  databaseId
  number
  status
  title
  holderName
  code
  pageUrl
  pdfUrl
  imageUrl
  views
  backdated
  createdAt {
    label
  }
  eventId
  eventName
  templateId
  templateName
  referrerId
  referrerName
  createdByName
  canEdit
  values
  person {
    id
    databaseId
    name
    email
    phone {
      e164
      digits
      display
    }
  }
  deliveries {
    channel
    sent
    failed
    waiting
  }
}`},dn={id:`63c3a7065d4066b30ea90f2e1ea4ec6e59c30e054a3883ac08f0954fb04ad5be`,name:`FlxCertificatePerson`,kind:`query`,plugin:`flx-certificates`,document:`query FlxCertificatePerson($id: Int!) {
  flxCertificatePerson(id: $id) {
    id
    databaseId
    name
    email
    phone {
      display
    }
    note
    certificateCount
    createdAt {
      label
    }
    createdByName
  }
  flxCertificates(first: 50, filter: { personId: $id }) {
    nodes {
      ...FlxCertificateRow
    }
    totalCount
  }
}

fragment FlxCertificateRow on FlxCertificate {
  id
  databaseId
  number
  status
  title
  holderName
  code
  pageUrl
  pdfUrl
  imageUrl
  views
  backdated
  createdAt {
    label
  }
  eventId
  eventName
  templateId
  templateName
  referrerId
  referrerName
  createdByName
  canEdit
  values
  person {
    id
    databaseId
    name
    email
    phone {
      e164
      digits
      display
    }
  }
  deliveries {
    channel
    sent
    failed
    waiting
  }
}`},fn={id:`829d0fbbd990f5c78cd6d9d44103de61aecaf0da57eca859c229c20df0928fc9`,name:`FlxCertificatePersonDelete`,kind:`mutation`,plugin:`flx-certificates`,document:`mutation FlxCertificatePersonDelete($input: FlxCertificatePersonDeleteInput!) {
  flxCertificatePersonDelete(input: $input) {
    deleted
    errors {
      code
      field
      message
    }
  }
}`},pn={id:`75fb6ccfdc9600ce24d9967c1c9048b17c71825b1bb9121952fbf11a9fd01e87`,name:`FlxCertificatePersonMessages`,kind:`query`,plugin:`flx-certificates`,document:`query FlxCertificatePersonMessages($personId: Int!) {
  flxCertificatePersonMessages(personId: $personId) {
    channel
    recipient
    status
    at {
      label
    }
    createdByName
  }
}`},mn={id:`58f2ac83168bc76be1786e917bf203296d8ab6b39d65033a9b94553abb47a71b`,name:`FlxCertificatePersonSave`,kind:`mutation`,plugin:`flx-certificates`,document:`mutation FlxCertificatePersonSave($input: FlxCertificatePersonSaveInput!) {
  flxCertificatePersonSave(input: $input) {
    person {
      id
      databaseId
      name
      email
      phone {
        display
      }
      note
    }
    errors {
      code
      field
      message
    }
  }
}`},hn={id:`503c977120b7d23e060c0167c686a9a0d6bd54130b6b92916d8d7416696dbbe8`,name:`FlxCertificateRecords`,kind:`query`,plugin:`flx-certificates`,document:`query FlxCertificateRecords($first: Int!, $after: String, $filter: FlxCertificateFilterInput) {
  flxCertificates(first: $first, after: $after, filter: $filter) {
    nodes {
      ...FlxCertificateRow
    }
    pageInfo {
      hasNextPage
      endCursor
    }
    totalCount
  }
  flxCertificateRecordsMeta(filter: $filter) {
    canManage
    canBulk
    scoped
    me
    noEventCount
    noReferrerCount
    exportUrl
    templates {
      id
      name
      active
      count
    }
    events {
      id
      name
      status
      count
    }
    referrers {
      id
      name
      count
    }
    providers {
      channel
      id
      name
    }
  }
}

fragment FlxCertificateRow on FlxCertificate {
  id
  databaseId
  number
  status
  title
  holderName
  code
  pageUrl
  pdfUrl
  imageUrl
  views
  backdated
  createdAt {
    label
  }
  eventId
  eventName
  templateId
  templateName
  referrerId
  referrerName
  createdByName
  canEdit
  values
  person {
    id
    databaseId
    name
    email
    phone {
      e164
      digits
      display
    }
  }
  deliveries {
    channel
    sent
    failed
    waiting
  }
}`},gn={id:`3ebbb5d9d7f735bb420df2c2f927170b3e31fa1a9e257bbb84c74a519ba22d05`,name:`FlxCertificateSend`,kind:`mutation`,plugin:`flx-certificates`,document:`mutation FlxCertificateSend($input: FlxCertificateSendInput!) {
  flxCertificateSend(input: $input) {
    results {
      channel
      ok
      message
    }
    errors {
      code
      field
      message
    }
  }
}`},_n={id:`e9f1d691fedbbb0148b208326b2235aeb2f27c130e672266c57f58e1bfb8a415`,name:`FlxCertificateSendTo`,kind:`mutation`,plugin:`flx-certificates`,document:`mutation FlxCertificateSendTo($input: FlxCertificateSendToInput!) {
  flxCertificateSendTo(input: $input) {
    results {
      channel
      ok
      message
    }
    errors {
      code
      field
      message
    }
  }
}`},vn={id:`4d0212937844b0ee61dd841fe28bd2551205d63ad50bbf1e0468cfd311fab438`,name:`FlxCertificateSetReferrer`,kind:`mutation`,plugin:`flx-certificates`,document:`mutation FlxCertificateSetReferrer($input: FlxCertificateSetReferrerInput!) {
  flxCertificateSetReferrer(input: $input) {
    certificate {
      id
      referrerId
      referrerName
    }
    errors {
      code
      field
      message
    }
  }
}`},yn={id:`529c807007c76195c53dbe131c098b75400478cdbed2dad3a5fe1689173dc893`,name:`FlxCertificateSetStatus`,kind:`mutation`,plugin:`flx-certificates`,document:`mutation FlxCertificateSetStatus($input: FlxCertificateSetStatusInput!) {
  flxCertificateSetStatus(input: $input) {
    certificate {
      id
      status
      pdfUrl
      imageUrl
    }
    errors {
      code
      field
      message
    }
  }
}`},bn={id:`d799e87f2572e2b103584e4320ab0353aadeb428d9aad40f8b78b0de59620add`,name:`FlxCertificateUpdate`,kind:`mutation`,plugin:`flx-certificates`,document:`mutation FlxCertificateUpdate($input: FlxCertificateUpdateInput!) {
  flxCertificateUpdate(input: $input) {
    certificate {
      ...FlxCertificateRow
    }
    person {
      id
      databaseId
      name
    }
    notes
    errors {
      code
      field
      message
    }
  }
}

fragment FlxCertificateRow on FlxCertificate {
  id
  databaseId
  number
  status
  title
  holderName
  code
  pageUrl
  pdfUrl
  imageUrl
  views
  backdated
  createdAt {
    label
  }
  eventId
  eventName
  templateId
  templateName
  referrerId
  referrerName
  createdByName
  canEdit
  values
  person {
    id
    databaseId
    name
    email
    phone {
      e164
      digits
      display
    }
  }
  deliveries {
    channel
    sent
    failed
    waiting
  }
}`},xn={id:`edb4984f25c406a9143913da2ef715d97887a74bbacdcda82da3f7a8c9707ca2`,name:`FlxChatbotConversationClose`,kind:`mutation`,plugin:`flx-chatbot`,document:`mutation FlxChatbotConversationClose($input: FlxChatbotConversationCloseInput!) {
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
}`},Sn={id:`06f77cf96adcd58c306be0a2123151c07f7b20c102c675ac503f4e0f2193d3aa`,name:`FlxChatbotConversationDelete`,kind:`mutation`,plugin:`flx-chatbot`,document:`mutation FlxChatbotConversationDelete($input: FlxChatbotConversationDeleteInput!) {
  flxChatbotConversationDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},Cn={id:`0159e5c0272f4a76eb58d3908ee9e42355887bca6384a602ca16fa3ded368fb7`,name:`FlxChatbotConversationList`,kind:`query`,plugin:`flx-chatbot`,document:`query FlxChatbotConversationList($filter: FlxChatbotConversationFilter, $search: String, $first: Int = 50) {
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
}`},wn={id:`c22dcbbc9132d33808d9995d8744230f8914601afae91ff9f71b5aa8e40f71f4`,name:`FlxChatbotConversationOpen`,kind:`query`,plugin:`flx-chatbot`,document:`query FlxChatbotConversationOpen($id: ID!, $afterId: Int = 0, $peek: Boolean = false) {
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
}`},Tn={id:`019b8ae4942af3eb50b362f80c7946ad71090cbad2a0b11a8383c6dc969665f8`,name:`FlxChatbotConversationReply`,kind:`mutation`,plugin:`flx-chatbot`,document:`mutation FlxChatbotConversationReply($input: FlxChatbotConversationReplyInput!) {
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
}`},En={id:`4b73e1d9351052ac89be5c9f77fe281fc9c69498e55c1b83e984e65c49ddcafd`,name:`FlxChatbotConversationTyping`,kind:`mutation`,plugin:`flx-chatbot`,document:`mutation FlxChatbotConversationTyping($input: FlxChatbotConversationTypingInput!) {
  flxChatbotConversationTyping(input: $input) {
    ok
  }
}`},Dn={id:`2578214d2427732b0bca1b77938018e459d2b57a4a4b50fab18baa2c9c90171f`,name:`FlxChatbotInbox`,kind:`query`,plugin:`flx-chatbot`,document:`query FlxChatbotInbox {
  flxChatbotInbox {
    fluxChatUrl
    settingsUrl
    canDelete
    agentName
    maxUploadMb
  }
}`},On={id:`dd194ad3bf7141b6584ae9722f80c6df83445e4000e35eb3015caa0b9b093059`,name:`FlxChatBotSet`,kind:`mutation`,plugin:`flx-chat`,document:`mutation FlxChatBotSet($input: FlxChatBotSetInput!) {
  flxChatBotSet(input: $input) {
    chat {
      ...FlxChatFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxChatFields on FlxChat {
  databaseId
  connectionId
  jid
  phoneDigits
  phoneFmt
  channel
  title
  name
  pushName
  avatarUrl
  labels
  notes
  status
  assignedTo
  unread
  pinned
  lastMs
  lastInMs
  group
  typing
  rev
  createdMs
  unknownName
  blocked
  optOut
  bot
  botBusy
  ai
  lastMessage {
    dir
    type
    body
    status
    user
  }
}`},kn={id:`58380f4eafb8b23ee0ed48cc1b49d596c5a05fda9b8cce508d5cc3a3ebcd0b4c`,name:`FlxChatbotSettingsSave`,kind:`mutation`,plugin:`flx-chatbot`,document:`mutation FlxChatbotSettingsSave($input: FlxChatbotSettingsSaveInput!) {
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
}`},An={id:`c116623608453060a2014d87ec9efcc566c9956ce079c536f86803f37e4f56e7`,name:`FlxChatbotSettingsScreen`,kind:`query`,plugin:`flx-chatbot`,document:`query FlxChatbotSettingsScreen($days: Int = 30) {
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
}`},jn={id:`d2c81ff7d98d4dfca632940677e838977aff02b80322cda0dea02e91d397ee4c`,name:`FlxChatCheckNumber`,kind:`query`,plugin:`flx-chat`,document:`query FlxChatCheckNumber($phone: String!, $conn: String) {
  flxChatCheckNumber(phone: $phone, conn: $conn) {
    valid
    phone
    phoneFmt
    exists
    chatId
    name
  }
}`},Mn={id:`3824b78d5ad9f5d8e5e7d33fd70253e054a104d44cadf2eaa98b9ac2730fed0e`,name:`FlxChatCreate`,kind:`mutation`,plugin:`flx-chat`,document:`mutation FlxChatCreate($input: FlxChatCreateInput!) {
  flxChatCreate(input: $input) {
    chat {
      ...FlxChatFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxChatFields on FlxChat {
  databaseId
  connectionId
  jid
  phoneDigits
  phoneFmt
  channel
  title
  name
  pushName
  avatarUrl
  labels
  notes
  status
  assignedTo
  unread
  pinned
  lastMs
  lastInMs
  group
  typing
  rev
  createdMs
  unknownName
  blocked
  optOut
  bot
  botBusy
  ai
  lastMessage {
    dir
    type
    body
    status
    user
  }
}`},Nn={id:`449bbaf09d92eb30b6c7a6082f65cb5c1132d665b04b939d3231e1375f29ba31`,name:`FlxChatInbox`,kind:`query`,plugin:`flx-chat`,document:`query FlxChatInbox {
  flxChatInbox {
    me {
      databaseId
      name
      manage
    }
    delHours
    agents {
      databaseId
      name
    }
    labels {
      id
      name
      color
    }
    connections {
      id
      label
      color
      state
    }
    replies {
      databaseId
      shortcut
      title
      body
    }
    unread
    ai
    bot
    rev
    classicUrl
  }
}`},Pn={id:`db34aa00716f7d4845997146ca6f0a6bc420b3f9fbe51c80d2b95004606590cd`,name:`FlxChatLinkPreview`,kind:`query`,plugin:`flx-chat`,document:`query FlxChatLinkPreview($url: String!) {
  flxChatLinkPreview(url: $url) {
    url
    title
    desc
    image
    site
    domain
  }
}`},Fn={id:`9511d71e0c1d86f66d91a20cfba589edbae425a16c40cc0f012ac0843e208788`,name:`FlxChatList`,kind:`query`,plugin:`flx-chat`,document:`query FlxChatList($filter: FlxChatFilter, $search: String, $label: String, $channel: String, $first: Int = 50, $after: String) {
  flxChats(
    filter: $filter
    search: $search
    label: $label
    channel: $channel
    first: $first
    after: $after
  ) {
    nodes {
      ...FlxChatFields
    }
    pageInfo {
      hasNextPage
      endCursor
    }
    totalCount
  }
}

fragment FlxChatFields on FlxChat {
  databaseId
  connectionId
  jid
  phoneDigits
  phoneFmt
  channel
  title
  name
  pushName
  avatarUrl
  labels
  notes
  status
  assignedTo
  unread
  pinned
  lastMs
  lastInMs
  group
  typing
  rev
  createdMs
  unknownName
  blocked
  optOut
  bot
  botBusy
  ai
  lastMessage {
    dir
    type
    body
    status
    user
  }
}`},In={id:`fdcda525c1e35f9d5a458d6f10a9277c761b8d4ffc61ea88a8251ca6bd674a95`,name:`FlxChatMarkRead`,kind:`mutation`,plugin:`flx-chat`,document:`mutation FlxChatMarkRead($input: FlxChatMarkReadInput!) {
  flxChatMarkRead(input: $input) {
    chat {
      databaseId
      unread
    }
    errors {
      code
      message
      field
    }
  }
}`},Ln={id:`962434763d8634c779e1c288803728472009af1a1fa3d762792c00dbeec51131`,name:`FlxChatMessageDelete`,kind:`mutation`,plugin:`flx-chat`,document:`mutation FlxChatMessageDelete($input: FlxChatMessageDeleteInput!) {
  flxChatMessageDelete(input: $input) {
    message {
      ...FlxChatMessageFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxChatMessageFields on FlxChatMessage {
  databaseId
  chatId
  dir
  type
  body
  status
  error
  author
  authorId
  source
  sender
  ts
  rev
  edited
  deleted
  media {
    ptt
    ready
    secs
    file {
      url
      name
      mime
      size
    }
  }
  quoted {
    databaseId
    dir
    type
    body
    author
  }
  reactions {
    side
    emoji
  }
}`},Rn={id:`d99ee3b1e8abf76a2f2ee33014f904cc15f69c0391978bd6060800db26ad7ddf`,name:`FlxChatMessageReact`,kind:`mutation`,plugin:`flx-chat`,document:`mutation FlxChatMessageReact($input: FlxChatMessageReactInput!) {
  flxChatMessageReact(input: $input) {
    message {
      ...FlxChatMessageFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxChatMessageFields on FlxChatMessage {
  databaseId
  chatId
  dir
  type
  body
  status
  error
  author
  authorId
  source
  sender
  ts
  rev
  edited
  deleted
  media {
    ptt
    ready
    secs
    file {
      url
      name
      mime
      size
    }
  }
  quoted {
    databaseId
    dir
    type
    body
    author
  }
  reactions {
    side
    emoji
  }
}`},zn={id:`b3144eea7da75ee05110b6b15b336f85bd5022de42fd6c2393a29077bac0ff9e`,name:`FlxChatMessageRetry`,kind:`mutation`,plugin:`flx-chat`,document:`mutation FlxChatMessageRetry($input: FlxChatMessageRetryInput!) {
  flxChatMessageRetry(input: $input) {
    message {
      ...FlxChatMessageFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxChatMessageFields on FlxChatMessage {
  databaseId
  chatId
  dir
  type
  body
  status
  error
  author
  authorId
  source
  sender
  ts
  rev
  edited
  deleted
  media {
    ptt
    ready
    secs
    file {
      url
      name
      mime
      size
    }
  }
  quoted {
    databaseId
    dir
    type
    body
    author
  }
  reactions {
    side
    emoji
  }
}`},Bn={id:`09d20eafbeb8d57a7ad272cfdc736f5693a3092156c19204244707468c0a65e8`,name:`FlxChatMessages`,kind:`query`,plugin:`flx-chat`,document:`query FlxChatMessages($chatId: ID!, $before: Int, $first: Int = 40) {
  flxChatMessages(chatId: $chatId, before: $before, first: $first) {
    messages {
      ...FlxChatMessageFields
    }
    hasMore
    note
  }
}

fragment FlxChatMessageFields on FlxChatMessage {
  databaseId
  chatId
  dir
  type
  body
  status
  error
  author
  authorId
  source
  sender
  ts
  rev
  edited
  deleted
  media {
    ptt
    ready
    secs
    file {
      url
      name
      mime
      size
    }
  }
  quoted {
    databaseId
    dir
    type
    body
    author
  }
  reactions {
    side
    emoji
  }
}`},Vn={id:`0dc417c7125851f44108892094c8f41b472397dcea3f6ed803481900aa1e1d3e`,name:`FlxChatMessageSend`,kind:`mutation`,plugin:`flx-chat`,document:`mutation FlxChatMessageSend($input: FlxChatMessageSendInput!) {
  flxChatMessageSend(input: $input) {
    message {
      ...FlxChatMessageFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxChatMessageFields on FlxChatMessage {
  databaseId
  chatId
  dir
  type
  body
  status
  error
  author
  authorId
  source
  sender
  ts
  rev
  edited
  deleted
  media {
    ptt
    ready
    secs
    file {
      url
      name
      mime
      size
    }
  }
  quoted {
    databaseId
    dir
    type
    body
    author
  }
  reactions {
    side
    emoji
  }
}`},Hn={id:`8d0c870e4a157b67f509ad0610958726034c2abedcc925eb3c2b0d46f0d258ed`,name:`FlxChatOpen`,kind:`query`,plugin:`flx-chat`,document:`query FlxChatOpen($id: ID!) {
  flxChat(id: $id) {
    ...FlxChatFields
    sections {
      id
      title
      items {
        label
        sub
        url
      }
    }
  }
}

fragment FlxChatFields on FlxChat {
  databaseId
  connectionId
  jid
  phoneDigits
  phoneFmt
  channel
  title
  name
  pushName
  avatarUrl
  labels
  notes
  status
  assignedTo
  unread
  pinned
  lastMs
  lastInMs
  group
  typing
  rev
  createdMs
  unknownName
  blocked
  optOut
  bot
  botBusy
  ai
  lastMessage {
    dir
    type
    body
    status
    user
  }
}`},Un={id:`f0f2593ffba14f99e614eacae458a85b950c030d09927e9ca759c7d6c83c5b11`,name:`FlxChatReplyUse`,kind:`mutation`,plugin:`flx-chat`,document:`mutation FlxChatReplyUse($input: FlxChatReplyUseInput!) {
  flxChatReplyUse(input: $input) {
    text
    errors {
      code
      message
      field
    }
  }
}`},Wn={id:`fcf822c88cda28ecc764979d299ead1ea4a4a9553aa9d48d7da15e341b174799`,name:`FlxChatSuggest`,kind:`mutation`,plugin:`flx-chat`,document:`mutation FlxChatSuggest($input: FlxChatSuggestInput!) {
  flxChatSuggest(input: $input) {
    text
    errors {
      code
      message
      field
    }
  }
}`},Gn={id:`2e591858d54938bb6164aba17dc8fb71a7f017a7f5a43cd507427bca67c18867`,name:`FlxChatTyping`,kind:`mutation`,plugin:`flx-chat`,document:`mutation FlxChatTyping($input: FlxChatTypingInput!) {
  flxChatTyping(input: $input) {
    ok
  }
}`},Kn={id:`5f7ff863046c3ff55bee64d92092ae84643860040e12137d51baea4346ed25c5`,name:`FlxChatUpdate`,kind:`mutation`,plugin:`flx-chat`,document:`mutation FlxChatUpdate($input: FlxChatUpdateInput!) {
  flxChatUpdate(input: $input) {
    chat {
      ...FlxChatFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxChatFields on FlxChat {
  databaseId
  connectionId
  jid
  phoneDigits
  phoneFmt
  channel
  title
  name
  pushName
  avatarUrl
  labels
  notes
  status
  assignedTo
  unread
  pinned
  lastMs
  lastInMs
  group
  typing
  rev
  createdMs
  unknownName
  blocked
  optOut
  bot
  botBusy
  ai
  lastMessage {
    dir
    type
    body
    status
    user
  }
}`},qn={id:`877bbf978da86e3fd9937ccc67b1987c5554f9a1cece7d11b9f14a98c9f38bce`,name:`FlxChatUpdates`,kind:`query`,plugin:`flx-chat`,document:`query FlxChatUpdates($since: String!, $after: Int, $chatId: ID, $afterChat: Int) {
  flxChatUpdates(
    since: $since
    after: $after
    chatId: $chatId
    afterChat: $afterChat
  ) {
    rev
    continueRev
    continueId
    truncated
    messagesTruncated
    unread
    chats {
      ...FlxChatFields
    }
    messages {
      ...FlxChatMessageFields
    }
    incoming {
      databaseId
      chatId
      title
      body
    }
    connections {
      id
      label
      color
      state
    }
  }
}

fragment FlxChatFields on FlxChat {
  databaseId
  connectionId
  jid
  phoneDigits
  phoneFmt
  channel
  title
  name
  pushName
  avatarUrl
  labels
  notes
  status
  assignedTo
  unread
  pinned
  lastMs
  lastInMs
  group
  typing
  rev
  createdMs
  unknownName
  blocked
  optOut
  bot
  botBusy
  ai
  lastMessage {
    dir
    type
    body
    status
    user
  }
}

fragment FlxChatMessageFields on FlxChatMessage {
  databaseId
  chatId
  dir
  type
  body
  status
  error
  author
  authorId
  source
  sender
  ts
  rev
  edited
  deleted
  media {
    ptt
    ready
    secs
    file {
      url
      name
      mime
      size
    }
  }
  quoted {
    databaseId
    dir
    type
    body
    author
  }
  reactions {
    side
    emoji
  }
}`},Jn={id:`61294f7a47cb7e5d55e35fa7537ac944ad4e3b1d0ddef1e0a45bda1de654e65f`,name:`FlxCommerceBankSettingsSave`,kind:`mutation`,plugin:`flx-commerce`,document:`mutation FlxCommerceBankSettingsSave($input: FlxCommerceBankSettingsSaveInput!) {
  flxCommerceBankSettingsSave(input: $input) {
    settings {
      enabled
      title
      iban
    }
    errors {
      code
      message
      field
    }
  }
}`},Yn={id:`024cb05ad1c6a52783d6de1f806ffba892c6f6fa80eae894a146417d58143734`,name:`FlxCommerceCustomer`,kind:`query`,plugin:`flx-commerce`,document:`query FlxCommerceCustomer($id: ID!) {
  flxCommerceCustomer(id: $id) {
    ...FlxCommerceCustomerRow
    openOrders
    addresses {
      kind
      text
    }
    products {
      productId
      name
      quantity
      total {
        ...FlxCommerceMoney
      }
    }
    recentOrders {
      id
      number
      status
      statusLabel
      paid
      url
      total {
        ...FlxCommerceMoney
      }
      createdAt {
        ...FlxCommerceWhen
      }
    }
    timeline {
      type
      label
      title
      text
      url
      paid
      at {
        ...FlxCommerceWhen
      }
    }
  }
}

fragment FlxCommerceCustomerRow on FlxCommerceCustomer {
  id
  name
  email
  phone
  city
  orders
  userId
  guest
  note
  total {
    ...FlxCommerceMoney
  }
  firstAt {
    ...FlxCommerceWhen
  }
  lastAt {
    ...FlxCommerceWhen
  }
}

fragment FlxCommerceMoney on FlxMoney {
  amount
  currency
  formatted
}

fragment FlxCommerceWhen on FlxDateTime {
  iso
  timestamp
  label
  relative
}`},Xn={id:`b3fe03c1b02e9c6d015275ad990f79b526aac70e397a000ccb3e83afbf823d6c`,name:`FlxCommerceCustomerNote`,kind:`mutation`,plugin:`flx-commerce`,document:`mutation FlxCommerceCustomerNote($input: FlxCommerceCustomerNoteInput!) {
  flxCommerceCustomerNote(input: $input) {
    customer {
      id
      note
    }
    errors {
      code
      message
      field
    }
  }
}`},Zn={id:`0fd386a20519e54e969b906f3cc5a726c7c5c9b74a6faa0574ec4c9477977e8a`,name:`FlxCommerceCustomers`,kind:`query`,plugin:`flx-commerce`,document:`query FlxCommerceCustomers($first: Int = 25, $after: String, $search: String, $segment: FlxCommerceSegment, $orderby: FlxCommerceCustomerSort, $order: FlxCommerceSortDirection) {
  flxCommerceCustomers(
    first: $first
    after: $after
    search: $search
    segment: $segment
    orderby: $orderby
    order: $order
  ) {
    nodes {
      ...FlxCommerceCustomerRow
    }
    pageInfo {
      hasNextPage
      endCursor
    }
    totalCount
  }
  flxCommerceCustomerCounts {
    all
    repeat
    vip
    lapsed
    new
    built
    builtAt {
      ...FlxCommerceWhen
    }
    spent {
      ...FlxCommerceMoney
    }
    ltv {
      ...FlxCommerceMoney
    }
  }
}

fragment FlxCommerceCustomerRow on FlxCommerceCustomer {
  id
  name
  email
  phone
  city
  orders
  userId
  guest
  note
  total {
    ...FlxCommerceMoney
  }
  firstAt {
    ...FlxCommerceWhen
  }
  lastAt {
    ...FlxCommerceWhen
  }
}

fragment FlxCommerceMoney on FlxMoney {
  amount
  currency
  formatted
}

fragment FlxCommerceWhen on FlxDateTime {
  iso
  timestamp
  label
  relative
}`},Qn={id:`48c26ee86009532b22868209d6f06b8fc9e7a5feecf91be19a16fb557d8b68b2`,name:`FlxCommerceCustomersRebuild`,kind:`mutation`,plugin:`flx-commerce`,document:`mutation FlxCommerceCustomersRebuild($input: FlxCommerceCustomersRebuildInput!) {
  flxCommerceCustomersRebuild(input: $input) {
    done
    page
    scanned
    total
    errors {
      code
      message
      field
    }
  }
}`},$n={id:`b908b002213aa504f9bc33c3a7f3dc9959431409ecf9a80aa0ad2cb0f3f53ed9`,name:`FlxCommerceReceiptApprove`,kind:`mutation`,plugin:`flx-commerce`,document:`mutation FlxCommerceReceiptApprove($input: FlxCommerceReceiptApproveInput!) {
  flxCommerceReceiptApprove(input: $input) {
    receipt {
      id
      status
    }
    errors {
      code
      message
      field
    }
  }
}`},er={id:`22a97eab0cfd9c64f871c4ebbc98c0372ffcd2a413faf4fae7c3dde39172f2f5`,name:`FlxCommerceRenewalsRun`,kind:`mutation`,plugin:`flx-commerce`,document:`mutation FlxCommerceRenewalsRun($input: FlxCommerceRenewalsRunInput!) {
  flxCommerceRenewalsRun(input: $input) {
    status {
      ran
      at {
        ...FlxCommerceWhen
      }
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxCommerceWhen on FlxDateTime {
  iso
  timestamp
  label
  relative
}`},tr={id:`3968aa7d880b0d83e469e5431922d4c280f686dd6b5b242ca377ba5be37b4ce4`,name:`FlxCommerceReport`,kind:`query`,plugin:`flx-commerce`,document:`query FlxCommerceReport($preset: FlxCommerceReportPreset, $from: String, $to: String) {
  flxCommerceReport(preset: $preset, from: $from, to: $to) {
    from
    to
    orders
    items
    customers
    newCustomers
    revenue {
      ...FlxCommerceMoney
    }
    gross {
      ...FlxCommerceMoney
    }
    refunds {
      ...FlxCommerceMoney
    }
    shipping {
      ...FlxCommerceMoney
    }
    tax {
      ...FlxCommerceMoney
    }
    discount {
      ...FlxCommerceMoney
    }
    aov {
      ...FlxCommerceMoney
    }
    revenueDelta
    ordersDelta
    itemsDelta
    aovDelta
  }
}

fragment FlxCommerceMoney on FlxMoney {
  amount
  currency
  formatted
}`},nr={id:`560fe32dc0005f816d2f29f6676a51eaec9cd689cf487798f4349af6e646e038`,name:`FlxCommerceReportCategories`,kind:`query`,plugin:`flx-commerce`,document:`query FlxCommerceReportCategories($first: Int = 50, $preset: FlxCommerceReportPreset, $from: String, $to: String) {
  flxCommerceReportCategories(
    first: $first
    preset: $preset
    from: $from
    to: $to
  ) {
    nodes {
      name
      quantity
      total {
        ...FlxCommerceMoney
      }
    }
  }
}

fragment FlxCommerceMoney on FlxMoney {
  amount
  currency
  formatted
}`},rr={id:`244f0f2ab11dc4106b2fb7a59bef2c7f1c955814dc2a32b6feb5e1769b6c394a`,name:`FlxCommerceReportDays`,kind:`query`,plugin:`flx-commerce`,document:`query FlxCommerceReportDays($first: Int = 50, $after: String, $preset: FlxCommerceReportPreset, $from: String, $to: String) {
  flxCommerceReportDays(
    first: $first
    after: $after
    preset: $preset
    from: $from
    to: $to
  ) {
    nodes {
      date
      orders
      revenue {
        ...FlxCommerceMoney
      }
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}

fragment FlxCommerceMoney on FlxMoney {
  amount
  currency
  formatted
}`},ir={id:`63b9b0b2ff047347c67e785059743dbc65016d1fa43c1ddf9fb9788a10595b7f`,name:`FlxCommerceReportMethods`,kind:`query`,plugin:`flx-commerce`,document:`query FlxCommerceReportMethods($first: Int = 50, $preset: FlxCommerceReportPreset, $from: String, $to: String) {
  flxCommerceReportMethods(first: $first, preset: $preset, from: $from, to: $to) {
    nodes {
      title
      orders
      total {
        ...FlxCommerceMoney
      }
    }
  }
}

fragment FlxCommerceMoney on FlxMoney {
  amount
  currency
  formatted
}`},ar={id:`58f73e0318e83d348499bdd32e8943970619767d6bf3a721f6c4ecb5f6d07e03`,name:`FlxCommerceReportProducts`,kind:`query`,plugin:`flx-commerce`,document:`query FlxCommerceReportProducts($first: Int = 50, $after: String, $preset: FlxCommerceReportPreset, $from: String, $to: String) {
  flxCommerceReportProducts(
    first: $first
    after: $after
    preset: $preset
    from: $from
    to: $to
  ) {
    nodes {
      productId
      name
      quantity
      total {
        ...FlxCommerceMoney
      }
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}

fragment FlxCommerceMoney on FlxMoney {
  amount
  currency
  formatted
}`},or={id:`949172de37b35632b379869d1dce3e07b563108eb5c9f41a6df6229b98ccad27`,name:`FlxCommerceSettings`,kind:`query`,plugin:`flx-commerce`,document:`query FlxCommerceSettings {
  flxCommerceSettings {
    paidStatuses
    excludeDonations
    lowStock
    hideCompany
    hideAddress2
    virtualNoAddress
    orderNotes
    stickyPay
    freeShipBar
    freeShipAmount
    receiptMaxMb
    receiptNotify
    deferToDonate
    cancelAfterDays
    feed
    feedBrand
    feedUrl
    notifyCustomerWa
    notifyAdminPhone
    notifyAdminOrder
    subRenewDays
    subGraceDays
    hiddenRedirect
    cardProviders
    whatsappReady
    donateCart
    donateHavale
    orderStatuses {
      slug
      label
    }
    freeShipThreshold {
      ...FlxCommerceMoney
    }
  }
  flxCommerceBankSettings {
    enabled
    title
    description
    holder
    iban
    bank
    refFormat
    refPrefix
  }
  flxCommerceRenewalStatus {
    ran
    at {
      ...FlxCommerceWhen
    }
  }
}

fragment FlxCommerceMoney on FlxMoney {
  amount
  currency
  formatted
}

fragment FlxCommerceWhen on FlxDateTime {
  iso
  timestamp
  label
  relative
}`},sr={id:`a31d85f24dc49cba6c9609c7e7ff0124e4a430d3291496e06802e10e2c3d2631`,name:`FlxCommerceSettingsSave`,kind:`mutation`,plugin:`flx-commerce`,document:`mutation FlxCommerceSettingsSave($input: FlxCommerceSettingsSaveInput!) {
  flxCommerceSettingsSave(input: $input) {
    settings {
      feed
      feedUrl
      lowStock
    }
    errors {
      code
      message
      field
    }
  }
}`},cr={id:`de7c0f24ec85c8c4038a0d976626583fba2a2aadee0b7906d72e6c1bf0232c8f`,name:`FlxCommerceSubscriptionCancel`,kind:`mutation`,plugin:`flx-commerce`,document:`mutation FlxCommerceSubscriptionCancel($input: FlxCommerceSubscriptionCancelInput!) {
  flxCommerceSubscriptionCancel(input: $input) {
    subscription {
      id
      status
    }
    errors {
      code
      message
      field
    }
  }
}`},lr={id:`d6b5f7be12da26092350d600ab77058dc1a2b190334ec09f4438d0746e2ea23d`,name:`FlxCommerceSubscriptionEnd`,kind:`mutation`,plugin:`flx-commerce`,document:`mutation FlxCommerceSubscriptionEnd($input: FlxCommerceSubscriptionEndInput!) {
  flxCommerceSubscriptionEnd(input: $input) {
    subscription {
      id
      status
    }
    errors {
      code
      message
      field
    }
  }
}`},ur={id:`dbf7dec60951c28374fe0b70093ad37f1bb8ef7eab3bcafc4dcbc73ad6a8cc01`,name:`FlxCommerceSubscriptionRenew`,kind:`mutation`,plugin:`flx-commerce`,document:`mutation FlxCommerceSubscriptionRenew($input: FlxCommerceSubscriptionRenewInput!) {
  flxCommerceSubscriptionRenew(input: $input) {
    subscription {
      ...FlxCommerceSubscriptionRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxCommerceMoney on FlxMoney {
  amount
  currency
  formatted
}

fragment FlxCommerceSubscriptionRow on FlxCommerceSubscription {
  id
  status
  hasAccess
  autoRenew
  autoCharge
  userId
  email
  name
  productId
  productName
  productUrl
  period
  paymentMethod
  parentOrderId
  lastOrderId
  renewalOrderId
  renewals
  renewNow
  payUrl
  lastOrderUrl
  cardLabel
  endReason
  amount {
    ...FlxCommerceMoney
  }
  startedAt {
    ...FlxCommerceWhen
  }
  periodEnd {
    ...FlxCommerceWhen
  }
  cancelledAt {
    ...FlxCommerceWhen
  }
  endedAt {
    ...FlxCommerceWhen
  }
  nextChargeAt {
    ...FlxCommerceWhen
  }
  createdAt {
    ...FlxCommerceWhen
  }
}

fragment FlxCommerceWhen on FlxDateTime {
  iso
  timestamp
  label
  relative
}`},dr={id:`3e20e29374383cc76bfbde64652dc6cf03ee65a286126055514ab5d4b1b499cf`,name:`FlxCommerceSubscriptionResume`,kind:`mutation`,plugin:`flx-commerce`,document:`mutation FlxCommerceSubscriptionResume($input: FlxCommerceSubscriptionResumeInput!) {
  flxCommerceSubscriptionResume(input: $input) {
    subscription {
      id
      status
    }
    errors {
      code
      message
      field
    }
  }
}`},fr={id:`e29fbbf141479441c8c6f29f4df17a1ee9794aa524687c80fc1a50b48733069d`,name:`FlxCommerceSubscriptions`,kind:`query`,plugin:`flx-commerce`,document:`query FlxCommerceSubscriptions($first: Int = 25, $after: String, $search: String, $status: FlxCommerceSubscriptionStatus) {
  flxCommerceSubscriptions(
    first: $first
    after: $after
    search: $search
    status: $status
  ) {
    nodes {
      ...FlxCommerceSubscriptionRow
    }
    pageInfo {
      hasNextPage
      endCursor
    }
    totalCount
  }
  flxCommerceSubscriptionCounts {
    all
    pending
    active
    pastDue
    cancelled
    expired
    due
    mrr {
      ...FlxCommerceMoney
    }
  }
}

fragment FlxCommerceMoney on FlxMoney {
  amount
  currency
  formatted
}

fragment FlxCommerceSubscriptionRow on FlxCommerceSubscription {
  id
  status
  hasAccess
  autoRenew
  autoCharge
  userId
  email
  name
  productId
  productName
  productUrl
  period
  paymentMethod
  parentOrderId
  lastOrderId
  renewalOrderId
  renewals
  renewNow
  payUrl
  lastOrderUrl
  cardLabel
  endReason
  amount {
    ...FlxCommerceMoney
  }
  startedAt {
    ...FlxCommerceWhen
  }
  periodEnd {
    ...FlxCommerceWhen
  }
  cancelledAt {
    ...FlxCommerceWhen
  }
  endedAt {
    ...FlxCommerceWhen
  }
  nextChargeAt {
    ...FlxCommerceWhen
  }
  createdAt {
    ...FlxCommerceWhen
  }
}

fragment FlxCommerceWhen on FlxDateTime {
  iso
  timestamp
  label
  relative
}`},pr={id:`3cc9d0c24418a2e7990ff691e01bb8f7b8ee607b117c91a5d8255af608a7b1ae`,name:`FlxCommerceSummary`,kind:`query`,plugin:`flx-commerce`,document:`query FlxCommerceSummary($days: Int) {
  flxCommerceSummary(days: $days) {
    days
    from
    to
    awaiting
    customers
    newCustomers
    orders
    items
    revenue {
      ...FlxCommerceMoney
    }
    aov {
      ...FlxCommerceMoney
    }
    revenueDelta
    ordersDelta
    aovDelta
    itemsDelta
    series {
      date
      orders
      revenue {
        ...FlxCommerceMoney
      }
    }
    lowStock {
      id
      name
      stock
      url
    }
  }
  flxCommerceReceipts(first: 20) {
    nodes {
      id
      number
      name
      email
      reference
      status
      statusLabel
      files {
        name
        url
      }
      total {
        ...FlxCommerceMoney
      }
      createdAt {
        ...FlxCommerceWhen
      }
    }
    pageInfo {
      hasNextPage
      endCursor
    }
    totalCount
  }
}

fragment FlxCommerceMoney on FlxMoney {
  amount
  currency
  formatted
}

fragment FlxCommerceWhen on FlxDateTime {
  iso
  timestamp
  label
  relative
}`},mr={id:`1e91260e91cb14354fcf6343416ffbcd5f6750eedd314080bfe027b08f2acf35`,name:`FlxCrmContact`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmContact($id: ID!) {
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
  sections {
    ...FlxCrmPersonSection
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

fragment FlxCrmPersonSection on FlxPersonSection {
  id
  title
  placement
  package
  blocks {
    kind
    text
    columns
    items {
      label
      value
      url
      meta
      primary
    }
    rows {
      cells
      url
      label
      meta
    }
  }
}

fragment FlxCrmTag on FlxTag {
  databaseId
  name
  color
  count
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  timestamp
  label
  relative
}`},hr={id:`d64b39e87439228efb39cf454ec4b8156cf3e0fd2704751e0c30f58a4d4e8698`,name:`FlxCrmContactBulk`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactBulk($input: FlxContactBulkInput!) {
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
}`},gr={id:`7a45e11108a56fdc5bfd5a7d1cc12e4cd8864f44b77e658e6af222ac743fe1ad`,name:`FlxCrmContactCreate`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactCreate($input: FlxContactCreateInput!) {
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
  sections {
    ...FlxCrmPersonSection
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

fragment FlxCrmPersonSection on FlxPersonSection {
  id
  title
  placement
  package
  blocks {
    kind
    text
    columns
    items {
      label
      value
      url
      meta
      primary
    }
    rows {
      cells
      url
      label
      meta
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
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  timestamp
  label
  relative
}`},_r={id:`e5ac09b6da29cd8a29870ad1eeecceb4e0930a083afacb5cb0f0823727443497`,name:`FlxCrmContactDelete`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactDelete($input: FlxContactDeleteInput!) {
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
}`},vr={id:`5b37df020f5fcf7eb644d59e7d43255a10daf12b3e1e81becfe80e6be2b63666`,name:`FlxCrmContactMerge`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactMerge($input: FlxContactMergeInput!) {
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
}`},yr={id:`116e9fc4755e4e5cab8e4bd48f39182c726074a0222753b61423a246cb0e0d88`,name:`FlxCrmContacts`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmContacts($filter: FlxContactFilterInput, $orderBy: FlxContactOrderBy, $order: FlxContactOrder, $first: Int = 25, $after: String) {
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
  timestamp
  label
  relative
}`},br={id:`77a9de7228db47e08446983a9cafae90890c4f22cc72a8c53491476ecaaa1991`,name:`FlxCrmContactUpdate`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactUpdate($input: FlxContactUpdateInput!) {
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
  sections {
    ...FlxCrmPersonSection
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

fragment FlxCrmPersonSection on FlxPersonSection {
  id
  title
  placement
  package
  blocks {
    kind
    text
    columns
    items {
      label
      value
      url
      meta
      primary
    }
    rows {
      cells
      url
      label
      meta
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
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  timestamp
  label
  relative
}`},xr={id:`38a29d9d9b83fb642e3e94ebb60f40e6b9ba2f7ce732abec9374495dd9bdba7e`,name:`FlxCrmDuplicates`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmDuplicates {
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
  timestamp
  label
  relative
}`},Sr={id:`842f7ab05afbd341a9e00a9b405054a5b72ba01ff65668f3754105afe99df2e1`,name:`FlxCrmEventAdd`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmEventAdd($input: FlxContactEventAddInput!) {
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
  timestamp
  label
  relative
}`},Cr={id:`a76a713193a0c8d25b06c56932ac7a945251d1c2966e1e35351c2b154124d10d`,name:`FlxCrmEventDelete`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmEventDelete($input: FlxContactEventDeleteInput!) {
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
}`},wr={id:`de578536b073d90339ee7d16b5217e5660676b0afc14941909d6006dacd6347c`,name:`FlxCrmGathering`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmGathering($id: Int!) {
  flxGathering(id: $id) {
    ...FlxCrmGatheringDetail
  }
}

fragment FlxCrmGatheringCard on FlxGathering {
  databaseId
  name
  status
  whenLabel
  place
  phaseTone
  phaseLabel
  peopleCount
  coverUrl
  dateDay
  dateMonth
  dateYear
  stats {
    label
    value
    hint
    progress
  }
  progress
  progressHint
  actions {
    label
    url
    target
    primary
  }
  mailReady
  postId
  pageState
  pageUrl
  pageTitle
  canCreatePage
  startsAt {
    ...FlxCrmWhen
  }
}

fragment FlxCrmGatheringDetail on FlxGathering {
  ...FlxCrmGatheringCard
  summary
  note
  address
  mapUrl
  deliveredCount
  sendCount
  reasons {
    label
    count
    percent
  }
  recentSends {
    ...FlxCrmGatheringSend
  }
}

fragment FlxCrmGatheringSend on FlxGatheringSend {
  eventId
  key
  overall
  overallLabel
  tone
  kind
  text
  at {
    ...FlxCrmWhen
  }
  sendAt {
    ...FlxCrmWhen
  }
  byName
  mediaId
  mediaUrl
  mediaIsImage
  thumbUrl
  channels {
    key
    label
    missing
    sent
    total
    failed
    skipped
    percent
    eta {
      ...FlxCrmWhen
    }
    campaignId
  }
  eta {
    ...FlxCrmWhen
  }
  delivered
  read
  replied
  optout
  failed
  reachedTotal
  canPause
  pending
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  timestamp
  label
  relative
}`},Tr={id:`268db0304bf6db81f3728f07c8605b0a705c957eaa78611a86154627b1d498eb`,name:`FlxCrmGatheringDelete`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmGatheringDelete($input: FlxGatheringDeleteInput!) {
  flxGatheringDelete(input: $input) {
    deleted
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},Er={id:`f073d75944d71105ac142580bff23fa4d12907780a00acc5da8ca823edbe7a57`,name:`FlxCrmGatheringForm`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmGatheringForm($id: Int) {
  flxGatheringForm(id: $id) {
    databaseId
    name
    createPost
    postType
    afterHtml
    sections {
      key
      title
      html
      fields {
        key
        type
        label
        placeholder
        help
        required
        value
        options {
          value
          label
        }
      }
    }
  }
}`},Dr={id:`d4c26d44e2fc853d533a38268b88d860a7c165d8fabfb856c4ca7a17743014e3`,name:`FlxCrmGatheringMessages`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmGatheringMessages($id: Int!) {
  flxGatheringMessages(id: $id) {
    databaseId
    mailReady
    canPause
    hasMedia
    timezone
    defaultSendAt {
      ...FlxCrmWhen
    }
    channels {
      key
      label
      ready
      infoCount
      marketingCount
    }
    variables {
      group
      key
      label
      off
    }
    samples {
      name
      values {
        key
        value
      }
    }
    sends {
      ...FlxCrmGatheringSend
    }
  }
}

fragment FlxCrmGatheringSend on FlxGatheringSend {
  eventId
  key
  overall
  overallLabel
  tone
  kind
  text
  at {
    ...FlxCrmWhen
  }
  sendAt {
    ...FlxCrmWhen
  }
  byName
  mediaId
  mediaUrl
  mediaIsImage
  thumbUrl
  channels {
    key
    label
    missing
    sent
    total
    failed
    skipped
    percent
    eta {
      ...FlxCrmWhen
    }
    campaignId
  }
  eta {
    ...FlxCrmWhen
  }
  delivered
  read
  replied
  optout
  failed
  reachedTotal
  canPause
  pending
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  timestamp
  label
  relative
}`},Or={id:`532cd110867e395ef8d6e1716c5d2dd298edf13b19a1b9127de512bca7050ab8`,name:`FlxCrmGatheringPage`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmGatheringPage($input: FlxGatheringEnsurePageInput!) {
  flxGatheringEnsurePage(input: $input) {
    postId
    gathering {
      ...FlxCrmGatheringCard
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmGatheringCard on FlxGathering {
  databaseId
  name
  status
  whenLabel
  place
  phaseTone
  phaseLabel
  peopleCount
  coverUrl
  dateDay
  dateMonth
  dateYear
  stats {
    label
    value
    hint
    progress
  }
  progress
  progressHint
  actions {
    label
    url
    target
    primary
  }
  mailReady
  postId
  pageState
  pageUrl
  pageTitle
  canCreatePage
  startsAt {
    ...FlxCrmWhen
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  timestamp
  label
  relative
}`},kr={id:`2bd35d86a634e017ffadb50f1bbce3bca23d9c56b0e6f6b98db8110c82897753`,name:`FlxCrmGatheringPeople`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmGatheringPeople($id: Int!, $search: String, $first: Int = 40, $after: String) {
  flxGatheringPeople(id: $id, search: $search, first: $first, after: $after) {
    totalCount
    nodes {
      databaseId
      name
      email
      phone
      why
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}`},Ar={id:`3fcfbb5a384fa5417f803dc29ea184ed4e429bc15ac8c86e952a65915cb112af`,name:`FlxCrmGatherings`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmGatherings($status: FlxGatheringStatus, $first: Int = 24, $after: String) {
  flxGatherings(status: $status) {
    openCount
    closedCount
    hasSite
    siteHtml
    mailReady
    events(status: $status, first: $first, after: $after) {
      totalCount
      nodes {
        ...FlxCrmGatheringCard
      }
      pageInfo {
        hasNextPage
        endCursor
      }
    }
  }
}

fragment FlxCrmGatheringCard on FlxGathering {
  databaseId
  name
  status
  whenLabel
  place
  phaseTone
  phaseLabel
  peopleCount
  coverUrl
  dateDay
  dateMonth
  dateYear
  stats {
    label
    value
    hint
    progress
  }
  progress
  progressHint
  actions {
    label
    url
    target
    primary
  }
  mailReady
  postId
  pageState
  pageUrl
  pageTitle
  canCreatePage
  startsAt {
    ...FlxCrmWhen
  }
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  timestamp
  label
  relative
}`},jr={id:`63695a284190988eeec38cef3e20784d3cf4c0b8c3bd581cc217fa792a80e8ab`,name:`FlxCrmGatheringSave`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmGatheringSave($input: FlxGatheringSaveInput!) {
  flxGatheringSave(input: $input) {
    gathering {
      ...FlxCrmGatheringDetail
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmGatheringCard on FlxGathering {
  databaseId
  name
  status
  whenLabel
  place
  phaseTone
  phaseLabel
  peopleCount
  coverUrl
  dateDay
  dateMonth
  dateYear
  stats {
    label
    value
    hint
    progress
  }
  progress
  progressHint
  actions {
    label
    url
    target
    primary
  }
  mailReady
  postId
  pageState
  pageUrl
  pageTitle
  canCreatePage
  startsAt {
    ...FlxCrmWhen
  }
}

fragment FlxCrmGatheringDetail on FlxGathering {
  ...FlxCrmGatheringCard
  summary
  note
  address
  mapUrl
  deliveredCount
  sendCount
  reasons {
    label
    count
    percent
  }
  recentSends {
    ...FlxCrmGatheringSend
  }
}

fragment FlxCrmGatheringSend on FlxGatheringSend {
  eventId
  key
  overall
  overallLabel
  tone
  kind
  text
  at {
    ...FlxCrmWhen
  }
  sendAt {
    ...FlxCrmWhen
  }
  byName
  mediaId
  mediaUrl
  mediaIsImage
  thumbUrl
  channels {
    key
    label
    missing
    sent
    total
    failed
    skipped
    percent
    eta {
      ...FlxCrmWhen
    }
    campaignId
  }
  eta {
    ...FlxCrmWhen
  }
  delivered
  read
  replied
  optout
  failed
  reachedTotal
  canPause
  pending
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  timestamp
  label
  relative
}`},Mr={id:`6ba18cf9b6ab920842421aaf10ca3bfeaed4c7cf9539909af554472cd3600b37`,name:`FlxCrmGatheringSend`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmGatheringSend($input: FlxGatheringMessageSendInput!) {
  flxGatheringMessageSend(input: $input) {
    send {
      ...FlxCrmGatheringSend
    }
    skipped {
      channel
      reason
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmGatheringSend on FlxGatheringSend {
  eventId
  key
  overall
  overallLabel
  tone
  kind
  text
  at {
    ...FlxCrmWhen
  }
  sendAt {
    ...FlxCrmWhen
  }
  byName
  mediaId
  mediaUrl
  mediaIsImage
  thumbUrl
  channels {
    key
    label
    missing
    sent
    total
    failed
    skipped
    percent
    eta {
      ...FlxCrmWhen
    }
    campaignId
  }
  eta {
    ...FlxCrmWhen
  }
  delivered
  read
  replied
  optout
  failed
  reachedTotal
  canPause
  pending
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  timestamp
  label
  relative
}`},Nr={id:`1228311aa883dbcc03990ef5e10dafea2e28af8a5851cdcf285cde962f3e7b45`,name:`FlxCrmGatheringSendControl`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmGatheringSendControl($input: FlxGatheringSendControlInput!) {
  flxGatheringSendControl(input: $input) {
    send {
      ...FlxCrmGatheringSend
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmGatheringSend on FlxGatheringSend {
  eventId
  key
  overall
  overallLabel
  tone
  kind
  text
  at {
    ...FlxCrmWhen
  }
  sendAt {
    ...FlxCrmWhen
  }
  byName
  mediaId
  mediaUrl
  mediaIsImage
  thumbUrl
  channels {
    key
    label
    missing
    sent
    total
    failed
    skipped
    percent
    eta {
      ...FlxCrmWhen
    }
    campaignId
  }
  eta {
    ...FlxCrmWhen
  }
  delivered
  read
  replied
  optout
  failed
  reachedTotal
  canPause
  pending
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  timestamp
  label
  relative
}`},Pr={id:`2e3559815b07e3f899b890324799b6c8f9a52a0e1a516127189274f559f1a7e3`,name:`FlxCrmGatheringSendView`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmGatheringSendView($id: Int!, $key: String!, $channel: String, $status: String, $first: Int = 40, $after: String) {
  flxGatheringSend(id: $id, key: $key) {
    ...FlxCrmGatheringSend
    recipients(channel: $channel, status: $status, first: $first, after: $after) {
      totalCount
      channel
      channels
      countSent
      countWaiting
      countFailed
      countOther
      nodes {
        name
        recipient
        status
        error
        sentAt {
          ...FlxCrmWhen
        }
        scheduledAt {
          ...FlxCrmWhen
        }
        delivered
        read
        replied
        optout
        hasEngagement
      }
      pageInfo {
        hasNextPage
        endCursor
      }
    }
  }
}

fragment FlxCrmGatheringSend on FlxGatheringSend {
  eventId
  key
  overall
  overallLabel
  tone
  kind
  text
  at {
    ...FlxCrmWhen
  }
  sendAt {
    ...FlxCrmWhen
  }
  byName
  mediaId
  mediaUrl
  mediaIsImage
  thumbUrl
  channels {
    key
    label
    missing
    sent
    total
    failed
    skipped
    percent
    eta {
      ...FlxCrmWhen
    }
    campaignId
  }
  eta {
    ...FlxCrmWhen
  }
  delivered
  read
  replied
  optout
  failed
  reachedTotal
  canPause
  pending
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  timestamp
  label
  relative
}`},Fr={id:`720a586cdcb486eec80d9baaf15de9bf121f86dad377773723b014d524effb35`,name:`FlxCrmGatheringStatus`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmGatheringStatus($input: FlxGatheringSetStatusInput!) {
  flxGatheringSetStatus(input: $input) {
    gathering {
      ...FlxCrmGatheringCard
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmGatheringCard on FlxGathering {
  databaseId
  name
  status
  whenLabel
  place
  phaseTone
  phaseLabel
  peopleCount
  coverUrl
  dateDay
  dateMonth
  dateYear
  stats {
    label
    value
    hint
    progress
  }
  progress
  progressHint
  actions {
    label
    url
    target
    primary
  }
  mailReady
  postId
  pageState
  pageUrl
  pageTitle
  canCreatePage
  startsAt {
    ...FlxCrmWhen
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  timestamp
  label
  relative
}`},Ir={id:`e4410099ca450172284928b787d6653e3a20bb9fea759f3405dcc65b4b2d60fc`,name:`FlxCrmJourneyDelete`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmJourneyDelete($input: FlxJourneyDeleteInput!) {
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
}`},Lr={id:`81f33cb7df2b01fe473af742d3664bc98d855fff2d8d6c6bd10bf813b3126a00`,name:`FlxCrmJourneyFromTemplate`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmJourneyFromTemplate($input: FlxJourneyFromTemplateInput!) {
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
}`},Rr={id:`1d65e68f34e737d57ba69b8f75f693f31df5df46147478680767d4a886eba9a4`,name:`FlxCrmJourneys`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmJourneys {
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
}`},zr={id:`bb7940aae0aa4487e6596f7310dfe9d6c66cd05f801d9d2571a2fbd024003e89`,name:`FlxCrmJourneySave`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmJourneySave($input: FlxJourneySaveInput!) {
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
}`},Br={id:`271eb15db3d24fbf7acc5848820ed761c6fdcece95a02526b41ff2a7316997a2`,name:`FlxCrmJourneySetStatus`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmJourneySetStatus($input: FlxJourneySetStatusInput!) {
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
}`},Vr={id:`a78b2957f6830083986d4757b1e4ade31b49794c3cd9684562de0d2c70bdea9f`,name:`FlxCrmOverview`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmOverview {
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
  timestamp
  label
  relative
}`},Hr={id:`e0edd7dec5bacd4fa0efa1061b492dfd4514405725805c3d2dad8e334f1fa625`,name:`FlxCrmScoreRun`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmScoreRun($input: FlxCrmScoreRunInput!) {
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
}`},Ur={id:`2f13b0c4d23cfcf18bb040c35a545c3899b8be9c777bef71b9a3a1889584e38c`,name:`FlxCrmSegmentDelete`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmSegmentDelete($input: FlxSegmentDeleteInput!) {
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
}`},Wr={id:`acc491cf70a4b00af7fce340efd2fd26dc087fe84690b01a364966abf7dea880`,name:`FlxCrmSegmentEditor`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmSegmentEditor {
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
}`},Gr={id:`564b5e761395c08e8045dbc65f96f00b7f4fa14bc2dcfa48e28a0beff5edd00e`,name:`FlxCrmSegmentPreview`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmSegmentPreview($match: FlxSegmentMatch, $rules: [FlxSegmentRuleInput!]!) {
  flxSegmentPreview(match: $match, rules: $rules) {
    count
    summary
    sample {
      databaseId
      name
    }
  }
}`},Kr={id:`6035e1dbb5301cebeb87fa3481cf44003f54cdd356668d7d22ff87b96ec87866`,name:`FlxCrmSegmentSave`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmSegmentSave($input: FlxSegmentSaveInput!) {
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
}`},qr={id:`0fa3aa644071a7076ffd2584ca0e884e9861ce4fd613a0d755aa80fb5ce0fe78`,name:`FlxCrmSettings`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmSettings {
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
  timestamp
  label
  relative
}`},Jr={id:`475b0e826f22f6a5b379ba782b1a14a133838345508ef33f6050249ee46ab6d6`,name:`FlxCrmSettingsSave`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmSettingsSave($input: FlxCrmSettingsSaveInput!) {
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
  timestamp
  label
  relative
}`},Yr={id:`3a59d8e4195bf7ba635933d4e7baafd37f013da4a16f4d4cfc49b7501f3a7de4`,name:`FlxCrmShell`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmShell {
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
}`},Xr={id:`91833bc806371dcec389dec7ee846bdc3806edd84b27da2b516ee064b794c6a5`,name:`FlxCrmSyncStep`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmSyncStep($input: FlxCrmSyncStepInput!) {
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
}`},Zr={id:`803c73840e66e14fc6312eb733e95eb00bb1261c0fcdcaa93508c07d05751c35`,name:`FlxCrmTagAdd`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmTagAdd($input: FlxContactTagAddInput!) {
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
}`},Qr={id:`68836030d68ed1f45df0595812ec3fbbf361855944537c066a08b8ea641057fa`,name:`FlxCrmTagRemove`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmTagRemove($input: FlxContactTagRemoveInput!) {
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
}`},$r={id:`f439ebbf646df143cd836fa409a680df2e76d00604a89b92cc3b28e24208006b`,name:`FlxCrmTimeline`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmTimeline($id: ID!, $filter: String, $first: Int = 20, $after: String) {
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
  timestamp
  label
  relative
}`},ei={id:`7ac24fd3ec0c949c50bbbe7f9d125fe120582bb3b5e7672b11d7a34bd7ce8c5d`,name:`FlxDonateAdd`,kind:`query`,plugin:`flx-donate`,document:`query FlxDonateAdd($donorId: Int!) {
  flxDonationAddForm(donorId: $donorId) {
    request
    types {
      databaseId
      title
      currency
      free
      proxy
      cert
      options {
        key
        label
        price
        per
        unitOne
        unitMany
      }
    }
    methods {
      value
      label
    }
    accounts {
      value
      label
    }
    referrals {
      value
      label
    }
    currencies {
      value
      label
    }
    currency
    proxyVersion
    now
    donor {
      name
      phone
      email
      consentKvkk
      consentEmail
      consentSms
    }
    listUrl
    listLabel
  }
}`},ti={id:`56c9a83c490473c6f82c669f2d8f37a66c89ba2a647a52c7882e79d08f6e1483`,name:`FlxDonateDashboard`,kind:`query`,plugin:`flx-donate`,document:`query FlxDonateDashboard($range: FlxDonationRange, $from: String, $to: String) {
  flxDonationDashboard(range: $range, from: $from, to: $to) {
    from
    to
    fromLabel
    toLabel
    currency
    symbol
    hasAny
    allDonors
    now {
      total
      gifts
      donors
      newDonors
      average
    }
    prev {
      total
      gifts
      donors
      newDonors
      average
    }
    mrr
    activeSubscriptions
    pastDueSubscriptions
    series {
      day
      label
      oneTime
      recurring
      gifts
    }
    oneTime {
      amount
      gifts
    }
    recurring {
      amount
      gifts
    }
    byType {
      id
      label
      amount
      gifts
    }
    byForm {
      id
      label
      amount
      gifts
    }
    topDonors {
      id
      name
      contact
      amount
      gifts
      url
    }
    canList
    csvUrl
    listUrl
    donorsUrl
    importUrl
    ownOnly
  }
}`},ni={id:`131c957f59cd1b2001cf1b9cbc78347bf21fbd49ff15bbbe703357e68021474e`,name:`FlxDonateDetail`,kind:`query`,plugin:`flx-donate`,document:`query FlxDonateDetail($id: Int!) {
  flxDonationDetail(id: $id) {
    databaseId
    title
    date
    statusLabel
    tone
    kind
    kindLabel
    multi
    trashed
    receiptUrl
    listUrl
    hero {
      ...FlxDonationFactFields
    }
    approval {
      title
      hint
      canAct
      notifyTo
      receipts {
        url
        kind
        name
        meta
        warn
      }
    }
    lines {
      hint
      rows {
        image
        name
        url
        current
        statusLabel
        tone
        unit
        qty
        total
        unitLabel
        qtyLabel
        totalLabel
        bits {
          ...FlxDonationFactFields
        }
        share {
          code
          url
          statusLabel
          tone
          place
        }
      }
      totals {
        lines {
          label
          value
          look
        }
        paid {
          label
          value
          look
        }
        via
      }
      names {
        itemId
        label
        value
      }
    }
    payment {
      title
      kindLabel
      recurring
      statusLabel
      tone
      note
      canCertificates
      facts {
        ...FlxDonationFactFields
      }
      certificates {
        label
        url
        revoked
      }
      menu {
        orderLabel
        orderUrl
        refund {
          label
          help
          field
          max
          auto
        }
      }
    }
    contacts {
      name
      phone
      phoneUrl
      email
    }
    receipts {
      name
      url
      meta
    }
    history {
      date
      text
      user
    }
    donor {
      name
      initial
      contact
      paid
      total
      waiting
      cardUrl
      chatUrl
    }
    referral {
      hint
      current
      canEdit
      person {
        name
        initial
        contact
      }
      choices {
        value
        label
      }
    }
    manage {
      multi
      statusConfirm
      trashed
      trashConfirm
      canDelete
      statuses {
        value
        label
      }
    }
    notes {
      items {
        id
        html
        meta
        customer
        system
      }
    }
    islands {
      id
      title
      col
    }
    layout {
      main
      side
      closed
    }
  }
}

fragment FlxDonationFactFields on FlxDonationFact {
  label
  text
  url
  look
  tone
  hint
}`},ri={id:`8377d74d64d046e1786757ec282aea2641e4f2091f9dedd32d272105313d30f5`,name:`FlxDonateDonations`,kind:`query`,plugin:`flx-donate`,document:`query FlxDonateDonations($first: Int, $after: String, $filter: FlxDonationListFilter) {
  flxDonationList(first: $first, after: $after, filter: $filter) {
    totalCount
    pageInfo {
      hasNextPage
      hasPreviousPage
    }
    nodes {
      databaseId
      donorId
      donorName
      contact
      amountLabel
      kind
      statusLabel
      tone
      date {
        label
      }
      where
      lines
      referral
      referralFromDonor
      code
      url
    }
  }
  flxDonationListInfo(filter: $filter) {
    sumLabel
    trashCount
    statuses {
      value
      label
    }
    donationTypes {
      value
      label
    }
    forms {
      value
      label
    }
    referrals {
      value
      label
    }
    awaiting {
      databaseId
      donorName
      what
      date {
        relative
      }
      amountLabel
      url
    }
    ownOnly
    canManage
    canDeletePermanently
    canAdd
    csvUrl
    addUrl
  }
}`},ii={id:`b1c69af704b64d32eb4c0e0e8ae768402018b53803321e14b97bd745d35384c7`,name:`FlxDonateDonor`,kind:`query`,plugin:`flx-donate`,document:`query FlxDonateDonor($id: Int!) {
  flxDonor(id: $id) {
    databaseId
    name
    initial
    email
    phone
    phoneLabel
    sinceLabel
    totalLabel
    giftCount
    averageLabel
    lastGiftLabel
    ownOnly
    gifts {
      databaseId
      donorId
      donorName
      contact
      amountLabel
      kind
      statusLabel
      tone
      date {
        label
      }
      where
      lines
      referral
      referralFromDonor
      code
      url
    }
    giftsTotal
    giftsUrl
    subscriptions {
      databaseId
      donorId
      donorName
      donorUrl
      reference
      amountLabel
      intervalLabel
      status
      statusLabel
      tone
      note
      startedLabel
      nextLabel
      gifts
      giftsUrl
      canCancel
    }
    certificatesOn
    certificates {
      number
      dateLabel
      url
    }
    moreCertificates
    consentKvkk
    consentEmail
    consentSms
    note
    canEdit
    canAdd
    addUrl
    listUrl
  }
}`},ai={id:`ee3c8bc574eba0ef36de8c9c27d56b72eaa7d098b83fb7ee5ffb1cd7f56a80f8`,name:`FlxDonateDonors`,kind:`query`,plugin:`flx-donate`,document:`query FlxDonateDonors($first: Int, $after: String, $filter: FlxDonorListFilter) {
  flxDonorList(first: $first, after: $after, filter: $filter) {
    totalCount
    pageInfo {
      hasNextPage
      hasPreviousPage
    }
    nodes {
      databaseId
      name
      email
      phone
      totalLabel
      gifts
      lastGift {
        label
      }
      recurring
      url
    }
  }
  flxDonorListInfo {
    ownOnly
  }
}`},oi={id:`a4a8fa2668b1da0ae6f0815f070e865a37ff8ec8265f9ed9adc476dad0a9a040`,name:`FlxDonateImport`,kind:`query`,plugin:`flx-donate`,document:`query FlxDonateImport($id: String!) {
  flxDonationImport(id: $id) {
    id
    state
    file
    uploadUrl
    uploadNonce
    earlier {
      id
      file
      state
      sub
    }
    preview {
      stats {
        label
        value
        accent
      }
      summary {
        label
        value
        accent
      }
      byRef {
        name
        rows
        units
        amount
      }
      confirm
      ready
      runLabel
      fields {
        value
        label
      }
      columns {
        col
        header
        field
        example
      }
      types {
        value
        label
      }
      options {
        type
        paid
        keepDate
        nodate
        nodateDay
      }
      matches {
        group
        title
        choices {
          value
          label
        }
        rows {
          key
          raw
          count
          to
        }
      }
      rows {
        n
        who
        sub
        date
        dated
        type
        quantity
        amount
        status
        checks {
          label
          tone
        }
      }
      more
    }
    run {
      done
      pos
      total
      percent
      progress
      kpis {
        label
        value
        accent
      }
      errors {
        n
        name
        msg
      }
      nextUrl
      nextLabel
    }
  }
}`},si={id:`5d0e9f733593444887f24e74cbacbd56707fadf952224697474955f624b43663`,name:`FlxDonatePartner`,kind:`query`,plugin:`flx-donate`,document:`query FlxDonatePartner($id: Int!) {
  flxDonationPartner(id: $id) {
    databaseId
    name
    pkey
    group
    isService
    isSource
    typeIds
    contactName
    phone
    email
    currency
    active
    note
    canManage
    partnerLabel
    currencies
    types {
      value
      label
    }
    typeOptions {
      value
      label
    }
    today
    payments {
      databaseId
      date
      direction
      amount
      note
    }
    agreements {
      databaseId
      type
      option
      price
      validFrom
      active
      note
    }
  }
}`},ci={id:`bde686fdd4aad86518699cc424abf6be526b2d50b44abaa3c1a62716a7ec1b94`,name:`FlxDonatePartners`,kind:`query`,plugin:`flx-donate`,document:`query FlxDonatePartners {
  flxDonationPartners {
    canManage
    partnerLabel
    items {
      databaseId
      name
      group
      roles
      types
      agreement
      contact
      active
    }
  }
}`},li={id:`4ce12efd775e6649f3fa8c154b1d6747ac3f5a26e4ff7f3f78c39ada9779ab5c`,name:`FlxDonatePartnerSettlement`,kind:`query`,plugin:`flx-donate`,document:`query FlxDonatePartnerSettlement($kind: String!, $period: String!, $partnerId: Int!) {
  flxDonationPartnerSettlement(
    kind: $kind
    period: $period
    partnerId: $partnerId
  ) {
    kind
    period
    from
    to
    partnerLabel
    months {
      key
      label
      count
      sum
    }
    partners {
      value
      label
    }
    rows {
      partnerId
      name
      uncosted
      count
      sum
      moved
      open
    }
    detail {
      name
      canReprice
      items {
        date
        code
        trackUrl
        beneficiary
        option
        status
        tone
        price
      }
    }
    csvUrl
  }
}`},ui={id:`f701b92192e60e93ab01fa2d95bef39554a16758634e54bd711f0c6605d47b63`,name:`FlxDonateReport`,kind:`query`,plugin:`flx-donate`,document:`query FlxDonateReport($id: String!, $period: String!, $from: String!, $to: String!) {
  flxDonationReport(id: $id, period: $period, from: $from, to: $to) {
    reports {
      value
      label
    }
    canManage
    periods {
      value
      label
    }
    id
    title
    description
    edited
    builtin
    period
    from
    to
    csvUrl
    kpis {
      label
      value
      sub
    }
    columns {
      label
      num
    }
    rows {
      cells
      bar
    }
    note
  }
}`},di={id:`65277b2c6418c1f4bff19e355dc22191b989e42e76844a30e9eebe61ce283446`,name:`FlxDonateReportSource`,kind:`query`,plugin:`flx-donate`,document:`query FlxDonateReportSource($id: String!) {
  flxDonationReportSource(id: $id) {
    id
    title
    json
    edited
    builtin
    help {
      source
      label
      group
      metrics
    }
    periods
  }
}`},fi={id:`78af989d83826dce4e2b8615a8ea2cf02b314c6f4233fc9a2b74079f935e5c52`,name:`FlxDonateSettings`,kind:`query`,plugin:`flx-donate`,document:`query FlxDonateSettings {
  flxDonationSettings {
    tabs {
      id
      label
      island
      url
    }
    currency
    currencies {
      value
      label
    }
    donationForms
    formIds
    forms {
      value
      label
    }
    moduleTrack
    moduleReports
    moduleKurban
    orgName
    orgPlaceholder
    logoId
    logoUrl
    primaryColor
    colorHolder
    receiptText
    footerText
    brandName
    brandLogo
    brandPrimary
    brandDark
    categories {
      key
      label
      used
    }
    earlyDays
    labels {
      key
      label
      value
      placeholder
      max
    }
    kurban
    proxyText
    proxyVersions
    tabsJson
    tabsInUse {
      id
      title
      donation
      fields
      builtin
    }
    fieldTypes
    tabsExample
    importReady
    importLast
  }
}`},pi={id:`b15628175af3560beb6d8f423b969f7f1db0205a927b5bd96f36ba263fb8ee1b`,name:`FlxDonateSubscriptions`,kind:`query`,plugin:`flx-donate`,document:`query FlxDonateSubscriptions($first: Int, $after: String, $filter: FlxSubscriptionListFilter) {
  flxSubscriptionList(first: $first, after: $after, filter: $filter) {
    totalCount
    pageInfo {
      hasNextPage
      hasPreviousPage
    }
    nodes {
      ...FlxSubscriptionFields
    }
  }
  flxSubscriptionTotals {
    mrrLabel
    active
    pastDue
    canceled
  }
}

fragment FlxSubscriptionFields on FlxSubscriptionItem {
  databaseId
  donorId
  donorName
  donorUrl
  reference
  amountLabel
  intervalLabel
  status
  statusLabel
  tone
  note
  startedLabel
  nextLabel
  gifts
  giftsUrl
  canCancel
}`},mi={id:`d132179375dfe80538379d16145a1d05f5b8b01d31b94dc0d17a6344afc467fe`,name:`FlxDonateTrack`,kind:`query`,plugin:`flx-donate`,document:`query FlxDonateTrack($filter: FlxDonationTrackFilter, $page: Int) {
  flxDonationTrack(filter: $filter, page: $page) {
    title
    partnerLabel
    filesLabel
    canEdit
    actions {
      label
      url
      primary
    }
    tabs {
      value
      label
      count
    }
    types {
      value
      label
    }
    places {
      value
      label
    }
    steps {
      value
      label
    }
    columns
    items {
      databaseId
      code
      option
      beneficiary
      phone
      donationId
      donationUrl
      donor
      pay {
        label
        tone
      }
      target {
        date
        state
        label
      }
      place
      status
      tone
      files
      cells
    }
    total
    totalLabel
    page
    perPage
  }
}`},hi={id:`59c78df241d5824083afd276bda52b5830037754e3617b6a9a62c760c757fd1c`,name:`FlxDonateTrackItem`,kind:`query`,plugin:`flx-donate`,document:`query FlxDonateTrackItem($id: Int!) {
  flxDonationTrackItem(id: $id) {
    databaseId
    code
    title
    subtitle
    listTitle
    partnerLabel
    filesLabel
    itemLabel
    assignLabel
    status
    statusKey
    tone
    canceled
    late
    canEdit
    canAssign
    flow {
      label
      state
      date
    }
    details {
      label
      html
    }
    beneficiary
    phone
    targetDate
    donationStatus
    donationTone
    payment {
      label
      html
    }
    unpaid
    places {
      value
      label
    }
    actions {
      step
      label
      tone
      confirm
    }
    files {
      att
      url
      kind
      name
      meta
      sent
    }
    filesHint
    canSend
    sendHint
    ownerPhone
    uploadUrl
    uploadNonce
    islands
    history {
      date
      event
      label
      more
      note
      user
    }
  }
}`},gi={id:`c5a6a363dfa0cb0fb9de8790a0b76c854ca287e8024c046b9bb18ecf1aa53ff5`,name:`FlxDonateTypeForm`,kind:`query`,plugin:`flx-donate`,document:`query FlxDonateTypeForm($id: Int!) {
  flxDonationTypeForm(id: $id) {
    databaseId
    title
    slug
    category
    currency
    description
    subtitle
    icon
    imageId
    imageUrl
    options {
      key
      label
      price
      unit
      unitsPer
      badge
      active
      prices {
        currency
        amount
      }
    }
    allowFree
    minAmount
    minByCurrency {
      currency
      amount
    }
    allowQuantity
    maxQuantity
    allowRecurring
    formIds
    lockPreselect
    trackOn
    trackItems
    fileCompletes
    trackLabels {
      key
      label
      value
      placeholder
    }
    kurbanOn
    requiresProxy
    intention
    grouping
    meatToNeedy
    status
    startDate
    endDate
    goal
    shortcode
    hasGifts
    categories {
      value
      label
    }
    categoriesUrl
    currencies {
      value
      label
    }
    extraCurrencies
    icons {
      value
      label
    }
    units {
      value
      label
    }
    badges {
      value
      label
    }
    intentions {
      value
      label
    }
    forms {
      databaseId
      title
      others
    }
    listUrl
    islands {
      id
      title
      col
    }
  }
}`},_i={id:`372ca3a9d0b6d2ba56c08d3298b1a8d3259655d22de0d8160a1f09b6bf03bb77`,name:`FlxDonateTypes`,kind:`query`,plugin:`flx-donate`,document:`query FlxDonateTypes($archived: Boolean) {
  flxDonationTypeAdminList(archived: $archived) {
    canManage
    newUrl
    samples {
      value
      label
    }
    items {
      databaseId
      title
      stateLabel
      tone
      category
      shortcode
      prices
      flags
      hasGoal
      percent
      progress
      donors
      dates
      forms
      status
      listUrl
      editUrl
    }
  }
}`},vi={id:`1610970380610d0f4bc525d3cfbbc753055b0656d76162ad75012fbbe4489e2c`,name:`FlxDonationAdd`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationAdd($input: FlxDonationAddInput!) {
  flxDonationAdd(input: $input) {
    message
    databaseId
    url
    errors {
      message
      field
    }
  }
}`},yi={id:`efd67d4a70cd835868ee1cc9ea82a0e716c09669c7c048404ba4a6dc160d596c`,name:`FlxDonationApprove`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationApprove($id: Int!) {
  flxDonationApprove(input: { id: $id }) {
    message
    errors {
      message
      field
    }
  }
}`},bi={id:`aa04db165b7b8ae704dab1c83717bd559270b01a2531b5206418afa05687ce40`,name:`FlxDonationCertificates`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationCertificates($id: Int!, $action: FlxDonationCertificateAction!) {
  flxDonationCertificates(input: { id: $id, action: $action }) {
    message
    errors {
      message
      field
    }
  }
}`},xi={id:`84d77ec43e495f69d886692e7ec884b1e2e1ea0ae5ecaf136b66480c4a901deb`,name:`FlxDonationImportDelete`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationImportDelete($input: FlxDonationImportDeleteInput!) {
  flxDonationImportDelete(input: $input) {
    message
    errors {
      message
      field
    }
  }
}`},Si={id:`b3dae12f61e71b0d78d82af93a61215d16296e8a06e30a47c32183920f6cc501`,name:`FlxDonationImportSave`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationImportSave($input: FlxDonationImportSaveInput!) {
  flxDonationImportSave(input: $input) {
    message
    state
    errors {
      message
      field
    }
  }
}`},Ci={id:`61e5f0c3eabb4a73f404303ded293105b0d87317a491d2476b091eb66aedec48`,name:`FlxDonationImportStep`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationImportStep($input: FlxDonationImportStepInput!) {
  flxDonationImportStep(input: $input) {
    message
    done
    errors {
      message
      field
    }
  }
}`},wi={id:`dfd2cb5f56000bf08d0cf3560677ab724abe2d2ef556a587cec214fb51c92b87`,name:`FlxDonationLayoutSave`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationLayoutSave($main: [String!]!, $side: [String!]!, $closed: [String!]!) {
  flxDonationLayoutSave(input: { main: $main, side: $side, closed: $closed }) {
    ok
    errors {
      message
      field
    }
  }
}`},Ti={id:`9580f321f30fa000f8b0b86422306eded9845a8ffa5e1b9c24898da08cb7d992`,name:`FlxDonationNoteAdd`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationNoteAdd($id: Int!, $text: String!, $customer: Boolean) {
  flxDonationNoteAdd(input: { id: $id, text: $text, customer: $customer }) {
    message
    errors {
      message
      field
    }
  }
}`},Ei={id:`6479af3b3a9e0990536f0a33af75eb37dff2edc6a39a3286d16972881cc44a5f`,name:`FlxDonationNoteDelete`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationNoteDelete($id: Int!, $noteId: Int!) {
  flxDonationNoteDelete(input: { id: $id, noteId: $noteId }) {
    message
    errors {
      message
      field
    }
  }
}`},Di={id:`3a9703c772601f5ac0c55fbe679802831897a1043453a1b03607fc6d676a96ff`,name:`FlxDonationPartnerPaymentAdd`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationPartnerPaymentAdd($input: FlxDonationPartnerPaymentAddInput!) {
  flxDonationPartnerPaymentAdd(input: $input) {
    message
    errors {
      message
      field
    }
  }
}`},Oi={id:`ff6bb1ef39db7b4161288747805c06b96d7880434db834b9df87c53faf9e5bd3`,name:`FlxDonationPartnerPaymentDelete`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationPartnerPaymentDelete($input: FlxDonationPartnerPaymentDeleteInput!) {
  flxDonationPartnerPaymentDelete(input: $input) {
    message
    errors {
      message
      field
    }
  }
}`},ki={id:`2fc27e5a2a7faeb374ecde53f4a95759dbe092a708564a5cc733ae9c24729631`,name:`FlxDonationPartnerPriceAdd`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationPartnerPriceAdd($input: FlxDonationPartnerPriceAddInput!) {
  flxDonationPartnerPriceAdd(input: $input) {
    message
    errors {
      message
      field
    }
  }
}`},Ai={id:`74d442b51511bacbd435e2fdf7d98961e10016f8891a931df3da3beaa8a36eca`,name:`FlxDonationPartnerPriceDelete`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationPartnerPriceDelete($input: FlxDonationPartnerPriceDeleteInput!) {
  flxDonationPartnerPriceDelete(input: $input) {
    message
    errors {
      message
      field
    }
  }
}`},ji={id:`9143683aad711073fe49aabb3104fe2c547d964840075be7e8f21effcf59aec8`,name:`FlxDonationPartnerPriceToggle`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationPartnerPriceToggle($input: FlxDonationPartnerPriceToggleInput!) {
  flxDonationPartnerPriceToggle(input: $input) {
    message
    errors {
      message
      field
    }
  }
}`},Mi={id:`e1ba7e4eb9d174a2cfe6c2b2a9f949762abe56dee1d0316ec69ded17aedff39a`,name:`FlxDonationPartnerReprice`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationPartnerReprice($input: FlxDonationPartnerRepriceInput!) {
  flxDonationPartnerReprice(input: $input) {
    message
    errors {
      message
      field
    }
  }
}`},Ni={id:`1afa7039296109309f349a1e9cfedc4598fd88a3833065b7d3bd7b6c5a753dae`,name:`FlxDonationPartnerSave`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationPartnerSave($input: FlxDonationPartnerSaveInput!) {
  flxDonationPartnerSave(input: $input) {
    message
    databaseId
    errors {
      message
      field
    }
  }
}`},Pi={id:`371a8a5e0f454b6782994ba763a7b3e298849a38efd08abd7af7302478c866ae`,name:`FlxDonationRefund`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationRefund($id: Int!, $amount: Float!) {
  flxDonationRefund(input: { id: $id, amount: $amount }) {
    message
    errors {
      message
      field
    }
  }
}`},Fi={id:`d09c321ce7b02597fc41bcda3f3d98e7c319834b9f12f7aca0ac511b98f261ed`,name:`FlxDonationReject`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationReject($id: Int!, $reason: String!, $notify: Boolean) {
  flxDonationReject(input: { id: $id, reason: $reason, notify: $notify }) {
    message
    errors {
      message
      field
    }
  }
}`},Ii={id:`af4bde9d0c1d3999fcda2f92c7bbe23dd284474826c2a459b8ce785d6c321e28`,name:`FlxDonationReportDelete`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationReportDelete($input: FlxDonationReportDeleteInput!) {
  flxDonationReportDelete(input: $input) {
    message
    errors {
      message
      field
    }
  }
}`},Li={id:`d6a70e5eb0876fc2447e1b5ffd1d0e82e65c50d0817cb276a520e3b165b8ac97`,name:`FlxDonationReportSave`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationReportSave($input: FlxDonationReportSaveInput!) {
  flxDonationReportSave(input: $input) {
    message
    id
    errors {
      message
      field
    }
  }
}`},Ri={id:`d86512c3c1d7966f0942f0bae8fece63186d5e542c9503bccfef684b81c1df44`,name:`FlxDonationRequest`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationRequest($input: FlxDonationRequestInput!) {
  flxDonationRequest(input: $input) {
    message
    payUrl
    whatsappUrl
    emailed
    errors {
      message
      field
    }
  }
}`},zi={id:`24adc3d2339517af760ccc138569f16a2fdcfae616688686d94b25c11453c734`,name:`FlxDonationSaveNames`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationSaveNames($id: Int!, $names: [FlxDonationNameInput!]!) {
  flxDonationSaveNames(input: { id: $id, names: $names }) {
    message
    errors {
      message
      field
    }
  }
}`},Bi={id:`bb712ac3efc8d408d4f558e7abc8c5605a350cc4831626730a835b0fff078283`,name:`FlxDonationsBulk`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationsBulk($ids: [Int!]!, $action: FlxDonationBulkAction!) {
  flxDonationsBulk(input: { ids: $ids, action: $action }) {
    done
    message
    errors {
      message
      field
    }
  }
}`},Vi={id:`69187e77567c55d51ee8de2efa8afae0069196d131e6a00012747cb782e869ae`,name:`FlxDonationSetReferral`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationSetReferral($id: Int!, $referralId: Int!) {
  flxDonationSetReferral(input: { id: $id, referralId: $referralId }) {
    message
    errors {
      message
      field
    }
  }
}`},Hi={id:`7627fae551986ae1a794441888c6191ffbe91ecb6a41c5809ce8781c910df6a5`,name:`FlxDonationSetStatus`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationSetStatus($id: Int!, $status: String!) {
  flxDonationSetStatus(input: { id: $id, status: $status }) {
    message
    errors {
      message
      field
    }
  }
}`},Ui={id:`eb2bfe6283664a0e3cf0c79de33717c8703adb7a5b1a7450e50ff47a055fdd31`,name:`FlxDonationSettingsBackfill`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationSettingsBackfill($input: FlxDonationSettingsBackfillInput!) {
  flxDonationSettingsBackfill(input: $input) {
    message
    errors {
      message
      field
    }
  }
}`},Wi={id:`4225c48d6de114b2891821b4c49be40b9e01aee7aa9ac1ecb0f2744b2ae875ba`,name:`FlxDonationSettingsCategories`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationSettingsCategories($input: FlxDonationSettingsCategoriesInput!) {
  flxDonationSettingsCategories(input: $input) {
    message
    errors {
      message
      field
    }
  }
}`},Gi={id:`34bb0994fe911a2e7f6a99c31d281d99b5a59db7cb1fd05093e983e69387c608`,name:`FlxDonationSettingsGeneral`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationSettingsGeneral($input: FlxDonationSettingsGeneralInput!) {
  flxDonationSettingsGeneral(input: $input) {
    message
    errors {
      message
      field
    }
  }
}`},Ki={id:`525fc0b9260f7bd63c61c0ca4de8c231e23f854fc8bc381904a3ef0ff6cfc2d3`,name:`FlxDonationSettingsOrganisation`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationSettingsOrganisation($input: FlxDonationSettingsOrganisationInput!) {
  flxDonationSettingsOrganisation(input: $input) {
    message
    errors {
      message
      field
    }
  }
}`},qi={id:`c9d1f089bdcec758fad836e6d3e872b4eb3d54c4c6ffcc61efea631487f5f25a`,name:`FlxDonationSettingsTabs`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationSettingsTabs($input: FlxDonationSettingsTabsInput!) {
  flxDonationSettingsTabs(input: $input) {
    message
    errors {
      message
      field
    }
  }
}`},Ji={id:`0abceed04ca825c9bf79650388a17aaeb283f8c754b3f8bcac3fec8bbd4fba54`,name:`FlxDonationSettingsTrack`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationSettingsTrack($input: FlxDonationSettingsTrackInput!) {
  flxDonationSettingsTrack(input: $input) {
    message
    errors {
      message
      field
    }
  }
}`},Yi={id:`567f3485daa2c2bf3291245afd724fd6227d421419288776cec67d119bef394b`,name:`FlxDonationTrackApply`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationTrackApply($input: FlxDonationTrackApplyInput!) {
  flxDonationTrackApply(input: $input) {
    message
    warning
    errors {
      message
      field
    }
  }
}`},Xi={id:`1de88f8de73f9c647b929036b83398c900a209bd44d2ac82a0e5ce95451130a1`,name:`FlxDonationTrackEdit`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationTrackEdit($input: FlxDonationTrackEditInput!) {
  flxDonationTrackEdit(input: $input) {
    message
    errors {
      message
      field
    }
  }
}`},Zi={id:`f7f19f8514a5f7864ad1d69da3cd495c64e418d78e9012b19bf46b2d3b52f5a2`,name:`FlxDonationTrackFileRemove`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationTrackFileRemove($input: FlxDonationTrackFileRemoveInput!) {
  flxDonationTrackFileRemove(input: $input) {
    message
    errors {
      message
      field
    }
  }
}`},Qi={id:`ef03b1a6c986dc254f7928a0abc9d463d7efaf153143833ab211f771d2120765`,name:`FlxDonationTrackFileSend`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationTrackFileSend($input: FlxDonationTrackFileSendInput!) {
  flxDonationTrackFileSend(input: $input) {
    message
    errors {
      message
      field
    }
  }
}`},$i={id:`1135616b5139f6b81f700026e68f828e5b91b1c3522de16ebb5eb4497e37b937`,name:`FlxDonationTrackNote`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationTrackNote($input: FlxDonationTrackNoteInput!) {
  flxDonationTrackNote(input: $input) {
    message
    errors {
      message
      field
    }
  }
}`},ea={id:`eda6fbddda92b1100f0994df6176df9c1a1cb60e007293cc3c281dc6526f485f`,name:`FlxDonationTypeDelete`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationTypeDelete($id: Int!) {
  flxDonationTypeDelete(input: { id: $id }) {
    message
    errors {
      message
      field
    }
  }
}`},ta={id:`9c5e22d0fe5178e9ad6bd430930ae433ff14ad2c5fcae34909cf4ecc9a305cde`,name:`FlxDonationTypeMove`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationTypeMove($id: Int!, $up: Boolean!) {
  flxDonationTypeMove(input: { id: $id, up: $up }) {
    message
    errors {
      message
      field
    }
  }
}`},na={id:`8403234e422dfe7a55652c65f0d5b8aacaa814d8bb91905a280ca5ec66690cd3`,name:`FlxDonationTypeSamples`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationTypeSamples($key: String!) {
  flxDonationTypeSamples(input: { key: $key }) {
    message
    errors {
      message
      field
    }
  }
}`},ra={id:`b95fe0f6b9d3cb211fbd6496162fc5707ae9a365168a2ac2b4021c7226dd1186`,name:`FlxDonationTypeSave`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationTypeSave($input: FlxDonationTypeSaveInput!) {
  flxDonationTypeSave(input: $input) {
    message
    databaseId
    warning
    errors {
      message
      field
    }
  }
}`},ia={id:`ddcf9909b13c0656e826a34a5353b05151cc9876bda2aa4c2a31186d07fa8081`,name:`FlxDonationTypeSetStatus`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonationTypeSetStatus($id: Int!, $status: FlxDonationTypeStatus!) {
  flxDonationTypeSetStatus(input: { id: $id, status: $status }) {
    message
    errors {
      message
      field
    }
  }
}`},aa={id:`ba4cccce694ad4a055f6791126f7c51cb03f598a55bc38793fb921d7b9789ec7`,name:`FlxDonorSave`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxDonorSave($id: Int!, $consentKvkk: Boolean!, $consentEmail: Boolean!, $consentSms: Boolean!, $note: String!) {
  flxDonorSave(
    input: {
      id: $id
      consentKvkk: $consentKvkk
      consentEmail: $consentEmail
      consentSms: $consentSms
      note: $note
    }
  ) {
    message
    errors {
      message
      field
    }
  }
}`},oa={id:`61dca741b4cda6ec8f60505b8548df2e5d33c72546464ffa58e107cc85ba83d5`,name:`FlxFilesAiEdit`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesAiEdit($input: FlxFilesAiEditInput!) {
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
}`},sa={id:`32abcee8e0a969a62b439f52fbce12c71c847005925cfae133f29ebb40068a31`,name:`FlxFilesAiExplain`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesAiExplain($input: FlxFilesAiExplainInput!) {
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
}`},ca={id:`11c4bcaba7b6a88fc32514cdc6c72fa7eddee29ae51a30d924c9ef337ae01482`,name:`FlxFilesBootstrap`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesBootstrap {
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
}`},la={id:`4f023bb510e149c91f1629825589973c85cae4835fa812ad549677ba6980d5bc`,name:`FlxFilesChmod`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesChmod($input: FlxFilesChmodInput!) {
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
}`},ua={id:`f68b3adcc3c580e155d2613c5ebd32eda97074945cf130cd3b0c3aa8b15796e9`,name:`FlxFilesCopy`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesCopy($input: FlxFilesCopyInput!) {
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
}`},da={id:`9a932876948ac21226ac489170974c79b8567a55bd985e88b64f69a0f76baee6`,name:`FlxFilesDisk`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesDisk($refresh: Boolean) {
  flxFilesDisk(refresh: $refresh) {
    usedBytes
    totalBytes
    fileCount
    measured
    scanning
  }
}`},fa={id:`f669e131af94b4ab873afbea5a686bef1d50491e121c61abf6d622f7de7ee944`,name:`FlxFilesDuplicate`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesDuplicate($input: FlxFilesDuplicateInput!) {
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
}`},pa={id:`68f93e146bd77ed04fea958b274e19984ff9fea5474d5c1818fea7a00976d3aa`,name:`FlxFilesFile`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesFile($path: String!) {
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
}`},ma={id:`eff7fe14bc929cb4523e5cc13b4c9944073068de7ed0704080b8676ab83e75da`,name:`FlxFilesFolder`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesFolder($path: String!, $first: Int = 500, $after: String, $sort: FlxFilesSort, $order: FlxFilesOrder, $hidden: Boolean, $foldersOnly: Boolean, $countItems: Boolean) {
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
}`},ha={id:`3df09f77ce66415c30bf3f11448063018063298f8afe8693354151f339789a2d`,name:`FlxFilesFolderCreate`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesFolderCreate($input: FlxFilesFolderCreateInput!) {
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
}`},ga={id:`df297c445606cc8674482cb2a7dad93ef00b19f27724a00e88ff1013a86847bc`,name:`FlxFilesLog`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesLog($first: Int = 50, $after: String, $path: String, $via: FlxFilesVia) {
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
}`},_a={id:`ed9598e1137f47c40a7f58d684fd9cd4a825c7389ea9a74ee2d41891f38db51c`,name:`FlxFilesMove`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesMove($input: FlxFilesMoveInput!) {
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
}`},va={id:`4fda84d50728f46b957ba8c54dd0ddfa92f51531d8881ae73a899645d69dda5b`,name:`FlxFilesRename`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesRename($input: FlxFilesRenameInput!) {
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
}`},ya={id:`1a3f6c38af3fdde3ce542ed5459d58bc51e2e0531923742d050c3944e43fbfc6`,name:`FlxFilesRevert`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesRevert($input: FlxFilesRevertInput!) {
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
}`},ba={id:`5356c293231db15f518a757f5eb788d353ec8f33108eb57039c272de7e36fff4`,name:`FlxFilesSearch`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesSearch($path: String!, $query: String!, $content: Boolean) {
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
}`},xa={id:`47f5421ba6b9e20badaeb414b94d527088d2bfd140e32f7a147a3c730d4c5041`,name:`FlxFilesSettingsUpdate`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesSettingsUpdate($input: FlxFilesSettingsUpdateInput!) {
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
}`},Sa={id:`67739f018af821391785f275b9817550d051b7dad1879bcd1e491c84931c3314`,name:`FlxFilesShare`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesShare($input: FlxFilesShareInput!) {
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
}`},Ca={id:`714c52700ef0d6771f581011a651a3c23a0fe8e8ba99395c93d621c7cb988b4a`,name:`FlxFilesSize`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesSize($path: String!) {
  flxFilesSize(path: $path) {
    bytes
    files
    folders
    partial
  }
}`},wa={id:`770f0507a338ab54a68921d53536c2521475117d015f2ecbae7c73c9836e5f8e`,name:`FlxFilesTrash`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesTrash($input: FlxFilesTrashInput!) {
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
}`},Ta={id:`e7695dfcf0d582a7b2aac6dff97e29d64d15b8aacebe1bb54905fc69dac327a1`,name:`FlxFilesTrashList`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesTrashList($first: Int = 200, $after: String) {
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
}`},Ea={id:`2f986646c02a7e0cbfc324e66a5f57e02434a3b14c8137f0586fb3800647cb98`,name:`FlxFilesTrashPurge`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesTrashPurge($input: FlxFilesTrashPurgeInput!) {
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
}`},Da={id:`ae8ec6576f6d062c32245c2784087a446d828c8ed7f09e69761abbf62429ddaa`,name:`FlxFilesTrashRestore`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesTrashRestore($input: FlxFilesTrashRestoreInput!) {
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
}`},Oa={id:`d75223e3edb904ea34d2c371549694e223391ed1969dcb5eb23fa6e8350d6775`,name:`FlxFilesUnzip`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesUnzip($input: FlxFilesUnzipInput!) {
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
}`},ka={id:`f0d2ddd90670e48e6ecda7e06b56f5ed9687520d1a05c430b6f7d6e2269e8e5d`,name:`FlxFilesVersionContent`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesVersionContent($path: String!, $versionId: String!) {
  flxFilesVersionContent(path: $path, versionId: $versionId)
}`},Aa={id:`6723e081addb1e78d9fbf04474e63ac1877473e63ee8fa3e83bd70f8d13b0272`,name:`FlxFilesVersions`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesVersions($path: String!) {
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
}`},ja={id:`723e828c659888ada86d76eaf190cb45ba9d65731e4474ab57051f8cd19db9b6`,name:`FlxFilesWrite`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesWrite($input: FlxFilesWriteInput!) {
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
}`},Ma={id:`32203b0cbb4dd23257d8028daf389e5819361d7e3352f1ddab908a7459ba9244`,name:`FlxFilesZip`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesZip($input: FlxFilesZipInput!) {
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
}`},Na={id:`b6b9dbb8c2341ee7ef7436e8016432e56390ff11bf9a27196195f7fd4c49ce22`,name:`FlxHeadlessFrontendDelete`,kind:`mutation`,plugin:`flx-headless`,document:`mutation FlxHeadlessFrontendDelete($id: ID!) {
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
  cmsUrl
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
}`},Pa={id:`2d5bef00f49121273e838c95c999990618f4b408b2170f96662ff9deac2f5e69`,name:`FlxHeadlessFrontendSave`,kind:`mutation`,plugin:`flx-headless`,document:`mutation FlxHeadlessFrontendSave($input: FlxHeadlessFrontendSaveInput!) {
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
  cmsUrl
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
}`},Fa={id:`221a840f808f07f4ec956f3a8cbb1452d87070ac2ece04b21207badfb1a965f9`,name:`FlxHeadlessLogClear`,kind:`mutation`,plugin:`flx-headless`,document:`mutation FlxHeadlessLogClear {
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
  cmsUrl
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
}`},Ia={id:`2c19920fe81f7414561796fda1440d6661209606f6568c394d3220797d8e57d2`,name:`FlxHeadlessRevalidateAll`,kind:`mutation`,plugin:`flx-headless`,document:`mutation FlxHeadlessRevalidateAll {
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
  cmsUrl
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
}`},La={id:`f695a9bea8b63c802b68bb7075934582ba79931367d4b507e7ab4bd291cc16fc`,name:`FlxHeadlessRevalidateTest`,kind:`mutation`,plugin:`flx-headless`,document:`mutation FlxHeadlessRevalidateTest($id: ID!) {
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
  cmsUrl
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
}`},Ra={id:`3dc26a79e0ad9e5da64864fbd796bbccb9b155a8d691e9f5f081d46616455525`,name:`FlxHeadlessScreen`,kind:`query`,plugin:`flx-headless`,document:`query FlxHeadlessScreen {
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
  cmsUrl
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
}`},za={id:`ea296d990ec02d34bef8cca5aae3abbc722136477a3945bf33f120306abae343`,name:`FlxHeadlessWpFrontSet`,kind:`mutation`,plugin:`flx-headless`,document:`mutation FlxHeadlessWpFrontSet($mode: FlxHeadlessWpFront!) {
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
  cmsUrl
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
}`},Ba={id:`14ae18c34134e93d807ec1e3d21907a4c002555d4d327011a6cbdd05c13743ab`,name:`FlxLanguageSettingsScreen`,kind:`query`,plugin:`flx-lang`,document:`query FlxLanguageSettingsScreen {
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
}`},Va={id:`0da4e1f193deec851174ea768e4699d956a94e7670b172e44dafe5bf9f788a52`,name:`FlxLanguageSettingsUpdate`,kind:`mutation`,plugin:`flx-lang`,document:`mutation FlxLanguageSettingsUpdate($input: FlxLanguageSettingsUpdateInput!) {
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
}`},Ha={id:`1ce4331d4be7f6da93d3460e77ec431bfc979fe3b0dede869c7ff9d6141038b2`,name:`FlxMailDnsCheck`,kind:`mutation`,plugin:`flx-mail`,document:`mutation FlxMailDnsCheck($input: FlxMailDnsCheckInput!) {
  flxMailDnsCheck(input: $input) {
    dns {
      domain
      spf
      dmarc
      mx
      error
      checkedAt {
        ...FlxMailDate
      }
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxMailDate on FlxDateTime {
  iso
  label
  relative
}`},Ua={id:`e49af879fc11a0d4a247ebe046154feec4d3dbc3d5569b8a0c55a3bee3e94573`,name:`FlxMailImapTest`,kind:`mutation`,plugin:`flx-mail`,document:`mutation FlxMailImapTest($input: FlxMailImapTestInput!) {
  flxMailImapTest(input: $input) {
    ok
    message
    errors {
      code
      message
      field
    }
  }
}`},Wa={id:`4685c308d1fd254adc1c7c727ba6d4dabc851f0068cb07c31037e2e29993b4ce`,name:`FlxMailLog`,kind:`query`,plugin:`flx-mail`,document:`query FlxMailLog($id: Int!) {
  flxMailLog(id: $id) {
    ...FlxMailLog
  }
}

fragment FlxMailDate on FlxDateTime {
  iso
  label
  relative
}

fragment FlxMailLog on FlxMailLog {
  ...FlxMailLogRow
  body
}

fragment FlxMailLogRow on FlxMailLog {
  databaseId
  createdAt {
    ...FlxMailDate
  }
  channel
  template
  recipient
  subject
  status
  error
  sender
  campaignId
  person {
    id
    name
    email
  }
}`},Ga={id:`2e009a0c5c90678cfa7a1d8a018abf9a208de265ac5b79fb639ef81ca5a8f85d`,name:`FlxMailLogs`,kind:`query`,plugin:`flx-mail`,document:`query FlxMailLogs($first: Int, $after: String, $status: String, $channel: String, $template: String, $campaign: Int, $search: String) {
  flxMailLogs(
    first: $first
    after: $after
    status: $status
    channel: $channel
    template: $template
    campaign: $campaign
    search: $search
  ) {
    totalCount
    pageInfo {
      hasNextPage
      endCursor
    }
    nodes {
      ...FlxMailLogRow
    }
  }
}

fragment FlxMailDate on FlxDateTime {
  iso
  label
  relative
}

fragment FlxMailLogRow on FlxMailLog {
  databaseId
  createdAt {
    ...FlxMailDate
  }
  channel
  template
  recipient
  subject
  status
  error
  sender
  campaignId
  person {
    id
    name
    email
  }
}`},Ka={id:`d3baa5bbf327db24cdffd70af93e04ad01819c4e5a589a188651793371772a6c`,name:`FlxMailPreview`,kind:`mutation`,plugin:`flx-mail`,document:`mutation FlxMailPreview($input: FlxMailPreviewInput!) {
  flxMailPreview(input: $input) {
    preview {
      subject
      html
    }
    errors {
      code
      message
      field
    }
  }
}`},qa={id:`bfc6073b42f3da66067ea289b3862eff5285e59df95ce59368e456f01fbf81c8`,name:`FlxMailSettings`,kind:`query`,plugin:`flx-mail`,document:`query FlxMailSettings {
  flxMailSettings {
    ...FlxMailSettings
  }
}

fragment FlxMailDate on FlxDateTime {
  iso
  label
  relative
}

fragment FlxMailSecret on FlxMailSecret {
  set
  mask
  fromConfig
}

fragment FlxMailSettings on FlxMailSettings {
  fromName
  fromEmail
  noreplyName
  noreplyEmail
  replyTo
  forceFrom
  setSender
  mailer
  smtpHost
  smtpPort
  smtpSecure
  smtpAuthentication
  smtpUser
  smtpNoreplyUser
  smtpPassword {
    ...FlxMailSecret
  }
  smtpNoreplyPassword {
    ...FlxMailSecret
  }
  mailboxEnabled
  imapHost
  imapPort
  imapSecure
  imapUser
  imapPassword {
    ...FlxMailSecret
  }
  mailboxDays
  mailboxKeepDays
  mailboxSignature
  wrapPlain
  wrapHtml
  emailImagesFix
  embedImages
  footerArt
  logo
  logoWhite
  logoWidth
  colorPrimary
  colorDark
  colorBg
  colorText
  orgName
  tagline
  address
  phone
  contactEmail
  website
  social {
    network
    url
  }
  footerNote
  socialBlockCore
  socialBlockWc
  socialVerified
  logEnabled
  logBody
  logDays
  bulkPerRun
  bulkUnsubscribe
  optoutEnabled
  optoutFooterWa
  optoutFooterSms
  optoutText
  optoutKeywords
  optinKeywords
  optoutReply
  optinReply
  limitDaily
  limitRecipients
  limitReserve
  limitSizeMb
  limitAttachMb
  limitStorageGb
  limitMailboxEmails
  limitAliases
  limitForwarders
  unsubscribedCount
  optoutWhatsappCount
  optoutSmsCount
  wooActive
  lastTest {
    ok
    to
    error
    at {
      ...FlxMailDate
    }
  }
  wcBranded {
    ...FlxMailDate
  }
  otherSmtpPlugin
  smtpAccountNotes {
    mailbox
    level
    text
  }
  activeSocialPlugins
  quota {
    mailbox
    type
    used
    limit
    left
    percent
    next {
      ...FlxMailDate
    }
  }
  quotaFullDays
  dns {
    domain
    spf
    dmarc
    mx
    error
    checkedAt {
      ...FlxMailDate
    }
  }
  footerArts {
    key
    label
  }
  socialNetworks {
    key
    label
  }
}`},Ja={id:`42b4f6b70ddadea67c3f0b0e38567c4fa46142978949dd16d5dd74a5f11020ee`,name:`FlxMailSettingsUpdate`,kind:`mutation`,plugin:`flx-mail`,document:`mutation FlxMailSettingsUpdate($input: FlxMailSettingsUpdateInput!) {
  flxMailSettingsUpdate(input: $input) {
    settings {
      ...FlxMailSettings
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxMailDate on FlxDateTime {
  iso
  label
  relative
}

fragment FlxMailSecret on FlxMailSecret {
  set
  mask
  fromConfig
}

fragment FlxMailSettings on FlxMailSettings {
  fromName
  fromEmail
  noreplyName
  noreplyEmail
  replyTo
  forceFrom
  setSender
  mailer
  smtpHost
  smtpPort
  smtpSecure
  smtpAuthentication
  smtpUser
  smtpNoreplyUser
  smtpPassword {
    ...FlxMailSecret
  }
  smtpNoreplyPassword {
    ...FlxMailSecret
  }
  mailboxEnabled
  imapHost
  imapPort
  imapSecure
  imapUser
  imapPassword {
    ...FlxMailSecret
  }
  mailboxDays
  mailboxKeepDays
  mailboxSignature
  wrapPlain
  wrapHtml
  emailImagesFix
  embedImages
  footerArt
  logo
  logoWhite
  logoWidth
  colorPrimary
  colorDark
  colorBg
  colorText
  orgName
  tagline
  address
  phone
  contactEmail
  website
  social {
    network
    url
  }
  footerNote
  socialBlockCore
  socialBlockWc
  socialVerified
  logEnabled
  logBody
  logDays
  bulkPerRun
  bulkUnsubscribe
  optoutEnabled
  optoutFooterWa
  optoutFooterSms
  optoutText
  optoutKeywords
  optinKeywords
  optoutReply
  optinReply
  limitDaily
  limitRecipients
  limitReserve
  limitSizeMb
  limitAttachMb
  limitStorageGb
  limitMailboxEmails
  limitAliases
  limitForwarders
  unsubscribedCount
  optoutWhatsappCount
  optoutSmsCount
  wooActive
  lastTest {
    ok
    to
    error
    at {
      ...FlxMailDate
    }
  }
  wcBranded {
    ...FlxMailDate
  }
  otherSmtpPlugin
  smtpAccountNotes {
    mailbox
    level
    text
  }
  activeSocialPlugins
  quota {
    mailbox
    type
    used
    limit
    left
    percent
    next {
      ...FlxMailDate
    }
  }
  quotaFullDays
  dns {
    domain
    spf
    dmarc
    mx
    error
    checkedAt {
      ...FlxMailDate
    }
  }
  footerArts {
    key
    label
  }
  socialNetworks {
    key
    label
  }
}`},Ya={id:`5f19477b0ed44efb70ad3813a8abad3bef81367ea8cc3eb654bc9ed14e123af7`,name:`FlxMailSystemChecks`,kind:`query`,plugin:`flx-mail`,document:`query FlxMailSystemChecks {
  flxMailSystemChecks {
    id
    group
    label
    status
    message
    actionUrl
    actionLabel
  }
}`},Xa={id:`5cc0d97ffd52959ce1f2009c5d57764a9b753ca572d2d337e94833f242f8141c`,name:`FlxMailTemplate`,kind:`query`,plugin:`flx-mail`,document:`query FlxMailTemplate($key: ID!) {
  flxMailTemplate(key: $key) {
    ...FlxMailTemplate
  }
}

fragment FlxMailTemplate on FlxMailTemplate {
  key
  label
  group
  description
  audience
  enabled
  canDisable
  sender
  editable
  kind
  wooUrl
  lastSubject
  senderInfo
  senderNoreply
  subject
  heading
  body
  buttonText
  buttonUrl
  wrap
  vars {
    name
    description
  }
}`},Za={id:`0a21604144f35ad0c0e34bd33255b7f594cb283fc180efb1628ef8cc0ca848f9`,name:`FlxMailTemplateReset`,kind:`mutation`,plugin:`flx-mail`,document:`mutation FlxMailTemplateReset($input: FlxMailTemplateResetInput!) {
  flxMailTemplateReset(input: $input) {
    template {
      ...FlxMailTemplate
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxMailTemplate on FlxMailTemplate {
  key
  label
  group
  description
  audience
  enabled
  canDisable
  sender
  editable
  kind
  wooUrl
  lastSubject
  senderInfo
  senderNoreply
  subject
  heading
  body
  buttonText
  buttonUrl
  wrap
  vars {
    name
    description
  }
}`},Qa={id:`65c8ca2d6f5598d1da3c1c058e2f9d00b541e2ccbd341090af8e5526b7f89172`,name:`FlxMailTemplates`,kind:`query`,plugin:`flx-mail`,document:`query FlxMailTemplates($search: String, $first: Int, $after: String) {
  flxMailTemplates(search: $search, first: $first, after: $after) {
    totalCount
    pageInfo {
      hasNextPage
      endCursor
    }
    nodes {
      ...FlxMailTemplate
    }
  }
  flxMailLogStats {
    sent
    failed
    blocked
    senderName
    senderEmail
    method
  }
}

fragment FlxMailTemplate on FlxMailTemplate {
  key
  label
  group
  description
  audience
  enabled
  canDisable
  sender
  editable
  kind
  wooUrl
  lastSubject
  senderInfo
  senderNoreply
  subject
  heading
  body
  buttonText
  buttonUrl
  wrap
  vars {
    name
    description
  }
}`},$a={id:`ff95c58a851387b657af669a913f058c68edccadc9c2aba301279f5dec8f7e75`,name:`FlxMailTemplateUpdate`,kind:`mutation`,plugin:`flx-mail`,document:`mutation FlxMailTemplateUpdate($input: FlxMailTemplateUpdateInput!) {
  flxMailTemplateUpdate(input: $input) {
    template {
      ...FlxMailTemplate
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxMailTemplate on FlxMailTemplate {
  key
  label
  group
  description
  audience
  enabled
  canDisable
  sender
  editable
  kind
  wooUrl
  lastSubject
  senderInfo
  senderNoreply
  subject
  heading
  body
  buttonText
  buttonUrl
  wrap
  vars {
    name
    description
  }
}`},eo={id:`e7e00a1ac9e4a3b29bc714fd7a4e75dfcd5d586b4d74a99f0afaa121ddff4ae9`,name:`FlxMailTestSend`,kind:`mutation`,plugin:`flx-mail`,document:`mutation FlxMailTestSend($input: FlxMailTestSendInput!) {
  flxMailTestSend(input: $input) {
    ok
    to
    error
    at {
      ...FlxMailDate
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxMailDate on FlxDateTime {
  iso
  label
  relative
}`},to={id:`c8057a4742381eb3433249814ea0e9c1130b4f4d57a20e512a669595dac33fce`,name:`FlxMailWcBrandApply`,kind:`mutation`,plugin:`flx-mail`,document:`mutation FlxMailWcBrandApply($input: FlxMailWcBrandApplyInput!) {
  flxMailWcBrandApply(input: $input) {
    wcBranded {
      ...FlxMailDate
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxMailDate on FlxDateTime {
  iso
  label
  relative
}`},no={id:`42082b8e558e85edc6707d34ccc28c3b8e6b72fd28c53a27fd9dcf8696b01254`,name:`FlxPosAccountDelete`,kind:`mutation`,plugin:`flx-pos`,document:`mutation FlxPosAccountDelete($input: FlxPosAccountDeleteInput!) {
  flxPosAccountDelete(input: $input) {
    accounts {
      ...FlxPosAccountFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxPosAccountFields on FlxPosAccount {
  id
  provider
  providerName
  group
  label
  name
  enabled
  test
  mode
  currencies
  instMin
  title
  display
  notifyUrl
  takesCard
  creds {
    key
    value
    isSet
    mask
  }
  installments {
    count
    rate
  }
}`},ro={id:`d3b6618dcf474d711182cdc93063790717cfb98d64dfddd46889bce573b8dc8b`,name:`FlxPosAccounts`,kind:`query`,plugin:`flx-pos`,document:`query FlxPosAccounts {
  flxPosAccounts {
    ...FlxPosAccountFields
  }
  flxPosProviders {
    id
    name
    group
    ready
    currencies
    caps
    notes
    testCards
    modes {
      value
      label
    }
    fields {
      key
      label
      secret
      help
      defaultValue
    }
    endpoints {
      value
      label
    }
  }
  flxPosSettings {
    ...FlxPosSettingsFields
  }
}

fragment FlxPosAccountFields on FlxPosAccount {
  id
  provider
  providerName
  group
  label
  name
  enabled
  test
  mode
  currencies
  instMin
  title
  display
  notifyUrl
  takesCard
  creds {
    key
    value
    isSet
    mask
  }
  installments {
    count
    rate
  }
}

fragment FlxPosSettingsFields on FlxPosSettings {
  cardTitle
  cardDesc
  cardAccount
  display
  showBrands
  blockFallback
  saveLog
  routes {
    value
    label
  }
  displays {
    value
    label
  }
}`},io={id:`a950036a9e4002ecbd019ee9a175a96217efbb118b6e488754294db8172a7c6e`,name:`FlxPosAccountSave`,kind:`mutation`,plugin:`flx-pos`,document:`mutation FlxPosAccountSave($input: FlxPosAccountSaveInput!) {
  flxPosAccountSave(input: $input) {
    account {
      ...FlxPosAccountFields
    }
    accounts {
      ...FlxPosAccountFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxPosAccountFields on FlxPosAccount {
  id
  provider
  providerName
  group
  label
  name
  enabled
  test
  mode
  currencies
  instMin
  title
  display
  notifyUrl
  takesCard
  creds {
    key
    value
    isSet
    mask
  }
  installments {
    count
    rate
  }
}`},ao={id:`7605fa5abfa8d83fb3b2c8f27a3aca56a3504dde0f850ca10be7edade982fb27`,name:`FlxPosAccountTest`,kind:`mutation`,plugin:`flx-pos`,document:`mutation FlxPosAccountTest($input: FlxPosAccountTestInput!) {
  flxPosAccountTest(input: $input) {
    result {
      ok
      supported
      message
    }
    errors {
      code
      message
      field
    }
  }
}`},oo={id:`afc3c714e59558a8754484e117ecafcc1f9106713db50c030dd6db3361b9ddc3`,name:`FlxPosMethods`,kind:`query`,plugin:`flx-pos`,document:`query FlxPosMethods {
  flxPosMethods {
    ...FlxPosMethodsFields
  }
}

fragment FlxPosMethodsFields on FlxPosMethods {
  blockCheckout
  cardMethod
  cardMethodOwner
  checkoutUrl
  currency
  cardChoices {
    value
    label
  }
  methods {
    id
    title
    methodTitle
    description
    enabled
    order
    icons
    flux
    hidden
    hasFields
    offline
    settingsUrl
    caps {
      key
      label
      help
      state
      note
    }
  }
}`},so={id:`feae8abe7c2290c6bd90665ed902cf45fa4004d82c4db2bab2a82bfc7517b14b`,name:`FlxPosMethodsUpdate`,kind:`mutation`,plugin:`flx-pos`,document:`mutation FlxPosMethodsUpdate($input: FlxPosMethodsUpdateInput!) {
  flxPosMethodsUpdate(input: $input) {
    methods {
      ...FlxPosMethodsFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxPosMethodsFields on FlxPosMethods {
  blockCheckout
  cardMethod
  cardMethodOwner
  checkoutUrl
  currency
  cardChoices {
    value
    label
  }
  methods {
    id
    title
    methodTitle
    description
    enabled
    order
    icons
    flux
    hidden
    hasFields
    offline
    settingsUrl
    caps {
      key
      label
      help
      state
      note
    }
  }
}`},co={id:`f2b12a5ec0fe1d6a86330ac32b62a9a5106dd965395bb52e993d40240e837d5c`,name:`FlxPosSettingsUpdate`,kind:`mutation`,plugin:`flx-pos`,document:`mutation FlxPosSettingsUpdate($input: FlxPosSettingsUpdateInput!) {
  flxPosSettingsUpdate(input: $input) {
    settings {
      ...FlxPosSettingsFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxPosSettingsFields on FlxPosSettings {
  cardTitle
  cardDesc
  cardAccount
  display
  showBrands
  blockFallback
  saveLog
  routes {
    value
    label
  }
  displays {
    value
    label
  }
}`},lo={id:`85c9a81b372991d843c90522ae0efc54ad2fe5c093206e5f8dfee6b27b2487cf`,name:`FlxPosTxnRefund`,kind:`mutation`,plugin:`flx-pos`,document:`mutation FlxPosTxnRefund($input: FlxPosTxnRefundInput!) {
  flxPosTxnRefund(input: $input) {
    txn {
      ...FlxPosTxnRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxPosMoney on FlxMoney {
  amount
  currency
  formatted
}

fragment FlxPosTxnRow on FlxPosTxn {
  databaseId
  ref
  bankRef
  kind
  account
  accountName
  orderId
  orderNumber
  orderUrl
  installment
  card
  status
  message
  test
  refundable
  amount {
    ...FlxPosMoney
  }
  refunded {
    ...FlxPosMoney
  }
  created {
    ...FlxPosWhen
  }
}

fragment FlxPosWhen on FlxDateTime {
  iso
  timestamp
  label
  relative
}`},uo={id:`667d5bcef75f3e3b0726b965bc3bf5e6d4b5f7f87ca5d1f2d71e837710644801`,name:`FlxPosTxns`,kind:`query`,plugin:`flx-pos`,document:`query FlxPosTxns($first: Int, $after: String, $status: FlxPosTxnStatus, $account: String, $search: String) {
  flxPosTxns(
    first: $first
    after: $after
    status: $status
    account: $account
    search: $search
  ) {
    nodes {
      ...FlxPosTxnRow
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}

fragment FlxPosMoney on FlxMoney {
  amount
  currency
  formatted
}

fragment FlxPosTxnRow on FlxPosTxn {
  databaseId
  ref
  bankRef
  kind
  account
  accountName
  orderId
  orderNumber
  orderUrl
  installment
  card
  status
  message
  test
  refundable
  amount {
    ...FlxPosMoney
  }
  refunded {
    ...FlxPosMoney
  }
  created {
    ...FlxPosWhen
  }
}

fragment FlxPosWhen on FlxDateTime {
  iso
  timestamp
  label
  relative
}`},fo={id:`8475313bd287a3d2bbf784eb2da2abcae1fbc70a38f9c1d6257b9251ea9c0873`,name:`FlxRecaptchaKeysTest`,kind:`mutation`,plugin:`flx-recaptcha`,document:`mutation FlxRecaptchaKeysTest($input: FlxRecaptchaKeysTestInput!) {
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
}`},po={id:`9c0623f86ccc32d8df7367593176ecb135881242b39ad7dca166ac7c9ab9a98d`,name:`FlxRecaptchaScreen`,kind:`query`,plugin:`flx-recaptcha`,document:`query FlxRecaptchaScreen {
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
}`},mo={id:`2d24402e0c3acaad9aeb96bea6cb25a54ff8664cf8103f515b767c891690206e`,name:`FlxRecaptchaSettingsUpdate`,kind:`mutation`,plugin:`flx-recaptcha`,document:`mutation FlxRecaptchaSettingsUpdate($input: FlxRecaptchaSettingsUpdateInput!) {
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
}`},ho={id:`21a336969a0dc41b8caaa1be0a20b9bd740359347ff57bc8cb72f70ca6254f9e`,name:`FlxRoleCreate`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleCreate($input: FlxRoleCreateInput!) {
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
}`},go={id:`2bd5579ac1188eadb0dff5235840b66098c68b1b0d9e5d9a86c1a07b54d3acea`,name:`FlxRoleDelete`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleDelete($input: FlxRoleDeleteInput!) {
  flxRoleDelete(input: $input) {
    deletedKey
    errors {
      code
      message
      field
    }
  }
}`},_o={id:`af09ea3f7594b6e37e1aa272f116e57dd7079ca275a754a8107fc230ce3014df`,name:`FlxRoleMemberAdd`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleMemberAdd($input: FlxRoleMemberAddInput!) {
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
}`},vo={id:`6eab4d6f1f97457a8679a9b1dd92d47dc2e01a7f41163eff439dc854213b2a7c`,name:`FlxRoleMemberRemove`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleMemberRemove($input: FlxRoleMemberRemoveInput!) {
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
}`},yo={id:`067ae709ce0675a0f8e8ab3ab7d21d722afa9f7449f358767be373133c98de6f`,name:`FlxRoleMembers`,kind:`query`,plugin:`flx-roles`,document:`query FlxRoleMembers($key: String!, $first: Int = 50, $after: String) {
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
}`},bo={id:`20e6cdbb88f7639a8749e74cf75ef58a4ab704143f0cf0fa3474dbb2366cb1d0`,name:`FlxRoleReset`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleReset($input: FlxRoleResetInput!) {
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
}`},xo={id:`0a4da13faad85546b92fab5b45716dab5b06e87791475a9dfaa4664888375658`,name:`FlxRolesSave`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRolesSave($input: FlxRolesSaveInput!) {
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
}`},So={id:`2c22565866bcebf0aaed510b75bec73444fc8257c8d2b327873e9c2963c59030`,name:`FlxRolesScreen`,kind:`query`,plugin:`flx-roles`,document:`query FlxRolesScreen($members: Int = 50) {
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
}`},Co={id:`24ef9e0380385f71b2af51a8163f15a8362c7a02a3fee076051efdd289a4d7a4`,name:`FlxSeoAuditRun`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoAuditRun($input: FlxSeoAuditRunInput!) {
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
}`},wo={id:`25585531a77819b9e9b681a85e767cb408d01dcab0ecb5e5e3717d30b20fb89e`,name:`FlxSeoAuditScreen`,kind:`query`,plugin:`flx-seo`,document:`query FlxSeoAuditScreen($history: Int = 30) {
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
}`},To={id:`97d49670dd4df105502f5013764c377eaadec82d4f58ba3d9d0f8d3a00566bd2`,name:`FlxSeoContentCheck`,kind:`query`,plugin:`flx-seo`,document:`query FlxSeoContentCheck($limit: Int = 300) {
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
}`},Eo={id:`aca5c530650ff0a558a94968a069e5d1d183315910cf69556396462fb63d557a`,name:`FlxSeoFixApply`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoFixApply($input: FlxSeoFixApplyInput!) {
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
}`},Do={id:`fa7f2e42636c7fd69983e7637be5625f01102cc336c76b7e751dfc6b25299aea`,name:`FlxSeoGuide`,kind:`query`,plugin:`flx-seo`,document:`query FlxSeoGuide($lang: FlxSeoGuideLang) {
  flxSeoGuide(lang: $lang) {
    lang
    markdown
    sections
  }
}`},Oo={id:`84dfec5a3b859e284998fc8124abae04e2fdd4419dac91ab6cb5971882673a9d`,name:`FlxSeoIndexNowSubmit`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoIndexNowSubmit($input: FlxSeoIndexNowSubmitInput!) {
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
}`},ko={id:`59e6780cadb6bc0d4dcecc09e482056c52aeb00879b5b1c00fa35049f815dea2`,name:`FlxSeoPsiKeySave`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoPsiKeySave($input: FlxSeoPsiKeySaveInput!) {
  flxSeoPsiKeySave(input: $input) {
    saved
    errors {
      code
      message
      field
    }
  }
}`},Ao={id:`ffd79b34482e7fd6c534446f2a81d044658a4eea3fafffa2d65eb14c8b4101fd`,name:`FlxSeoSettingsSave`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoSettingsSave($flxseo: FlxSettingsFlxseoInput!) {
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
}`},jo={id:`86bc810d43babcb5cefcbb0d86511dc9661c0d5431662164e77220a7f6f5e3b8`,name:`FlxSeoSettingsScreen`,kind:`query`,plugin:`flx-seo`,document:`query FlxSeoSettingsScreen {
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
}`},Mo={id:`b8f91c52dab2869ea091bb25833ba93b1b5ba0eb62b369c3cff557466b3478ba`,name:`FlxSetupAutoUpdateSet`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupAutoUpdateSet($input: FlxSetupAutoUpdateSetInput!) {
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
}`},No={id:`327a745eaebc60ab14f76d453be58e38f0399b212c0c15b87d2dbd1488a731c4`,name:`FlxSetupBackupsDelete`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupBackupsDelete($input: FlxSetupBackupsDeleteInput!) {
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
}`},Po={id:`5c3dea411ce3bbfc57b8945d04dd11c857a00fe726bc27e95ea52175cd11ac3f`,name:`FlxSetupCheck`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupCheck {
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
}`},Fo={id:`eb8d9ce62c7a472da893ca374c342e81ceb58a4aff227e9adc67cf501aa9c504`,name:`FlxSetupCloudCard`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupCloudCard($input: FlxSetupCloudCardInput!) {
  flxSetupCloudCard(input: $input) {
    cloud {
      ...FlxSetupCloudFields
    }
    errors {
      ...FlxSetupErrors
    }
  }
}

fragment FlxSetupCloudFields on FlxSetupCloud {
  connected
  title
  text
  note
  error
  manageUrl
  postUrl
  flash
  flashType
  hidden
  hideDays
  actions {
    action
    label
    nonce
    kind
  }
}

fragment FlxSetupErrors on FlxUserError {
  code
  message
  field
}`},Io={id:`eec0142a4b5a9a7196095bff33e20721af4f68725b6c1fbccc758d9849078468`,name:`FlxSetupGoogleCardDismiss`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupGoogleCardDismiss($input: FlxSetupGoogleCardDismissInput!) {
  flxSetupGoogleCardDismiss(input: $input) {
    ok
    errors {
      ...FlxSetupErrors
    }
  }
}

fragment FlxSetupErrors on FlxUserError {
  code
  message
  field
}`},Lo={id:`37263135928406a3c3b43057dea2d7a20158ccc4683a8309d560bf066b087d66`,name:`FlxSetupGoogleCards`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupGoogleCards {
  flxSetupGoogleCards {
    id
    title
    text
    button
    installUrl
    dismissUrl
  }
}`},Ro={id:`fea5c46697571e499c36f813537e124a23f33e2a3b616d09264af5ef3fec1d26`,name:`FlxSetupHistory`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupHistory($page: Int) {
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
}`},zo={id:`6c9941eefcb59ba827d304f3336844f4a348ecb3f1b549a96d805b1c48412b6b`,name:`FlxSetupManage`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupManage($input: FlxSetupManageInput!) {
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
}`},Bo={id:`1641f892cadf8664b2298b4af2847db998d4c838826ebc0f08572a5ea648e637`,name:`FlxSetupOverview`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupOverview($search: String) {
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
}`},Vo={id:`e8e1935e414a5f61d5841b22119e7874da774b8e7905d19e76333469f34eff77`,name:`FlxSetupPackages`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupPackages($tab: FlxSetupPackageTab, $search: String, $onlyUpdates: Boolean, $first: Int = 200, $after: String) {
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
}`},Ho={id:`91629093982ae065468fb3f63497bc77ac4c8950e085938617acebb155eb692e`,name:`FlxSetupPlan`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupPlan($slugs: [String!], $wpKeys: [String!], $all: Boolean, $activate: Boolean) {
  flxSetupPlan(slugs: $slugs, wpKeys: $wpKeys, all: $all, activate: $activate) {
    kind
    slug
    id
    name
    why
    version
  }
}`},Uo={id:`20b2e2929728ec932fd886ce76780a3b35a35fc0045391ff82428916aa37984b`,name:`FlxSetupRetentionSave`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupRetentionSave($input: FlxSetupRetentionSaveInput!) {
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
}`},Wo={id:`33e46578e9a32d1bff6a6ad7f8785580eb6208cbc4b806543fc964bf1a3048f7`,name:`FlxSetupSettingsSave`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupSettingsSave($input: FlxSetupSettingsSaveInput!) {
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
}`},Go={id:`052df4f53731f34a4e2d4dfb020e3fd765d0f5cb61ce64dab3024d5adcdaceed`,name:`FlxSetupSettingsScreen`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupSettingsScreen {
  flxSetupSettings {
    ...FlxSetupSettingsFields
  }
  flxSetupConnection {
    ...FlxSetupConnectionFields
  }
  flxSetupSources {
    ...FlxSetupSourceFields
  }
  flxSetupCloud {
    ...FlxSetupCloudFields
  }
  flxSetupTelemetry {
    ...FlxSetupTelemetryFields
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

fragment FlxSetupCloudFields on FlxSetupCloud {
  connected
  title
  text
  note
  error
  manageUrl
  postUrl
  flash
  flashType
  hidden
  hideDays
  actions {
    action
    label
    nonce
    kind
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
}

fragment FlxSetupTelemetryFields on FlxSetupTelemetry {
  enabled
  status
  url
  defaultUrl
  payload
  options {
    id
    title
    text
    label
    on
  }
}`},Ko={id:`f8f67e0723c3045d9ba05b6454009c8f931f5f3a6a25a5680ccb8e478d3b2f3e`,name:`FlxSetupSourceAdd`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupSourceAdd($input: FlxSetupSourceAddInput!) {
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
}`},qo={id:`758bf6b4e9d2b43b0a4a2613eb688c94c8b73f0dd94f8e6efafd99fb74d5aa06`,name:`FlxSetupSourceChange`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupSourceChange($input: FlxSetupSourceChangeInput!) {
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
}`},Jo={id:`562e895123bcf4698f2e0b64d5f322aeecdf71358e7d66ef3677e3ed3e190a89`,name:`FlxSetupStepRun`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupStepRun($input: FlxSetupStepRunInput!) {
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
}`},Yo={id:`576a0c4f97f5e18773e557431f7c7575aa370ef63d63c8aca818590588e52929`,name:`FlxSetupTelemetrySave`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupTelemetrySave($input: FlxSetupTelemetrySaveInput!) {
  flxSetupTelemetrySave(input: $input) {
    sent
    telemetry {
      ...FlxSetupTelemetryFields
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

fragment FlxSetupTelemetryFields on FlxSetupTelemetry {
  enabled
  status
  url
  defaultUrl
  payload
  options {
    id
    title
    text
    label
    on
  }
}`},Xo={id:`ac5bddd734508bac19a20d5d020bdbd2ec57e3dd9b351b65d12e009a3cf72068`,name:`FlxSocialAccountDelete`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialAccountDelete($input: FlxSocialAccountDeleteInput!) {
  flxSocialAccountDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},Zo={id:`9bec0a5cfe1a3cb3321b0913feb2586f8948d0fb81cdfa85c46c0d6c1d380492`,name:`FlxSocialAccountRefresh`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialAccountRefresh($input: FlxSocialAccountRefreshInput!) {
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
}`},Qo={id:`fb8b8e12765fec1841e78ad598cbabafad6ad06e26ae41571b78081081bb35d0`,name:`FlxSocialAccountReorder`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialAccountReorder($input: FlxSocialAccountReorderInput!) {
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
}`},$o={id:`ddde3fb7af5fc653690c8d162de6a4a70f367d608c25e10e234f43d2b2eeeecb`,name:`FlxSocialAccountUpdate`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialAccountUpdate($input: FlxSocialAccountUpdateInput!) {
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
}`},es={id:`3d420b1c8992d44b15a62c551e463f57a3ff6e9cb8a480d28e5ba6d956724b5a`,name:`FlxSocialAiWrite`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialAiWrite($input: FlxSocialAiWriteInput!) {
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
}`},ts={id:`d152736fe8890c423eaa3d4481b02a64c2827c46b46be9b2f4111b313b3411e3`,name:`FlxSocialAppUpdate`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialAppUpdate($input: FlxSocialAppUpdateInput!) {
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
}`},ns={id:`777e5e347a4e283420534a0c3123fcc967ba15ca3e8799e50a3dfd4105fdde68`,name:`FlxSocialBoot`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialBoot {
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
}`},rs={id:`7a97a59e91842fa3a8db49e219db99e0cc8df12bccaf04637a5cb01abd82b25f`,name:`FlxSocialConnectUrl`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialConnectUrl($for: String!) {
  flxSocialConnectUrl(for: $for)
}`},is={id:`cefaf931508b3dfca6c7addc0f5c6e7e55091746577f16dcb1de6270fa7606ce`,name:`FlxSocialCounts`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialCounts {
  flxSocialCounts {
    pending
    scheduled
    failed
    due
  }
}`},as={id:`05849f5c15939169ff6d203b2ef91e4c8467f528073099294c6c4799af4422fe`,name:`FlxSocialLog`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialLog {
  flxSocialLog {
    databaseId
    time {
      timestamp
    }
    level
    accountId
    message
  }
}`},os={id:`ef5f39879b17171c3946b6fe7f72b3ec1a072e805cecc6ab5ead805f804ffd5d`,name:`FlxSocialMedia`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialMedia($ids: [Int!], $search: String, $page: Int) {
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
}`},ss={id:`65c5355402f955e3c229ad9d455e6c81023d21f910725625c69d71b1676e95c6`,name:`FlxSocialMediaAdd`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialMediaAdd($input: FlxSocialMediaAddInput!) {
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
}`},cs={id:`6be8bb6227b0ea3f5fb3a6b4cf402977622023b1544fde917e0bd16c72506afd`,name:`FlxSocialMediaImport`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialMediaImport($input: FlxSocialMediaImportInput!) {
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
}`},ls={id:`17e002c255a2a86aa0d738a5603ec081a9c987125aa1ac32b6cbcd4ae376faff`,name:`FlxSocialPost`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialPost($id: ID!) {
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
}`},us={id:`bad63e76be4a72da349259fd37da2b216637760e2043864da9ba72b10c722535`,name:`FlxSocialPostAct`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialPostAct($input: FlxSocialPostActInput!) {
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
}`},ds={id:`9198337975631125c80b4d3144ff8c95c87f78bcced0a1239a914dfec0fe9053`,name:`FlxSocialPostBulkAct`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialPostBulkAct($input: FlxSocialPostBulkActInput!) {
  flxSocialPostBulkAct(input: $input) {
    done
    issues
    errors {
      code
      message
      field
    }
  }
}`},fs={id:`6fbb3d188ae4f3ac1afb8bdb803de5afa89ad9d81f4597a97c0c5bf752c43cfc`,name:`FlxSocialPostCreate`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialPostCreate($input: FlxSocialPostCreateInput!) {
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
}`},ps={id:`501365bb59297864329d808e41b73469e0fca66f789c2838584ee8464787ae48`,name:`FlxSocialPostDelete`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialPostDelete($input: FlxSocialPostDeleteInput!) {
  flxSocialPostDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},ms={id:`4043b0e0cad9ce1b24b08e084041e854742976d792fce3aed127b5e39b3d7076`,name:`FlxSocialPostDuplicate`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialPostDuplicate($input: FlxSocialPostDuplicateInput!) {
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
}`},hs={id:`96bfd9683efd78e4c882e801d09d73f2d9600d382fa9c094fc0697b135507705`,name:`FlxSocialPosts`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialPosts($first: Int, $after: String, $status: [FlxSocialPostStatus!], $accountId: Int, $provider: String, $search: String, $tag: String, $mine: Boolean, $from: Int, $to: Int, $ascending: Boolean) {
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
}`},gs={id:`b1d81348826f953c3c5c4c13a46de500f7862d6edb2e50b5d892b2b4b9f85d07`,name:`FlxSocialPostUpdate`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialPostUpdate($input: FlxSocialPostUpdateInput!) {
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
}`},_s={id:`6a244ceffd24ffbaa730873fc84b76c1943f19b81f949f48ee591584d5450a38`,name:`FlxSocialSettings`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialSettings {
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
}`},vs={id:`dc8548114e3ee4319fb5256091aa79de47ea2bf608ebc84792773869110d7c11`,name:`FlxSocialSettingsUpdate`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialSettingsUpdate($input: FlxSocialSettingsUpdateInput!) {
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
}`},ys={id:`f751908be074aa0bf4d1dd045535dd240d0474b9eb994dc13d20449a847acf40`,name:`FlxSocialStats`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialStats($days: Int) {
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
}`},bs={id:`cec3bd8f7f67ca6ade378ae4c96239c14fb729e6acb36deaae2f619716abbff3`,name:`FlxSocialStatsRefresh`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialStatsRefresh($input: FlxSocialStatsRefreshInput!) {
  flxSocialStatsRefresh(input: $input) {
    updated
    errors {
      code
      message
      field
    }
  }
}`},xs={id:`66b1e332cd2d0ad55c6d27fe7d60d1b50aadd3cbaa44a561e8029892cd063639`,name:`FlxSocialStudioMedia`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialStudioMedia {
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
}`},Ss={id:`b7859e5459cd00b1b2e64810c3eaf209d3a9773569d2667851f5f82371cf11f1`,name:`FlxSocialTargetRemoteDelete`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialTargetRemoteDelete($input: FlxSocialTargetRemoteDeleteInput!) {
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
}`},Cs={id:`5badf33fcf805a44c7c661263f28a611ea7d53e8fd03fecfad7829a2c4c916b6`,name:`FlxSocialTargetRemoteEdit`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialTargetRemoteEdit($input: FlxSocialTargetRemoteEditInput!) {
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
}`},ws={id:`c28e9e66d529c5b571ac1c2184279c81c13ac6e28ed1d93b5bdafa8872a1354f`,name:`FlxSocialTargetRetry`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialTargetRetry($input: FlxSocialTargetRetryInput!) {
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
}`},Ts={id:`f8d604a8bbf847a49e9b83a886cc1f5c1f6bc02be42716dd0ad361dacdfdd65f`,name:`FlxSocialTickKeyReveal`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialTickKeyReveal($input: FlxSocialTickKeyRevealInput!) {
  flxSocialTickKeyReveal(input: $input) {
    url
    errors {
      code
      message
      field
    }
  }
}`},Es={id:`df8b65ba6af7222044b383bcd58eb659a7dbcfc1aef6943b475750d6f51562a2`,name:`FlxSocialTickRun`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialTickRun($input: FlxSocialTickRunInput!) {
  flxSocialTickRun(input: $input) {
    processed
    due
    errors {
      code
      message
      field
    }
  }
}`},Ds={id:`1431f1fc9a3b21f5302c2d3cb267ddc62497d15d14a8c8ea57ce7cb8070d5cd3`,name:`FlxSocialWhatsAppAdd`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialWhatsAppAdd($input: FlxSocialWhatsAppAddInput!) {
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
}`},Os={id:`770bd0bc66b661e8dbf40c7acceacf1953312994effd37432fcfc1edf5ed5bf4`,name:`FlxSocialWhatsAppTargets`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialWhatsAppTargets {
  flxSocialWhatsAppTargets {
    extId
    name
    kind
    avatar
    exists
  }
}`},ks={id:`c0c034497a50d8f207d50ee8d53dee71fb810f0dfd57c6163345be91a32f0a5b`,name:`FlxSpeedAutoload`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedAutoload {
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
}`},As={id:`8d270a918a41d495c99f9871ce13a133eade0ce31328d7c2bd4123e82cb5e2c6`,name:`FlxSpeedCache`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedCache {
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
}`},js={id:`d977b224915686fef097ececbb92793cf30eced524668174faa48191478ee08f`,name:`FlxSpeedCacheRun`,kind:`mutation`,plugin:`flx-speed`,document:`mutation FlxSpeedCacheRun($input: FlxSpeedCacheRunInput!) {
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
}`},Ms={id:`fcd076afcbd146a9e6ca574ba597984e1637eee1b8f35aa9b512e428916aaa43`,name:`FlxSpeedCleanup`,kind:`mutation`,plugin:`flx-speed`,document:`mutation FlxSpeedCleanup($input: FlxSpeedCleanupInput!) {
  flxSpeedCleanup(input: $input) {
    done
    errors {
      code
      message
      field
    }
  }
}`},Ns={id:`48d2ae9c4348a0b4300b2fc4b0254b07472858e0e5f65eaddd4996ce4801bb4d`,name:`FlxSpeedConfig`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedConfig {
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
}`},Ps={id:`58ed686173cd507ef595683e7cbf4b30ed514b82612dd1caa4e75a2199f885f0`,name:`FlxSpeedCron`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedCron {
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
}`},Fs={id:`8de800753b00b5e90b240028948dd2ee2a99c1c6e89ef22b03eae970f15f2a3f`,name:`FlxSpeedDatabase`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedDatabase {
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
}`},Is={id:`7d247ff1bb9def8192c868f601c45a17feed2dfb10ea254afa0b785be069a8c8`,name:`FlxSpeedLive`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedLive {
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
}`},Ls={id:`f053efbf67cf7f284fd5c1efd1a0157d89c3beedf57981dde2a693e1b6567f7f`,name:`FlxSpeedPageSpeed`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedPageSpeed {
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
}`},Rs={id:`b375ed8680d809378b5e9616f1f1bc440c2e912041b84d39d6df690eb4de4c09`,name:`FlxSpeedPageSpeedClear`,kind:`mutation`,plugin:`flx-speed`,document:`mutation FlxSpeedPageSpeedClear {
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
}`},zs={id:`e4e117cb961c1485e8fd5b4c322f34231ddec9056751ec79317e99985fca16bc`,name:`FlxSpeedPageSpeedSave`,kind:`mutation`,plugin:`flx-speed`,document:`mutation FlxSpeedPageSpeedSave($input: FlxSpeedPageSpeedSaveInput!) {
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
}`},Bs={id:`7dbefa1a379a4febe1f2eb7c39c6f399f2bfa417df3cd57c8cf0ede758a3b3c7`,name:`FlxSpeedProfile`,kind:`mutation`,plugin:`flx-speed`,document:`mutation FlxSpeedProfile($input: FlxSpeedProfileInput!) {
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
}`},Vs={id:`d43528eacd33ad43a20ded425950c72cfe41d37466f94418bbb5a91d9bd39bd1`,name:`FlxSpeedRecord`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedRecord($id: Int!) {
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
}`},Hs={id:`b6692580ef2fbfa27c06265ef8f344b27cc749cd14f6cfb2725953194ca7909a`,name:`FlxSpeedRecords`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedRecords($first: Int = 25, $after: String, $hours: Int, $flag: String, $type: String, $q: String, $slow: Boolean) {
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
}`},Us={id:`845b54851a74fba07a12deeefeb2c7b71f6ecfaaa759daa50c6041a482dddb25`,name:`FlxSpeedRecordsClear`,kind:`mutation`,plugin:`flx-speed`,document:`mutation FlxSpeedRecordsClear {
  flxSpeedRecordsClear(input: {  }) {
    cleared
    errors {
      code
      message
      field
    }
  }
}`},Ws={id:`9234ad49b162d1b924b722bc341baff844c5c5fe32121c5f3c604fc0d018b89a`,name:`FlxSpeedServer`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedServer {
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
}`},Gs={id:`3ed06bef7eb6f4c9e11545d09afef2ce9ff9cbc1fd38115124ff2b649e3d630f`,name:`FlxSpeedSettings`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedSettings {
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
}`},Ks={id:`ae1ea2b03f50ac55abebc5ae0a507d77e463e14edae360feaf1646ebd2ddaac4`,name:`FlxSpeedSettingsSave`,kind:`mutation`,plugin:`flx-speed`,document:`mutation FlxSpeedSettingsSave($input: FlxSpeedSettingsSaveInput!) {
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
}`},qs={id:`29e3951ab3ac534d8bc6a7abc8e6aaa037842a052d95472eddaa6c714ad66e7e`,name:`FlxSpeedSummary`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedSummary($hours: Int = 24) {
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
}`},Js={id:`a6ad626fabff2f9904a2f88aec77a49b4dfd900c575ea7fd357b0234aca73d82`,name:`FlxSpeedSystem`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedSystem {
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
}`},Ys={id:`7e536f64c2f2071ee080da4c303e3ff3df331d4ee5454e2d32e6ca2cfa558491`,name:`FlxStudioBrainstorm`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioBrainstorm($input: FlxStudioBrainstormInput!) {
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
}`},Xs={id:`164e3eaaab653626f4560a3b3c33be0334bea85986ca1777daa2d7b6bd689aab`,name:`FlxStudioCancel`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioCancel($input: FlxStudioCancelInput!) {
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
}`},Zs={id:`412a1cf0eb420e33030acdc395810cb1317af6d7530bddff047baab4845ff908`,name:`FlxStudioEnhance`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioEnhance($input: FlxStudioEnhanceInput!) {
  flxStudioEnhance(input: $input) {
    prompt
    errors {
      code
      message
      field
    }
  }
}`},Qs={id:`93c9aa7f8a19fc0358f9c0f5fce98107b3cb6406d5c75a3e23d35bafc2030270`,name:`FlxStudioFolderCreate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioFolderCreate($input: FlxStudioFolderCreateInput!) {
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
}`},$s={id:`9ecd95b4c0e16c01a049c04b82f79013ad067a1dd99cd5a8186be641a6f36288`,name:`FlxStudioFolderDelete`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioFolderDelete($input: FlxStudioFolderDeleteInput!) {
  flxStudioFolderDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},ec={id:`ac9f3ba4ecb20b0da721b057651d3f6d4670fade63fa567ffff21a55bbfa1e4f`,name:`FlxStudioFolderUpdate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioFolderUpdate($input: FlxStudioFolderUpdateInput!) {
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
}`},tc={id:`9bc13e2c994ded586d4eee73ff381a8d1e21c82a079689d5e02053485353496a`,name:`FlxStudioGenerate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioGenerate($input: FlxStudioGenerateInput!) {
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
}`},nc={id:`639f7d0675693c31cb95b48aabe5b674671e4f6df5db262ee98e9354c4f2941f`,name:`FlxStudioItems`,kind:`query`,plugin:`flx-studio`,document:`query FlxStudioItems($first: Int = 60, $after: String, $folderId: Int, $kind: FlxStudioKind, $favorite: Boolean, $batch: String, $search: String, $allOwners: Boolean) {
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
}`},rc={id:`5c12139fff325a5d541ffe31a43bbdab23d6b3e372fff9323d164547a5d0c353`,name:`FlxStudioItemsDelete`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioItemsDelete($input: FlxStudioItemsDeleteInput!) {
  flxStudioItemsDelete(input: $input) {
    deletedIds
    errors {
      code
      message
      field
    }
  }
}`},ic={id:`ed4c508fa39d29a80a706703e8aecf3b0830064acc79dc3824204f955ee94ae1`,name:`FlxStudioItemsMove`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioItemsMove($input: FlxStudioItemsMoveInput!) {
  flxStudioItemsMove(input: $input) {
    movedIds
    errors {
      code
      message
      field
    }
  }
}`},ac={id:`b6814f6729f348c9e0b952a2b4487db6976a118af605e0a4a6f300163c33332e`,name:`FlxStudioItemToLibrary`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioItemToLibrary($input: FlxStudioItemToLibraryInput!) {
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
}`},oc={id:`82ed1fcbf3ca9509636cc0cc2917dc945f9c446c42ee0461571e8e6826727144`,name:`FlxStudioItemUpdate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioItemUpdate($input: FlxStudioItemUpdateInput!) {
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
}`},sc={id:`df8d9048b9a909150a5b758644ccfaae13f920a46ff85df0a5de0a5edef196d5`,name:`FlxStudioPersonCreate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioPersonCreate($input: FlxStudioPersonCreateInput!) {
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
}`},cc={id:`715fd6d84c8d95994b9cf336dfa63288931d4f28adc31c3b5e0074cba19aa55c`,name:`FlxStudioPersonDelete`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioPersonDelete($input: FlxStudioPersonDeleteInput!) {
  flxStudioPersonDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},lc={id:`b929a0c4365da430782991c15105207e5722bfaa9c23583e7535c6a823c9b8a8`,name:`FlxStudioPersonRename`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioPersonRename($input: FlxStudioPersonRenameInput!) {
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
}`},uc={id:`8592ab98d0e723bac97426d1c9853126e94192f67de8787c77cf8a65a5707321`,name:`FlxStudioPoll`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioPoll($input: FlxStudioPollInput!) {
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
}`},dc={id:`076f0f83b765ad61ba0d1034777af88df4d15742683110a1390fc3320e815248`,name:`FlxStudioRefCreate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioRefCreate($input: FlxStudioRefCreateInput!) {
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
}`},fc={id:`4cc7a8b993dd67b840279ee0ecdb4ad0059d3de9fb975053551e0907c9b35778`,name:`FlxStudioRefDelete`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioRefDelete($input: FlxStudioRefDeleteInput!) {
  flxStudioRefDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},pc={id:`7bcbc27e22f195e5ce14ab38eedd554807ce1e4f5d27c4bd6837a7f823fed355`,name:`FlxStudioRefUpdate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioRefUpdate($input: FlxStudioRefUpdateInput!) {
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
}`},mc={id:`5658cd768af0cfcae70f66f69eab5a1f47657f446c922a166dcb4249182de2e6`,name:`FlxStudioSettingsUpdate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioSettingsUpdate($input: FlxStudioSettingsUpdateInput!) {
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
}`},hc={id:`2b2f30d9b3b3929647767a5232da353836f4ffcc9bab3945162b4237ad8aa50a`,name:`FlxStudioStats`,kind:`query`,plugin:`flx-studio`,document:`query FlxStudioStats($days: Int = 30) {
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
}`},gc={id:`f3d78587f86d7f71592a658e92d7281ccb4626ba12a84ac511a444c7cd8df6ba`,name:`FlxStudioTemplateCreate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioTemplateCreate($input: FlxStudioTemplateCreateInput!) {
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
}`},_c={id:`ddb8a4be1c12b292124e41747f467cc316dbb805cd3949d366715dabf5be628e`,name:`FlxStudioTemplateDelete`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioTemplateDelete($input: FlxStudioTemplateDeleteInput!) {
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
}`},vc={id:`2bacebe6d3f0fa55575f79f7abb0d22d42c7def0e3bf004c11657ff248dd3bcc`,name:`FlxStudioWorkspace`,kind:`query`,plugin:`flx-studio`,document:`query FlxStudioWorkspace {
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
}`},yc={id:`e2807f6687f4281ce55d3071721c422d538fb19b31651bc7b8ac85363121cab5`,name:`FlxSubscriptionCancel`,kind:`mutation`,plugin:`flx-donate`,document:`mutation FlxSubscriptionCancel($id: Int!) {
  flxSubscriptionCancel(input: { id: $id }) {
    message
    errors {
      message
      field
    }
  }
}`},bc={id:`fca51d9372f393736cd5c7f3cc3a6c6f76bc42c714907f05360f59ce43283d8b`,name:`FlxTaskCommentCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCommentCreate($input: FlxTaskCommentCreateInput!) {
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
}`},xc={id:`f4712b3032ffaf4580393cf0e6234154fa1eb66399b12784aa29f29b625d34bd`,name:`FlxTaskCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCreate($input: FlxTaskCreateInput!) {
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
}`},Sc={id:`157053946bdf55aa4bf5f22a676119ec48e7bbe04c053867b7e0eedb11e04ec2`,name:`FlxTaskDelete`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskDelete($input: FlxTaskDeleteInput!) {
  flxTaskDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},Cc={id:`fa1d3f71ca31ab2a7b72c1a987e295f209d6564e8a68e3643dee354a44544ecf`,name:`FlxTaskDetail`,kind:`query`,plugin:`flx-tasks`,document:`query FlxTaskDetail($id: ID!) {
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
}`},wc={id:`d357c8fdb6c372508b17bb93fa22ec80224c0ff43854104149816d70a9e80870`,name:`FlxTaskNotificationsRead`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskNotificationsRead($input: FlxTaskNotificationsReadInput!) {
  flxTaskNotificationsRead(input: $input) {
    unread
    errors {
      code
      message
      field
    }
  }
}`},Tc={id:`004e85b9237b995957f75c2cfe2a996f5d2de6ca98b31c98511cb403df800ae3`,name:`FlxTaskUpdate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdate($input: FlxTaskUpdateInput!) {
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
}`},Ec={id:`7c644e53f296f0dbf859b7bfcb4748e8c5be3ef891474fb47a262a2cd993be7a`,name:`FlxTaskUpdateDetail`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdateDetail($input: FlxTaskUpdateInput!) {
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
}`},Dc={id:`a2c919c298da71f1634726a7c85c4344cf85f3602cbf54e4175bd8e45578e9b5`,name:`FlxTaskWatch`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskWatch($input: FlxTaskWatchInput!) {
  flxTaskWatch(input: $input) {
    watcherIds
    errors {
      code
      message
      field
    }
  }
}`},Oc={id:`82a77154cc559fdb176a8880985bf3a64706b404515780458ee98ec4b66f3870`,name:`FlxThemeMigrate`,kind:`mutation`,plugin:`fluxwp`,document:`mutation FlxThemeMigrate($input: FlxThemeMigrateInput!) {
  flxThemeMigrate(input: $input) {
    log
    errors {
      code
      message
      field
    }
  }
}`},kc={id:`fc5031368340a38a66f0982b81b94cbed677d41d75d84f75da83d39cbf71af4e`,name:`FlxThemeMigrationSources`,kind:`query`,plugin:`fluxwp`,document:`query FlxThemeMigrationSources {
  flxThemeMigrationSources {
    stylesheet
    lastRun {
      label
    }
  }
}`},Ac={id:`3ed6b0ac119ee2d69851b315aa6bf0e730f23ff9b8a1b58d8df96549912a99fb`,name:`FlxThemePayLogosSave`,kind:`mutation`,plugin:`fluxwp`,document:`mutation FlxThemePayLogosSave($input: FlxThemePayLogosSaveInput!) {
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
}`},jc={id:`60f97b392adcc80d2ba7b3dcb7031f8bfe5f4b2daf5323f35c7bde9e0b321ab6`,name:`FlxThemeSettings`,kind:`query`,plugin:`fluxwp`,document:`query FlxThemeSettings {
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
  authStyle
  authLogo
  authTitle
  authTitleDefault
  authText
  authImage
  authImageUrl
  authBadge
  authPanelTitle
  authPanelText
  campaigns {
    databaseId
    title
  }
}`},Mc={id:`6640eee1f357ca94b5ffe773207cecd85fc4f02a85b366c790e0d514136a4783`,name:`FlxThemeSettingsSave`,kind:`mutation`,plugin:`fluxwp`,document:`mutation FlxThemeSettingsSave($input: FlxThemeSettingsSaveInput!) {
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
  authStyle
  authLogo
  authTitle
  authTitleDefault
  authText
  authImage
  authImageUrl
  authBadge
  authPanelTitle
  authPanelText
  campaigns {
    databaseId
    title
  }
}`},Nc={id:`2319101e3a871af11052c6fe8dcae1de118834cc9f13b232bd573001daca537c`,name:`FlxThemeSystem`,kind:`query`,plugin:`fluxwp`,document:`query FlxThemeSystem {
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
}`},Pc={id:`3b302e1658cbd0be2df949e041acd18026dfe171eb43cf850e998a3590b1eab6`,name:`FlxViewer`,kind:`query`,plugin:`flx-graphql`,document:`query FlxViewer {
  flxViewer {
    loggedIn
    databaseId
    name
    isAdmin
    capabilities
  }
}`},Fc={id:`a722ff87a9ced30ef1b3cd860906049dfb55ce9de492e09647f12fa6702e6897`,name:`FlxWikiGuidesImport`,kind:`mutation`,plugin:`flx-wiki`,document:`mutation FlxWikiGuidesImport {
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
}`},Ic={id:`d2e33bfc675984497dcf47d59db70aa05a8a9704a6c16ab10dc7b460f4253382`,name:`FlxWikiPageEdit`,kind:`query`,plugin:`flx-wiki`,document:`query FlxWikiPageEdit($id: ID!) {
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
}`},Lc={id:`438ecf2d6813eff8606ac244eed7b25027e8df9f8126c21e56be54b6f7342f95`,name:`FlxWikiPages`,kind:`query`,plugin:`flx-wiki`,document:`query FlxWikiPages($first: Int = 20, $after: String, $search: String, $statuses: [FlxWikiPageStatus!], $section: String) {
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
}`},Rc={id:`1663d5b4943b6f754178ebe061036a5405310c8c28eb1b12fe49d3975da909af`,name:`FlxWikiPageSave`,kind:`mutation`,plugin:`flx-wiki`,document:`mutation FlxWikiPageSave($input: FlxWikiPageSaveInput!) {
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
}`},zc={id:`45ad148e9015ca73a0a7a6b29bdfcd06d601810ba382f318ba7ca618f3bc781b`,name:`FlxWikiPageTrash`,kind:`mutation`,plugin:`flx-wiki`,document:`mutation FlxWikiPageTrash($input: FlxWikiPageTrashInput!) {
  flxWikiPageTrash(input: $input) {
    trashedId
    errors {
      code
      message
      field
    }
  }
}`},Bc={id:`0b8b7593708daa39f4935d849e0dcbba9c50bc5f2ebd1400ac7c45646f80a0ac`,name:`FlxWikiPreview`,kind:`mutation`,plugin:`flx-wiki`,document:`mutation FlxWikiPreview($input: FlxWikiPreviewInput!) {
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
}`},Vc={id:`a81958793c487ce071f1c2c4f7f0bd8dc214be127573eb1b85123934c08cebbd`,name:`FlxWikiScreen`,kind:`query`,plugin:`flx-wiki`,document:`query FlxWikiScreen {
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
}`},Hc={id:`e960c92aac1edd40038d31da2506df2a0ac83b057a92d2fd3cae3af316118156`,name:`FlxWikiSectionDelete`,kind:`mutation`,plugin:`flx-wiki`,document:`mutation FlxWikiSectionDelete($input: FlxWikiSectionDeleteInput!) {
  flxWikiSectionDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},Uc={id:`8f83641d5dd64bd14dedc7cb6097a02bdc1daa0e13c7b34dfda0f50d8cefc258`,name:`FlxWikiSectionSave`,kind:`mutation`,plugin:`flx-wiki`,document:`mutation FlxWikiSectionSave($input: FlxWikiSectionSaveInput!) {
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
}`},Wc={id:`e95a30e9de1775e201bc99018b23d52eb77faed64ff768b865e5ad647509110f`,name:`FlxWikiSettingsSave`,kind:`mutation`,plugin:`flx-wiki`,document:`mutation FlxWikiSettingsSave($input: FlxSettingsUpdateInput!) {
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
}`},Gc={id:`69a3c84c1667538d92545f46e1c9ca1c50bee5fbb3154e584c804edbfb5b839e`,name:`FlxWikiTree`,kind:`query`,plugin:`flx-wiki`,document:`query FlxWikiTree {
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
}`};export{Q as $,ea as $a,ec as $c,$r as $i,Qt as $n,Qa as $o,$n as $r,es as $s,Qe as $t,k as A,Ai as Aa,js as Ac,jr as Ai,jc as Al,kt as An,Aa as Ao,zn as Ar,jo as As,ke as At,z as B,Vi as Ba,Vs as Bc,Rr as Bi,Vc as Bl,zt as Bn,Ba as Bo,Sn as Br,Vo as Bs,Ie as Bt,S as C,Ci as Ca,ws as Cc,Cr as Ci,wc as Cl,St as Cn,Ca as Co,Mn as Cr,wo as Cs,xe as Ct,E as D,Di as Da,Os as Dc,Dr as Di,Oc as Dl,Et as Dn,Da as Do,In as Dr,Oo as Ds,Ee as Dt,T as E,Ei as Ea,Ds as Ec,Er as Ei,Dc as El,Tt as En,Ea as Eo,Fn as Er,Do as Es,Te as Et,P as F,Fi as Fa,Is as Fc,Ar as Fi,Ic as Fl,Pt as Fn,Fa as Fo,Wn as Fr,Io as Fs,Pe as Ft,W as G,Ki as Ga,Ks as Gc,Gr as Gi,Wt as Gn,Ga as Go,Dn as Gr,Ko as Gs,We as Gt,V as H,Ui as Ha,Us as Hc,Hr as Hi,Uc as Hl,Vt as Hn,Ha as Ho,wn as Hr,Uo as Hs,He as Ht,F as I,Ii as Ia,Ls as Ic,Ir as Ii,Rc as Il,Ft as In,Ia as Io,Gn as Ir,Lo as Is,Fe as It,q as J,Yi as Ja,Ys as Jc,Jr as Ji,qt as Jn,Ja as Jo,Jn as Jr,Yo as Js,Je as Jt,G as K,qi as Ka,qs as Kc,Kr as Ki,Gt as Kn,Ka as Ko,kn as Kr,qo as Ks,Ge as Kt,I as L,Li as La,Rs as Lc,Lr as Li,zc as Ll,It as Ln,La as Lo,Kn as Lr,Ro as Ls,Le as Lt,j as M,Mi as Ma,Ns as Mc,Nr as Mi,Nc as Ml,jt as Mn,Ma as Mo,Bn as Mr,No as Ms,je as Mt,M as N,Ni as Na,Ps as Nc,Pr as Ni,Pc as Nl,Mt as Nn,Na as No,Hn as Nr,Po as Ns,Me as Nt,D as O,Oi as Oa,ks as Oc,Or as Oi,kc as Ol,Dt as On,Oa as Oo,Ln as Or,ko as Os,De as Ot,N as P,Pi as Pa,Fs as Pc,Fr as Pi,Fc as Pl,Nt as Pn,Pa as Po,Un as Pr,Fo as Ps,Ne as Pt,Z as Q,$i as Qa,$s as Qc,Qr as Qi,Zt as Qn,$a as Qo,Qn as Qr,$o as Qs,Ze as Qt,L as R,Ri as Ra,zs as Rc,zr as Ri,Lc as Rl,Lt as Rn,Ra as Ro,qn as Rr,zo as Rs,ze as Rt,x as S,Si as Sa,Cs as Sc,Sr as Si,Cc as Sl,xt as Sn,Sa as So,jn as Sr,Co as Ss,Se as St,w as T,Ti as Ta,Es as Tc,Tr as Ti,Ec as Tl,wt as Tn,Ta as To,Pn as Tr,Eo as Ts,we as Tt,H as U,Wi as Ua,Ws as Uc,Ur as Ui,Wc as Ul,Ht as Un,Ua as Uo,Tn as Ur,Wo as Us,Ue as Ut,B as V,Hi as Va,Hs as Vc,Vr as Vi,Hc as Vl,Bt as Vn,Va as Vo,Cn as Vr,Ho as Vs,Re as Vt,U as W,Gi as Wa,Gs as Wc,Wr as Wi,Gc as Wl,Ut as Wn,Wa as Wo,En as Wr,Go as Ws,Ve as Wt,Y as X,Zi as Xa,Zs as Xc,Xr as Xi,Yt as Xn,Xa as Xo,Xn as Xr,Zo as Xs,qe as Xt,J as Y,Xi as Ya,Xs as Yc,Yr as Yi,Jt as Yn,Ya as Yo,Yn as Yr,Xo as Ys,Ye as Yt,X as Z,Qi as Za,Qs as Zc,Zr as Zi,Xt as Zn,Za as Zo,Zn as Zr,Qo as Zs,Xe as Zt,g as _,_i as _a,vs as _c,_r as _i,vc as _l,gt as _n,_a as _o,_n as _r,vo as _s,ge as _t,i as a,ai as aa,os as ac,ar as ai,ic as al,it as an,aa as ao,an as ar,ro as as,ie as at,y as b,bi as ba,xs as bc,yr as bi,xc as bl,yt as bn,ba as bo,bn as br,xo as bs,ye as bt,s as c,li as ca,ls as cc,cr as ci,lc as cl,st as cn,ca as co,cn as cr,co as cs,se as ct,d,di as da,fs as dc,dr as di,fc as dl,ut as dn,da as do,dn as dr,fo as ds,de as dt,ei as ea,ts as ec,er as ei,tc as el,$e as en,ta as eo,$t as er,eo as es,$ as et,u as f,fi as fa,ps as fc,fr as fi,pc as fl,ft as fn,fa as fo,fn as fr,po as fs,fe as ft,m as g,gi as ga,_s as gc,gr as gi,_c as gl,ht as gn,ga as go,gn as gr,_o as gs,he as gt,h,hi as ha,hs as hc,hr as hi,gc as hl,mt as hn,ha as ho,hn as hr,go as hs,me as ht,r as i,ii as ia,as as ic,ir as ii,rc as il,rt as in,Bi as io,rn as ir,ao as is,re as it,A as j,ji as ja,Ms as jc,Mr as ji,Mc as jl,At as jn,ja as jo,Vn as jr,Mo as js,Ae as jt,O as k,ki as ka,As as kc,kr as ki,Ac as kl,Ot as kn,ka as ko,Rn as kr,Ao as ks,Oe as kt,c as l,ci as la,us as lc,lr as li,uc as ll,ct as ln,la as lo,ln as lr,lo as ls,ce as lt,p as m,mi as ma,gs as mc,mr as mi,hc as ml,pt as mn,ma as mo,mn as mr,ho as ms,ue as mt,t as n,ni as na,rs as nc,nr as ni,oc as nl,tt as nn,ra as no,tn as nr,no as ns,te as nt,a as o,oi as oa,ss as oc,or as oi,sc as ol,at as on,oa as oo,on as or,oo as os,oe as ot,f as p,pi as pa,ms as pc,pr as pi,mc as pl,dt as pn,pa as po,pn as pr,mo as ps,pe as pt,K as q,Ji as qa,Js as qc,qr as qi,Kt as qn,qa as qo,An as qr,Jo as qs,Ke as qt,n as r,ri as ra,is as rc,rr as ri,nc as rl,nt as rn,ia as ro,nn as rr,io as rs,ne as rt,o as s,si as sa,cs as sc,sr as si,cc as sl,ot as sn,sa as so,sn as sr,so as ss,ae as st,e as t,ti as ta,ns as tc,tr as ti,ac as tl,et as tn,na as to,en as tr,to as ts,ee as tt,l as u,ui as ua,ds as uc,ur as ui,dc as ul,lt as un,ua as uo,un as ur,uo as us,le as ut,_ as v,vi as va,ys as vc,vr as vi,yc as vl,_t as vn,va as vo,vn as vr,yo as vs,_e as vt,C as w,wi as wa,Ts as wc,wr as wi,Tc as wl,Ct as wn,wa as wo,Nn as wr,To as ws,Ce as wt,b as x,xi as xa,Ss as xc,xr as xi,Sc as xl,bt as xn,xa as xo,On as xr,So as xs,be as xt,v as y,yi as ya,bs as yc,br as yi,bc as yl,vt as yn,ya as yo,yn as yr,bo as ys,ve as yt,R as z,zi as za,Bs as zc,Br as zi,Bc as zl,Rt as zn,za as zo,xn as zr,Bo as zs,Be as zt};