import{J as e,K as t}from"./flux-host-CfpxPfYo.js";var n={id:`38c66e1db90a0c7f811614cb50f0f82d679a21ff93ebe0cbf3b021b9e5a9f7af`,name:`FlxAnalyticsAggregate`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsAggregate($input: FlxAnalyticsAggregateInput!) {
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
}`},d={id:`ebbac7ddcb08b32fb8b2c0eb5b67c80284fa09be3165b30eebeae0a282cee423`,name:`FlxAnalyticsPages`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsPages($filter: FlxAnalyticsFilterInput, $q: String, $only404: Boolean, $first: Int, $after: String) {
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
}`},f={id:`db042cdd9f459d72081431f7df6d8a9e1081c5230eef59de2e263c30dcb9d698`,name:`FlxAnalyticsRealtime`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsRealtime($filter: FlxAnalyticsFilterInput, $minutes: Int, $kpi: Boolean) {
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
}`},p={id:`3c8464c3ca4e71ec015a9a16728f8a3696bdbd22a4e4eecc19312c893c3d4db1`,name:`FlxAnalyticsReferrers`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsReferrers($filter: FlxAnalyticsFilterInput, $q: String, $first: Int, $after: String) {
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
}`},m={id:`59ee013d5260056c879e17dc79e67240198450d0cb156e6425159922a2d37744`,name:`FlxAnalyticsRefSave`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsRefSave($input: FlxAnalyticsRefSaveInput!) {
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
}`},h={id:`8379b10f9853966238a2d54a9b2b4dd22175b692573038145ee03bf5b3501f45`,name:`FlxAnalyticsReport`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsReport($filter: FlxAnalyticsFilterInput) {
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
}`},g={id:`ce33713a41cfcf183974cb43003436209c4bedc460a35228a0fd27982819f466`,name:`FlxAnalyticsReset`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsReset($input: FlxAnalyticsResetInput!) {
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
}`},_={id:`129cfba2b194904d644f1f6c1a89065b2c3534dd0982d6aa88d30aa855920c9f`,name:`FlxAnalyticsSettings`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsSettings {
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
}`},v={id:`fcf4e0148b6d01ec7f05e578011d35764659fdef2afb0d3cd5438e14707c4c8f`,name:`FlxAnalyticsSettingsSave`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsSettingsSave($input: FlxAnalyticsSettingsSaveInput!) {
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
}`},y={id:`eb5453fc995071cd78ac870527d7da1090a3441f298f6113f2af7cfe9fde45f4`,name:`FlxAnalyticsTelemetry`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsTelemetry($days: Int, $country: String) {
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
}`},b={id:`98c319fa61824c0c4714f4d86466d15123634d74291edb45f9001c7d7b6b7fbb`,name:`FlxAnalyticsTelemetryErrors`,kind:`query`,plugin:`flx-analytics`,document:`query FlxAnalyticsTelemetryErrors($days: Int, $country: String, $plugin: String, $type: String) {
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
}`},x={id:`d64ae79eb5491a540154bd58029d69fc0b5e212e8c46414279d4ca0eaa940e1d`,name:`FlxAnalyticsTelemetryForget`,kind:`mutation`,plugin:`flx-analytics`,document:`mutation FlxAnalyticsTelemetryForget($input: FlxAnalyticsTelemetryForgetInput!) {
  flxAnalyticsTelemetryForget(input: $input) {
    removed
    errors {
      code
      message
      field
    }
  }
}`},S={id:`21a336969a0dc41b8caaa1be0a20b9bd740359347ff57bc8cb72f70ca6254f9e`,name:`FlxRoleCreate`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleCreate($input: FlxRoleCreateInput!) {
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
}`},C={id:`2bd5579ac1188eadb0dff5235840b66098c68b1b0d9e5d9a86c1a07b54d3acea`,name:`FlxRoleDelete`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleDelete($input: FlxRoleDeleteInput!) {
  flxRoleDelete(input: $input) {
    deletedKey
    errors {
      code
      message
      field
    }
  }
}`},w={id:`af09ea3f7594b6e37e1aa272f116e57dd7079ca275a754a8107fc230ce3014df`,name:`FlxRoleMemberAdd`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleMemberAdd($input: FlxRoleMemberAddInput!) {
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
}`},T={id:`6eab4d6f1f97457a8679a9b1dd92d47dc2e01a7f41163eff439dc854213b2a7c`,name:`FlxRoleMemberRemove`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleMemberRemove($input: FlxRoleMemberRemoveInput!) {
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
}`},E={id:`067ae709ce0675a0f8e8ab3ab7d21d722afa9f7449f358767be373133c98de6f`,name:`FlxRoleMembers`,kind:`query`,plugin:`flx-roles`,document:`query FlxRoleMembers($key: String!, $first: Int = 50, $after: String) {
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
}`},D={id:`20e6cdbb88f7639a8749e74cf75ef58a4ab704143f0cf0fa3474dbb2366cb1d0`,name:`FlxRoleReset`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleReset($input: FlxRoleResetInput!) {
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
}`},O={id:`0a4da13faad85546b92fab5b45716dab5b06e87791475a9dfaa4664888375658`,name:`FlxRolesSave`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRolesSave($input: FlxRolesSaveInput!) {
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
}`},k={id:`2c22565866bcebf0aaed510b75bec73444fc8257c8d2b327873e9c2963c59030`,name:`FlxRolesScreen`,kind:`query`,plugin:`flx-roles`,document:`query FlxRolesScreen($members: Int = 50) {
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
}`},A={id:`fca51d9372f393736cd5c7f3cc3a6c6f76bc42c714907f05360f59ce43283d8b`,name:`FlxTaskCommentCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCommentCreate($input: FlxTaskCommentCreateInput!) {
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
}`},j={id:`f4712b3032ffaf4580393cf0e6234154fa1eb66399b12784aa29f29b625d34bd`,name:`FlxTaskCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCreate($input: FlxTaskCreateInput!) {
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
}`},M={id:`157053946bdf55aa4bf5f22a676119ec48e7bbe04c053867b7e0eedb11e04ec2`,name:`FlxTaskDelete`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskDelete($input: FlxTaskDeleteInput!) {
  flxTaskDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},N={id:`fa1d3f71ca31ab2a7b72c1a987e295f209d6564e8a68e3643dee354a44544ecf`,name:`FlxTaskDetail`,kind:`query`,plugin:`flx-tasks`,document:`query FlxTaskDetail($id: ID!) {
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
}`},P={id:`d357c8fdb6c372508b17bb93fa22ec80224c0ff43854104149816d70a9e80870`,name:`FlxTaskNotificationsRead`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskNotificationsRead($input: FlxTaskNotificationsReadInput!) {
  flxTaskNotificationsRead(input: $input) {
    unread
    errors {
      code
      message
      field
    }
  }
}`},F={id:`004e85b9237b995957f75c2cfe2a996f5d2de6ca98b31c98511cb403df800ae3`,name:`FlxTaskUpdate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdate($input: FlxTaskUpdateInput!) {
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
}`},I={id:`7c644e53f296f0dbf859b7bfcb4748e8c5be3ef891474fb47a262a2cd993be7a`,name:`FlxTaskUpdateDetail`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdateDetail($input: FlxTaskUpdateInput!) {
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
}`},L={id:`a2c919c298da71f1634726a7c85c4344cf85f3602cbf54e4175bd8e45578e9b5`,name:`FlxTaskWatch`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskWatch($input: FlxTaskWatchInput!) {
  flxTaskWatch(input: $input) {
    watcherIds
    errors {
      code
      message
      field
    }
  }
}`},R=new Set([`UNAUTHENTICATED`,`FORBIDDEN`,`NOT_FOUND`,`INVALID_INPUT`,`CONFLICT`,`RATE_LIMITED`,`INTERNAL`,`PERSISTED_QUERY_NOT_FOUND`,`QUERY_NOT_ALLOWED`,`NETWORK`,`BAD_RESPONSE`]),z={401:`UNAUTHENTICATED`,403:`FORBIDDEN`,404:`NOT_FOUND`,429:`RATE_LIMITED`},B=class extends Error{constructor(t,n,r,i){let a=r[0];super(a?.message||i||`GraphQL request failed`),e(this,`code`,void 0),e(this,`field`,void 0),e(this,`errors`,void 0),e(this,`status`,void 0),e(this,`operation`,void 0),this.name=`FlxGraphQLError`,this.operation=t,this.status=n,this.errors=r;let o=a?.extensions?.code;this.code=o&&R.has(o)?o:i??z[n]??`INTERNAL`,this.field=typeof a?.extensions?.field==`string`?a.extensions.field:void 0}},V=e=>e instanceof B,H=`../graphql`,U=2e3,W=e=>Array.isArray(e)&&e.every(e=>typeof e==`object`&&!!e&&typeof e.message==`string`);function G(e){let n=e.persisted!==!1;async function r(t,n,r,i){let a=n&&typeof n==`object`&&Object.keys(n).length?n:void 0,o={raw:!0,throwOnError:!1,signal:i.signal,headers:{"X-Flux-GraphQL-Operation":t.name,...i.headers}};if(t.kind===`query`&&!r){let n={queryId:t.id,operationName:t.name,variables:a?JSON.stringify(a):void 0};if(Object.values(n).reduce((e,t)=>e+(t?encodeURIComponent(t).length+16:0),0)<U)return e.api(H,{...o,method:`GET`,query:n})}let s=r?{query:t.document,operationName:t.name,variables:a}:{queryId:t.id,operationName:t.name,variables:a};return e.api(H,{...o,method:`POST`,body:s})}async function i(e,...i){let a=i[0],o=i[1]??{},s;try{s=await r(e,a,!n,o),n&&s.data?.errors?.some(e=>e.extensions?.code===`PERSISTED_QUERY_NOT_FOUND`)&&(s=await r(e,a,!0,o))}catch(n){throw n instanceof t?new B(e.name,n.status,[{message:n.message}],n.code===`invalid_json`?`BAD_RESPONSE`:`NETWORK`):n}let c=s.data,l=W(c?.errors)?c.errors:[],u=c?.data;if(typeof u!=`object`||!u)throw new B(e.name,s.status,l.length?l:[{message:`Empty GraphQL response`}],l.length?void 0:`BAD_RESPONSE`);if(l.length){let t=new Set(l.map(e=>e.path?.[0]).filter(e=>typeof e==`string`));if([...t].some(e=>u[e]===null)||!t.size)throw new B(e.name,s.status,l)}return u}return{request:i}}export{O as A,x as C,T as D,w as E,N as F,P as I,F as L,A as M,j as N,E as O,M as P,I as R,b as S,C as T,h as _,r as a,v as b,o as c,l as d,u as f,p as g,m as h,n as i,k as j,D as k,s as l,f as m,B as n,i as o,d as p,V as r,a as s,G as t,c as u,g as v,S as w,y as x,_ as y,L as z};