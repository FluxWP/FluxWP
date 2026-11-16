import{J as e,K as t}from"./flux-host-ChJJuh98.js";var n={id:`38c66e1db90a0c7f811614cb50f0f82d679a21ff93ebe0cbf3b021b9e5a9f7af`,name:`FlxAnalyticsAggregate`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsAggregate($input: FlxAnalyticsAggregateInput!) {
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
}`},r={id:`6b342c00c7aeb5b90485e9ccd04debf159a54692615072abccfe121ae406f79f`,name:`FlxAnalyticsBreakdown`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsBreakdown($filter: FlxAnalyticsFilterInput, $kind: FlxAnalyticsDimKind!, $first: Int, $after: String) {
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
}`},i={id:`2023f590bac125700fa5bcb6ddf42b8117fe52804f68d372a0a34d4868d6832b`,name:`FlxAnalyticsConfig`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsConfig {
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
}`},a={id:`5576bd9bb02666b281e1c009983a7c270480a77cf90231fd8ba61016a7056037`,name:`FlxAnalyticsEvents`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsEvents($filter: FlxAnalyticsFilterInput, $name: String, $first: Int, $after: String) {
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
}`},o={id:`79d1dccd00bee3f2f3c4de1ed9fa6ff89fd2056b81518e5f7e92466bdbf3a388`,name:`FlxAnalyticsFastTest`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsFastTest($input: FlxAnalyticsFastTestInput!) {
  flxAnalyticsFastTest(input: $input) {
    method
    errors {
      code
      message
      field
    }
  }
}`},s={id:`ea6bd1bf4b9cbda61bd0c6e6053256c45c01af2682b85d64981868aa0b5d82f8`,name:`FlxAnalyticsFunnel`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsFunnel($filter: FlxAnalyticsFilterInput, $id: Int, $by: FlxAnalyticsFunnelBy) {
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
}`},c={id:`712f94387c03d252e5e19ad5cc11d4f20cc35fa4b778aba666cae6a27dff36a5`,name:`FlxAnalyticsJourneys`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsJourneys($filter: FlxAnalyticsFilterInput, $options: FlxAnalyticsJourneysInput) {
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
}`},l={id:`e7a13b823796a603132470f9f248230c22cedfd4d2391e05e1a1a6626ecbd35a`,name:`FlxAnalyticsKokoImport`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsKokoImport($input: FlxAnalyticsKokoImportInput!) {
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
}`},u={id:`8d1c4f7574619f7eb39e4b908cdd7ff3e11a3b13bc255f0e2b3f8efd83489839`,name:`FlxAnalyticsPage`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsPage($filter: FlxAnalyticsFilterInput, $target: String!) {
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
}`},ee={id:`ebbac7ddcb08b32fb8b2c0eb5b67c80284fa09be3165b30eebeae0a282cee423`,name:`FlxAnalyticsPages`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsPages($filter: FlxAnalyticsFilterInput, $q: String, $only404: Boolean, $first: Int, $after: String) {
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
}`},te={id:`db042cdd9f459d72081431f7df6d8a9e1081c5230eef59de2e263c30dcb9d698`,name:`FlxAnalyticsRealtime`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsRealtime($filter: FlxAnalyticsFilterInput, $minutes: Int, $kpi: Boolean) {
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
}`},ne={id:`3c8464c3ca4e71ec015a9a16728f8a3696bdbd22a4e4eecc19312c893c3d4db1`,name:`FlxAnalyticsReferrers`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsReferrers($filter: FlxAnalyticsFilterInput, $q: String, $first: Int, $after: String) {
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
}`},d={id:`59ee013d5260056c879e17dc79e67240198450d0cb156e6425159922a2d37744`,name:`FlxAnalyticsRefSave`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsRefSave($input: FlxAnalyticsRefSaveInput!) {
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
}`},f={id:`8379b10f9853966238a2d54a9b2b4dd22175b692573038145ee03bf5b3501f45`,name:`FlxAnalyticsReport`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsReport($filter: FlxAnalyticsFilterInput) {
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
}`},p={id:`ce33713a41cfcf183974cb43003436209c4bedc460a35228a0fd27982819f466`,name:`FlxAnalyticsReset`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsReset($input: FlxAnalyticsResetInput!) {
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
}`},m={id:`129cfba2b194904d644f1f6c1a89065b2c3534dd0982d6aa88d30aa855920c9f`,name:`FlxAnalyticsSettings`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsSettings {
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
}`},h={id:`fcf4e0148b6d01ec7f05e578011d35764659fdef2afb0d3cd5438e14707c4c8f`,name:`FlxAnalyticsSettingsSave`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsSettingsSave($input: FlxAnalyticsSettingsSaveInput!) {
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
}`},g={id:`eb5453fc995071cd78ac870527d7da1090a3441f298f6113f2af7cfe9fde45f4`,name:`FlxAnalyticsTelemetry`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsTelemetry($days: Int, $country: String) {
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
}`},_={id:`98c319fa61824c0c4714f4d86466d15123634d74291edb45f9001c7d7b6b7fbb`,name:`FlxAnalyticsTelemetryErrors`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsTelemetryErrors($days: Int, $country: String, $plugin: String, $type: String) {
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
}`},v={id:`d64ae79eb5491a540154bd58029d69fc0b5e212e8c46414279d4ca0eaa940e1d`,name:`FlxAnalyticsTelemetryForget`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsTelemetryForget($input: FlxAnalyticsTelemetryForgetInput!) {
  flxAnalyticsTelemetryForget(input: $input) {
    removed
    errors {
      code
      message
      field
    }
  }
}`},y={id:`1dd252522e2e7e352cbe31ebb7ef763661f7f52d407b0023f418e1365e0d5aa8`,name:`FlxAppAccess`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAccess($all: Boolean) {
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
}`},b={id:`0410fe39ca78d5cd046a033c326dd6bd357096aaab36212f8c0f44f9056de2c1`,name:`FlxAppAccessRevoke`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAccessRevoke($input: FlxAppAccessRevokeInput!) {
  flxAppAccessRevoke(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},x={id:`1860c58a8fdc3e9a90238fafaef5f1ecdc4bcf298ce323ef674dd35eefbe2e6c`,name:`FlxAppAdminAppearance`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminAppearance {
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
}`},S={id:`762de9d5115d73d8f01452c7b5f8a8ac15213df2102f3986d735018483a7670b`,name:`FlxAppAdminAppearanceSave`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminAppearanceSave($input: FlxAppAdminAppearanceSaveInput!) {
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
}`},C={id:`14ba5b9cfa34c05e5bef9a80d5087b56019d5b4c4a5a22ce7810d12dd6089f8c`,name:`FlxAppAdminClientCreate`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminClientCreate($input: FlxAppAdminClientCreateInput!) {
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
}`},w={id:`0acad378aa0aa39d0d878475c0e4c2c5e8a5a09045e3bd946641c722c6b0f44b`,name:`FlxAppAdminClientDelete`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminClientDelete($input: FlxAppAdminClientDeleteInput!) {
  flxAppAdminClientDelete(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},T={id:`0aa8eaf58f40a410644147b5f052aa2441f94a7c247af742f281bd5590f5b61f`,name:`FlxAppAdminClients`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminClients {
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
}`},E={id:`67b0edec2f4ef8fb57c5f796df9e32ec167f35dae7bec4196df4edcdff650a82`,name:`FlxAppAdminMenu`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminMenu {
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
}`},D={id:`e27de38feec02d07f97aa0a6cee1f3782bf39e4b41f0fd3cf84edb52d04a7d74`,name:`FlxAppAdminMobile`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminMobile {
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
}`},O={id:`3140882c7e64b03a33a9bdeaab1dc3b5eb7c50ba5709a2a95778672679795ae2`,name:`FlxAppAdminMobileSave`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminMobileSave($input: FlxAppAdminMobileSaveInput!) {
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
}`},k={id:`23953eab015437dc0f727a62ee3d73cb1e6cb45be22fc7f6b79e101318571d10`,name:`FlxAppAdminModules`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminModules {
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
}`},A={id:`a7aad76ad18dbda2567f29ad4d8b488efe4043659ae782a8d0f92eefba54349c`,name:`FlxAppAdminModulesSave`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminModulesSave($input: FlxAppAdminModulesSaveInput!) {
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
}`},j={id:`b9dddc6a4c12070645fd30a6fbdf39863d2b58ea1cbe71f7ebb5ee2db6bc9b3a`,name:`FlxAppAdminPushTest`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminPushTest {
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
}`},M={id:`b7d4bf6833511227a38948aef18cf38f05a29043d4dbfc3b04e49fcc1878ec06`,name:`FlxAppAdminSettings`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminSettings {
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
}`},N={id:`fc53aca192620408e9133dd653741e741a10c7d19ada18e38845f088b88e1788`,name:`FlxAppAdminSettingsSave`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminSettingsSave($input: FlxAppAdminSettingsSaveInput!) {
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
}`},P={id:`9f61478d3bc50857048da2499c97ed5de0bdc51362012d2153e2363175cb7d37`,name:`FlxAppDeviceRevoke`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppDeviceRevoke($input: FlxAppDeviceRevokeInput!) {
  flxAppDeviceRevoke(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},F={id:`49577b1d3b994160a8f4f89898086f3ffb70db6242a809e48b3515c7268d6e21`,name:`FlxAppKeyCreate`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppKeyCreate($input: FlxAppKeyCreateInput!) {
  flxAppKeyCreate(input: $input) {
    key
    errors {
      code
      message
      field
    }
  }
}`},I={id:`14ae18c34134e93d807ec1e3d21907a4c002555d4d327011a6cbdd05c13743ab`,name:`FlxLanguageSettingsScreen`,kind:`query`,plugin:`flx-lang`,document:`query FlxLanguageSettingsScreen {
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
}`},L={id:`0da4e1f193deec851174ea768e4699d956a94e7670b172e44dafe5bf9f788a52`,name:`FlxLanguageSettingsUpdate`,kind:`mutation`,plugin:`flx-lang`,document:`mutation FlxLanguageSettingsUpdate($input: FlxLanguageSettingsUpdateInput!) {
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
}`},R={id:`21a336969a0dc41b8caaa1be0a20b9bd740359347ff57bc8cb72f70ca6254f9e`,name:`FlxRoleCreate`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleCreate($input: FlxRoleCreateInput!) {
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
}`},z={id:`2bd5579ac1188eadb0dff5235840b66098c68b1b0d9e5d9a86c1a07b54d3acea`,name:`FlxRoleDelete`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleDelete($input: FlxRoleDeleteInput!) {
  flxRoleDelete(input: $input) {
    deletedKey
    errors {
      code
      message
      field
    }
  }
}`},B={id:`af09ea3f7594b6e37e1aa272f116e57dd7079ca275a754a8107fc230ce3014df`,name:`FlxRoleMemberAdd`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleMemberAdd($input: FlxRoleMemberAddInput!) {
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
}`},V={id:`6eab4d6f1f97457a8679a9b1dd92d47dc2e01a7f41163eff439dc854213b2a7c`,name:`FlxRoleMemberRemove`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleMemberRemove($input: FlxRoleMemberRemoveInput!) {
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
}`},H={id:`067ae709ce0675a0f8e8ab3ab7d21d722afa9f7449f358767be373133c98de6f`,name:`FlxRoleMembers`,kind:`query`,plugin:`flx-roles`,document:`query FlxRoleMembers($key: String!, $first: Int = 50, $after: String) {
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
}`},U={id:`20e6cdbb88f7639a8749e74cf75ef58a4ab704143f0cf0fa3474dbb2366cb1d0`,name:`FlxRoleReset`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleReset($input: FlxRoleResetInput!) {
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
}`},W={id:`0a4da13faad85546b92fab5b45716dab5b06e87791475a9dfaa4664888375658`,name:`FlxRolesSave`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRolesSave($input: FlxRolesSaveInput!) {
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
}`},G={id:`2c22565866bcebf0aaed510b75bec73444fc8257c8d2b327873e9c2963c59030`,name:`FlxRolesScreen`,kind:`query`,plugin:`flx-roles`,document:`query FlxRolesScreen($members: Int = 50) {
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
}`},K={id:`24ef9e0380385f71b2af51a8163f15a8362c7a02a3fee076051efdd289a4d7a4`,name:`FlxSeoAuditRun`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoAuditRun($input: FlxSeoAuditRunInput!) {
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
}`},q={id:`25585531a77819b9e9b681a85e767cb408d01dcab0ecb5e5e3717d30b20fb89e`,name:`FlxSeoAuditScreen`,kind:`query`,plugin:`flx-seo`,document:`query FlxSeoAuditScreen($history: Int = 30) {
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
}`},J={id:`97d49670dd4df105502f5013764c377eaadec82d4f58ba3d9d0f8d3a00566bd2`,name:`FlxSeoContentCheck`,kind:`query`,plugin:`flx-seo`,document:`query FlxSeoContentCheck($limit: Int = 300) {
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
}`},Y={id:`aca5c530650ff0a558a94968a069e5d1d183315910cf69556396462fb63d557a`,name:`FlxSeoFixApply`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoFixApply($input: FlxSeoFixApplyInput!) {
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
}`},X={id:`fa7f2e42636c7fd69983e7637be5625f01102cc336c76b7e751dfc6b25299aea`,name:`FlxSeoGuide`,kind:`query`,plugin:`flx-seo`,document:`query FlxSeoGuide($lang: FlxSeoGuideLang) {
  flxSeoGuide(lang: $lang) {
    lang
    markdown
    sections
  }
}`},re={id:`84dfec5a3b859e284998fc8124abae04e2fdd4419dac91ab6cb5971882673a9d`,name:`FlxSeoIndexNowSubmit`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoIndexNowSubmit($input: FlxSeoIndexNowSubmitInput!) {
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
}`},ie={id:`59e6780cadb6bc0d4dcecc09e482056c52aeb00879b5b1c00fa35049f815dea2`,name:`FlxSeoPsiKeySave`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoPsiKeySave($input: FlxSeoPsiKeySaveInput!) {
  flxSeoPsiKeySave(input: $input) {
    saved
    errors {
      code
      message
      field
    }
  }
}`},ae={id:`ffd79b34482e7fd6c534446f2a81d044658a4eea3fafffa2d65eb14c8b4101fd`,name:`FlxSeoSettingsSave`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoSettingsSave($flxseo: FlxSettingsFlxseoInput!) {
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
}`},oe={id:`86bc810d43babcb5cefcbb0d86511dc9661c0d5431662164e77220a7f6f5e3b8`,name:`FlxSeoSettingsScreen`,kind:`query`,plugin:`flx-seo`,document:`query FlxSeoSettingsScreen {
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
}`},se={id:`b8f91c52dab2869ea091bb25833ba93b1b5ba0eb62b369c3cff557466b3478ba`,name:`FlxSetupAutoUpdateSet`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupAutoUpdateSet($input: FlxSetupAutoUpdateSetInput!) {
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
}`},ce={id:`327a745eaebc60ab14f76d453be58e38f0399b212c0c15b87d2dbd1488a731c4`,name:`FlxSetupBackupsDelete`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupBackupsDelete($input: FlxSetupBackupsDeleteInput!) {
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
}`},le={id:`5c3dea411ce3bbfc57b8945d04dd11c857a00fe726bc27e95ea52175cd11ac3f`,name:`FlxSetupCheck`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupCheck {
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
}`},ue={id:`fea5c46697571e499c36f813537e124a23f33e2a3b616d09264af5ef3fec1d26`,name:`FlxSetupHistory`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupHistory($page: Int) {
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
}`},de={id:`6c9941eefcb59ba827d304f3336844f4a348ecb3f1b549a96d805b1c48412b6b`,name:`FlxSetupManage`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupManage($input: FlxSetupManageInput!) {
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
}`},fe={id:`1641f892cadf8664b2298b4af2847db998d4c838826ebc0f08572a5ea648e637`,name:`FlxSetupOverview`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupOverview($search: String) {
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
}`},pe={id:`e8e1935e414a5f61d5841b22119e7874da774b8e7905d19e76333469f34eff77`,name:`FlxSetupPackages`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupPackages($tab: FlxSetupPackageTab, $search: String, $onlyUpdates: Boolean, $first: Int = 200, $after: String) {
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
}`},me={id:`91629093982ae065468fb3f63497bc77ac4c8950e085938617acebb155eb692e`,name:`FlxSetupPlan`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupPlan($slugs: [String!], $wpKeys: [String!], $all: Boolean, $activate: Boolean) {
  flxSetupPlan(slugs: $slugs, wpKeys: $wpKeys, all: $all, activate: $activate) {
    kind
    slug
    id
    name
    why
    version
  }
}`},he={id:`20b2e2929728ec932fd886ce76780a3b35a35fc0045391ff82428916aa37984b`,name:`FlxSetupRetentionSave`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupRetentionSave($input: FlxSetupRetentionSaveInput!) {
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
}`},ge={id:`33e46578e9a32d1bff6a6ad7f8785580eb6208cbc4b806543fc964bf1a3048f7`,name:`FlxSetupSettingsSave`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupSettingsSave($input: FlxSetupSettingsSaveInput!) {
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
}`},_e={id:`d8622d9c1c248a1d4c9dbc1667856303277e0b4f7b03ae820895203ddc6aa9ee`,name:`FlxSetupSettingsScreen`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupSettingsScreen {
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
}`},ve={id:`f8f67e0723c3045d9ba05b6454009c8f931f5f3a6a25a5680ccb8e478d3b2f3e`,name:`FlxSetupSourceAdd`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupSourceAdd($input: FlxSetupSourceAddInput!) {
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
}`},ye={id:`758bf6b4e9d2b43b0a4a2613eb688c94c8b73f0dd94f8e6efafd99fb74d5aa06`,name:`FlxSetupSourceChange`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupSourceChange($input: FlxSetupSourceChangeInput!) {
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
}`},be={id:`562e895123bcf4698f2e0b64d5f322aeecdf71358e7d66ef3677e3ed3e190a89`,name:`FlxSetupStepRun`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupStepRun($input: FlxSetupStepRunInput!) {
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
}`},xe={id:`fca51d9372f393736cd5c7f3cc3a6c6f76bc42c714907f05360f59ce43283d8b`,name:`FlxTaskCommentCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCommentCreate($input: FlxTaskCommentCreateInput!) {
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
}`},Se={id:`f4712b3032ffaf4580393cf0e6234154fa1eb66399b12784aa29f29b625d34bd`,name:`FlxTaskCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCreate($input: FlxTaskCreateInput!) {
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
}`},Ce={id:`157053946bdf55aa4bf5f22a676119ec48e7bbe04c053867b7e0eedb11e04ec2`,name:`FlxTaskDelete`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskDelete($input: FlxTaskDeleteInput!) {
  flxTaskDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},we={id:`fa1d3f71ca31ab2a7b72c1a987e295f209d6564e8a68e3643dee354a44544ecf`,name:`FlxTaskDetail`,kind:`query`,plugin:`flx-tasks`,document:`query FlxTaskDetail($id: ID!) {
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
}`},Te={id:`d357c8fdb6c372508b17bb93fa22ec80224c0ff43854104149816d70a9e80870`,name:`FlxTaskNotificationsRead`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskNotificationsRead($input: FlxTaskNotificationsReadInput!) {
  flxTaskNotificationsRead(input: $input) {
    unread
    errors {
      code
      message
      field
    }
  }
}`},Ee={id:`004e85b9237b995957f75c2cfe2a996f5d2de6ca98b31c98511cb403df800ae3`,name:`FlxTaskUpdate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdate($input: FlxTaskUpdateInput!) {
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
}`},De={id:`7c644e53f296f0dbf859b7bfcb4748e8c5be3ef891474fb47a262a2cd993be7a`,name:`FlxTaskUpdateDetail`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdateDetail($input: FlxTaskUpdateInput!) {
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
}`},Oe={id:`a2c919c298da71f1634726a7c85c4344cf85f3602cbf54e4175bd8e45578e9b5`,name:`FlxTaskWatch`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskWatch($input: FlxTaskWatchInput!) {
  flxTaskWatch(input: $input) {
    watcherIds
    errors {
      code
      message
      field
    }
  }
}`},ke=new Set([`UNAUTHENTICATED`,`FORBIDDEN`,`NOT_FOUND`,`INVALID_INPUT`,`CONFLICT`,`RATE_LIMITED`,`INTERNAL`,`PERSISTED_QUERY_NOT_FOUND`,`QUERY_NOT_ALLOWED`,`NETWORK`,`BAD_RESPONSE`]),Z={401:`UNAUTHENTICATED`,403:`FORBIDDEN`,404:`NOT_FOUND`,429:`RATE_LIMITED`},Q=class extends Error{constructor(t,n,r,i){let a=r[0];super(a?.message||i||`GraphQL request failed`),e(this,`code`,void 0),e(this,`field`,void 0),e(this,`errors`,void 0),e(this,`status`,void 0),e(this,`operation`,void 0),this.name=`FlxGraphQLError`,this.operation=t,this.status=n,this.errors=r;let o=a?.extensions?.code;this.code=o&&ke.has(o)?o:i??Z[n]??`INTERNAL`,this.field=typeof a?.extensions?.field==`string`?a.extensions.field:void 0}},Ae=e=>e instanceof Q,$=`../graphql`,je=2e3,Me=e=>Array.isArray(e)&&e.every(e=>typeof e==`object`&&!!e&&typeof e.message==`string`);function Ne(e){let n=e.persisted!==!1;async function r(t,n,r,i){let a=n&&typeof n==`object`&&Object.keys(n).length?n:void 0,o={raw:!0,throwOnError:!1,signal:i.signal,headers:{"X-Flux-GraphQL-Operation":t.name,...i.headers}};if(t.kind===`query`&&!r){let n={queryId:t.id,operationName:t.name,variables:a?JSON.stringify(a):void 0};if(Object.values(n).reduce((e,t)=>e+(t?encodeURIComponent(t).length+16:0),0)<je)return e.api($,{...o,method:`GET`,query:n})}let s=r?{query:t.document,operationName:t.name,variables:a}:{queryId:t.id,operationName:t.name,variables:a};return e.api($,{...o,method:`POST`,body:s})}async function i(e,...i){let a=i[0],o=i[1]??{},s;try{s=await r(e,a,!n,o),n&&s.data?.errors?.some(e=>e.extensions?.code===`PERSISTED_QUERY_NOT_FOUND`)&&(s=await r(e,a,!0,o))}catch(n){throw n instanceof t?new Q(e.name,n.status,[{message:n.message}],n.code===`invalid_json`?`BAD_RESPONSE`:`NETWORK`):n}let c=s.data,l=Me(c?.errors)?c.errors:[],u=c?.data;if(typeof u!=`object`||!u)throw new Q(e.name,s.status,l.length?l:[{message:`Empty GraphQL response`}],l.length?void 0:`BAD_RESPONSE`);if(l.length){let t=new Set(l.map(e=>e.path?.[0]).filter(e=>typeof e==`string`));if([...t].some(e=>u[e]===null)||!t.size)throw new Q(e.name,s.status,l)}return u}return{request:i}}export{J as $,T as A,F as B,v as C,we as Ct,S as D,Oe as Dt,x as E,De as Et,A as F,B as G,L as H,j as I,U as J,V as K,M as L,D as M,O as N,C as O,k as P,q as Q,N as R,_ as S,Ce as St,b as T,Ee as Tt,R as U,I as V,z as W,G as X,W as Y,K as Z,f as _,ve as _t,r as a,oe as at,h as b,xe as bt,o as c,le as ct,l as d,fe as dt,Y as et,u as f,pe as ft,ne as g,_e as gt,d as h,ge as ht,n as i,ae as it,E as j,w as k,s as l,ue as lt,te as m,he as mt,Q as n,re as nt,i as o,se as ot,ee as p,me as pt,H as q,Ae as r,ie as rt,a as s,ce as st,Ne as t,X as tt,c as u,de as ut,p as v,ye as vt,y as w,Te as wt,g as x,Se as xt,m as y,be as yt,P as z};