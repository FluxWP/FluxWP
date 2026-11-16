import{J as e,K as t}from"./flux-host-CfpxPfYo.js";var n=new Set([`UNAUTHENTICATED`,`FORBIDDEN`,`NOT_FOUND`,`INVALID_INPUT`,`CONFLICT`,`RATE_LIMITED`,`INTERNAL`,`PERSISTED_QUERY_NOT_FOUND`,`QUERY_NOT_ALLOWED`,`NETWORK`,`BAD_RESPONSE`]),r={401:`UNAUTHENTICATED`,403:`FORBIDDEN`,404:`NOT_FOUND`,429:`RATE_LIMITED`},i=class extends Error{constructor(t,i,a,o){let s=a[0];super(s?.message||o||`GraphQL request failed`),e(this,`code`,void 0),e(this,`field`,void 0),e(this,`errors`,void 0),e(this,`status`,void 0),e(this,`operation`,void 0),this.name=`FlxGraphQLError`,this.operation=t,this.status=i,this.errors=a;let c=s?.extensions?.code;this.code=c&&n.has(c)?c:o??r[i]??`INTERNAL`,this.field=typeof s?.extensions?.field==`string`?s.extensions.field:void 0}},a=e=>e instanceof i,o=`../graphql`,s=2e3,c=e=>Array.isArray(e)&&e.every(e=>typeof e==`object`&&!!e&&typeof e.message==`string`);function l(e){let n=e.persisted!==!1;async function r(t,n,r,i){let a=n&&typeof n==`object`&&Object.keys(n).length?n:void 0,c={raw:!0,throwOnError:!1,signal:i.signal,headers:{"X-Flux-GraphQL-Operation":t.name,...i.headers}};if(t.kind===`query`&&!r){let n={queryId:t.id,operationName:t.name,variables:a?JSON.stringify(a):void 0};if(Object.values(n).reduce((e,t)=>e+(t?encodeURIComponent(t).length+16:0),0)<s)return e.api(o,{...c,method:`GET`,query:n})}let l=r?{query:t.document,operationName:t.name,variables:a}:{queryId:t.id,operationName:t.name,variables:a};return e.api(o,{...c,method:`POST`,body:l})}async function a(e,...a){let o=a[0],s=a[1]??{},l;try{l=await r(e,o,!n,s),n&&l.data?.errors?.some(e=>e.extensions?.code===`PERSISTED_QUERY_NOT_FOUND`)&&(l=await r(e,o,!0,s))}catch(n){throw n instanceof t?new i(e.name,n.status,[{message:n.message}],n.code===`invalid_json`?`BAD_RESPONSE`:`NETWORK`):n}let u=l.data,d=c(u?.errors)?u.errors:[],f=u?.data;if(typeof f!=`object`||!f)throw new i(e.name,l.status,d.length?d:[{message:`Empty GraphQL response`}],d.length?void 0:`BAD_RESPONSE`);if(d.length){let t=new Set(d.map(e=>e.path?.[0]).filter(e=>typeof e==`string`));if([...t].some(e=>f[e]===null)||!t.size)throw new i(e.name,l.status,d)}return f}return{request:a}}var u={id:`21a336969a0dc41b8caaa1be0a20b9bd740359347ff57bc8cb72f70ca6254f9e`,name:`FlxRoleCreate`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleCreate($input: FlxRoleCreateInput!) {
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
}`},d={id:`2bd5579ac1188eadb0dff5235840b66098c68b1b0d9e5d9a86c1a07b54d3acea`,name:`FlxRoleDelete`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleDelete($input: FlxRoleDeleteInput!) {
  flxRoleDelete(input: $input) {
    deletedKey
    errors {
      code
      message
      field
    }
  }
}`},f={id:`af09ea3f7594b6e37e1aa272f116e57dd7079ca275a754a8107fc230ce3014df`,name:`FlxRoleMemberAdd`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleMemberAdd($input: FlxRoleMemberAddInput!) {
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
}`},p={id:`6eab4d6f1f97457a8679a9b1dd92d47dc2e01a7f41163eff439dc854213b2a7c`,name:`FlxRoleMemberRemove`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleMemberRemove($input: FlxRoleMemberRemoveInput!) {
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
}`},m={id:`067ae709ce0675a0f8e8ab3ab7d21d722afa9f7449f358767be373133c98de6f`,name:`FlxRoleMembers`,kind:`query`,plugin:`flx-roles`,document:`query FlxRoleMembers($key: String!, $first: Int = 50, $after: String) {
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
}`},h={id:`20e6cdbb88f7639a8749e74cf75ef58a4ab704143f0cf0fa3474dbb2366cb1d0`,name:`FlxRoleReset`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleReset($input: FlxRoleResetInput!) {
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
}`},g={id:`0a4da13faad85546b92fab5b45716dab5b06e87791475a9dfaa4664888375658`,name:`FlxRolesSave`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRolesSave($input: FlxRolesSaveInput!) {
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
}`},_={id:`2c22565866bcebf0aaed510b75bec73444fc8257c8d2b327873e9c2963c59030`,name:`FlxRolesScreen`,kind:`query`,plugin:`flx-roles`,document:`query FlxRolesScreen($members: Int = 50) {
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
}`},v={id:`c0c034497a50d8f207d50ee8d53dee71fb810f0dfd57c6163345be91a32f0a5b`,name:`FlxSpeedAutoload`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedAutoload {
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
}`},y={id:`8d270a918a41d495c99f9871ce13a133eade0ce31328d7c2bd4123e82cb5e2c6`,name:`FlxSpeedCache`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedCache {
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
}`},b={id:`d977b224915686fef097ececbb92793cf30eced524668174faa48191478ee08f`,name:`FlxSpeedCacheRun`,kind:`mutation`,plugin:`flx-speed`,document:`mutation FlxSpeedCacheRun($input: FlxSpeedCacheRunInput!) {
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
}`},x={id:`fcd076afcbd146a9e6ca574ba597984e1637eee1b8f35aa9b512e428916aaa43`,name:`FlxSpeedCleanup`,kind:`mutation`,plugin:`flx-speed`,document:`mutation FlxSpeedCleanup($input: FlxSpeedCleanupInput!) {
  flxSpeedCleanup(input: $input) {
    done
    errors {
      code
      message
      field
    }
  }
}`},S={id:`48d2ae9c4348a0b4300b2fc4b0254b07472858e0e5f65eaddd4996ce4801bb4d`,name:`FlxSpeedConfig`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedConfig {
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
}`},C={id:`58ed686173cd507ef595683e7cbf4b30ed514b82612dd1caa4e75a2199f885f0`,name:`FlxSpeedCron`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedCron {
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
}`},w={id:`8de800753b00b5e90b240028948dd2ee2a99c1c6e89ef22b03eae970f15f2a3f`,name:`FlxSpeedDatabase`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedDatabase {
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
}`},T={id:`7d247ff1bb9def8192c868f601c45a17feed2dfb10ea254afa0b785be069a8c8`,name:`FlxSpeedLive`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedLive {
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
}`},E={id:`f053efbf67cf7f284fd5c1efd1a0157d89c3beedf57981dde2a693e1b6567f7f`,name:`FlxSpeedPageSpeed`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedPageSpeed {
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
}`},D={id:`b375ed8680d809378b5e9616f1f1bc440c2e912041b84d39d6df690eb4de4c09`,name:`FlxSpeedPageSpeedClear`,kind:`mutation`,plugin:`flx-speed`,document:`mutation FlxSpeedPageSpeedClear {
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
}`},O={id:`e4e117cb961c1485e8fd5b4c322f34231ddec9056751ec79317e99985fca16bc`,name:`FlxSpeedPageSpeedSave`,kind:`mutation`,plugin:`flx-speed`,document:`mutation FlxSpeedPageSpeedSave($input: FlxSpeedPageSpeedSaveInput!) {
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
}`},k={id:`7dbefa1a379a4febe1f2eb7c39c6f399f2bfa417df3cd57c8cf0ede758a3b3c7`,name:`FlxSpeedProfile`,kind:`mutation`,plugin:`flx-speed`,document:`mutation FlxSpeedProfile($input: FlxSpeedProfileInput!) {
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
}`},A={id:`d43528eacd33ad43a20ded425950c72cfe41d37466f94418bbb5a91d9bd39bd1`,name:`FlxSpeedRecord`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedRecord($id: Int!) {
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
}`},j={id:`b6692580ef2fbfa27c06265ef8f344b27cc749cd14f6cfb2725953194ca7909a`,name:`FlxSpeedRecords`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedRecords($first: Int = 25, $after: String, $hours: Int, $flag: String, $type: String, $q: String, $slow: Boolean) {
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
}`},M={id:`845b54851a74fba07a12deeefeb2c7b71f6ecfaaa759daa50c6041a482dddb25`,name:`FlxSpeedRecordsClear`,kind:`mutation`,plugin:`flx-speed`,document:`mutation FlxSpeedRecordsClear {
  flxSpeedRecordsClear(input: {  }) {
    cleared
    errors {
      code
      message
      field
    }
  }
}`},N={id:`9234ad49b162d1b924b722bc341baff844c5c5fe32121c5f3c604fc0d018b89a`,name:`FlxSpeedServer`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedServer {
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
}`},P={id:`3ed06bef7eb6f4c9e11545d09afef2ce9ff9cbc1fd38115124ff2b649e3d630f`,name:`FlxSpeedSettings`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedSettings {
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
}`},F={id:`ae1ea2b03f50ac55abebc5ae0a507d77e463e14edae360feaf1646ebd2ddaac4`,name:`FlxSpeedSettingsSave`,kind:`mutation`,plugin:`flx-speed`,document:`mutation FlxSpeedSettingsSave($input: FlxSpeedSettingsSaveInput!) {
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
}`},I={id:`29e3951ab3ac534d8bc6a7abc8e6aaa037842a052d95472eddaa6c714ad66e7e`,name:`FlxSpeedSummary`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedSummary($hours: Int = 24) {
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
}`},L={id:`a6ad626fabff2f9904a2f88aec77a49b4dfd900c575ea7fd357b0234aca73d82`,name:`FlxSpeedSystem`,kind:`query`,plugin:`flx-speed`,document:`query FlxSpeedSystem {
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
}`},R={id:`fca51d9372f393736cd5c7f3cc3a6c6f76bc42c714907f05360f59ce43283d8b`,name:`FlxTaskCommentCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCommentCreate($input: FlxTaskCommentCreateInput!) {
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
}`},z={id:`f4712b3032ffaf4580393cf0e6234154fa1eb66399b12784aa29f29b625d34bd`,name:`FlxTaskCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCreate($input: FlxTaskCreateInput!) {
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
}`},B={id:`157053946bdf55aa4bf5f22a676119ec48e7bbe04c053867b7e0eedb11e04ec2`,name:`FlxTaskDelete`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskDelete($input: FlxTaskDeleteInput!) {
  flxTaskDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},V={id:`fa1d3f71ca31ab2a7b72c1a987e295f209d6564e8a68e3643dee354a44544ecf`,name:`FlxTaskDetail`,kind:`query`,plugin:`flx-tasks`,document:`query FlxTaskDetail($id: ID!) {
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
}`},H={id:`d357c8fdb6c372508b17bb93fa22ec80224c0ff43854104149816d70a9e80870`,name:`FlxTaskNotificationsRead`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskNotificationsRead($input: FlxTaskNotificationsReadInput!) {
  flxTaskNotificationsRead(input: $input) {
    unread
    errors {
      code
      message
      field
    }
  }
}`},U={id:`004e85b9237b995957f75c2cfe2a996f5d2de6ca98b31c98511cb403df800ae3`,name:`FlxTaskUpdate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdate($input: FlxTaskUpdateInput!) {
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
}`},W={id:`7c644e53f296f0dbf859b7bfcb4748e8c5be3ef891474fb47a262a2cd993be7a`,name:`FlxTaskUpdateDetail`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdateDetail($input: FlxTaskUpdateInput!) {
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
}`},G={id:`a2c919c298da71f1634726a7c85c4344cf85f3602cbf54e4175bd8e45578e9b5`,name:`FlxTaskWatch`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskWatch($input: FlxTaskWatchInput!) {
  flxTaskWatch(input: $input) {
    watcherIds
    errors {
      code
      message
      field
    }
  }
}`};export{z as A,M as C,I as D,F as E,W as F,G as I,l as L,V as M,H as N,L as O,U as P,i as R,j as S,P as T,E as _,m as a,k as b,_ as c,b as d,x as f,T as g,w as h,p as i,B as j,R as k,v as l,C as m,d as n,h as o,S as p,f as r,g as s,u as t,y as u,D as v,N as w,A as x,O as y,a as z};