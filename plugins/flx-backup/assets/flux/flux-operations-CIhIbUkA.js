import{J as e,K as t}from"./flux-host-CfpxPfYo.js";var n=new Set([`UNAUTHENTICATED`,`FORBIDDEN`,`NOT_FOUND`,`INVALID_INPUT`,`CONFLICT`,`RATE_LIMITED`,`INTERNAL`,`PERSISTED_QUERY_NOT_FOUND`,`QUERY_NOT_ALLOWED`,`NETWORK`,`BAD_RESPONSE`]),r={401:`UNAUTHENTICATED`,403:`FORBIDDEN`,404:`NOT_FOUND`,429:`RATE_LIMITED`},i=class extends Error{constructor(t,i,a,o){let s=a[0];super(s?.message||o||`GraphQL request failed`),e(this,`code`,void 0),e(this,`field`,void 0),e(this,`errors`,void 0),e(this,`status`,void 0),e(this,`operation`,void 0),this.name=`FlxGraphQLError`,this.operation=t,this.status=i,this.errors=a;let c=s?.extensions?.code;this.code=c&&n.has(c)?c:o??r[i]??`INTERNAL`,this.field=typeof s?.extensions?.field==`string`?s.extensions.field:void 0}},a=e=>e instanceof i,o=`../graphql`,s=2e3,c=e=>Array.isArray(e)&&e.every(e=>typeof e==`object`&&!!e&&typeof e.message==`string`);function l(e){let n=e.persisted!==!1;async function r(t,n,r,i){let a=n&&typeof n==`object`&&Object.keys(n).length?n:void 0,c={raw:!0,throwOnError:!1,signal:i.signal,headers:{"X-Flux-GraphQL-Operation":t.name,...i.headers}};if(t.kind===`query`&&!r){let n={queryId:t.id,operationName:t.name,variables:a?JSON.stringify(a):void 0};if(Object.values(n).reduce((e,t)=>e+(t?encodeURIComponent(t).length+16:0),0)<s)return e.api(o,{...c,method:`GET`,query:n})}let l=r?{query:t.document,operationName:t.name,variables:a}:{queryId:t.id,operationName:t.name,variables:a};return e.api(o,{...c,method:`POST`,body:l})}async function a(e,...a){let o=a[0],s=a[1]??{},l;try{l=await r(e,o,!n,s),n&&l.data?.errors?.some(e=>e.extensions?.code===`PERSISTED_QUERY_NOT_FOUND`)&&(l=await r(e,o,!0,s))}catch(n){throw n instanceof t?new i(e.name,n.status,[{message:n.message}],n.code===`invalid_json`?`BAD_RESPONSE`:`NETWORK`):n}let u=l.data,d=c(u?.errors)?u.errors:[],f=u?.data;if(typeof f!=`object`||!f)throw new i(e.name,l.status,d.length?d:[{message:`Empty GraphQL response`}],d.length?void 0:`BAD_RESPONSE`);if(d.length){let t=new Set(d.map(e=>e.path?.[0]).filter(e=>typeof e==`string`));if([...t].some(e=>f[e]===null)||!t.size)throw new i(e.name,l.status,d)}return f}return{request:a}}var u={id:`8488b51d8ac2b33ecbd21791e9f59b8915e6afc07bd6bb903a5c002010852316`,name:`FlxBackupCancel`,kind:`mutation`,plugin:`flx-backup`,document:`mutation FlxBackupCancel {
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
}`},d={id:`5e0c2cbc788253a2687dbd388201e2a17fd45703bb4e80d90ee27db885b7a7dd`,name:`FlxBackupDashboard`,kind:`query`,plugin:`flx-backup`,document:`query FlxBackupDashboard {
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
}`},f={id:`762db37c8115f91fa0708dd983e1d5a6497ff9e471a1d48d4736e39b966030c8`,name:`FlxBackupDelete`,kind:`mutation`,plugin:`flx-backup`,document:`mutation FlxBackupDelete($input: FlxBackupDeleteInput!) {
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
}`},p={id:`7efa2a37904f7bc808c0c94dd88aad63935b3f1b89f6d6f859769c0ffbfedfe8`,name:`FlxBackupDetail`,kind:`query`,plugin:`flx-backup`,document:`query FlxBackupDetail($id: ID!) {
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
}`},m={id:`3441ccc32a645f6092c405c3bea324de1e67815de578be76bebfeb991c2c0567`,name:`FlxBackupJobRead`,kind:`query`,plugin:`flx-backup`,document:`query FlxBackupJobRead {
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
}`},h={id:`444fe54b9b0e5cdeba4d419732fc6004b26926ea3e5ae242b820ccafc8b0d12c`,name:`FlxBackupList`,kind:`query`,plugin:`flx-backup`,document:`query FlxBackupList($first: Int = 20, $after: String, $type: FlxBackupType, $status: FlxBackupStatus) {
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
}`},g={id:`48e64891298acc8e11a7b7988f76d522414a779aa2ded6cac1b81642c7bd6d87`,name:`FlxBackupLock`,kind:`mutation`,plugin:`flx-backup`,document:`mutation FlxBackupLock($input: FlxBackupLockInput!) {
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
}`},_={id:`1f63d67d4775c30a38621420617d5fe48b180e7fcb37ed2548169f8185236331`,name:`FlxBackupRestoreStart`,kind:`mutation`,plugin:`flx-backup`,document:`mutation FlxBackupRestoreStart($input: FlxBackupRestoreStartInput!) {
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
}`},v={id:`fc5a400fc1bcd3cc9add10409ab93b7e08cff77540a6c3e4c425d4aba82bcd5e`,name:`FlxBackupSettingsSave`,kind:`mutation`,plugin:`flx-backup`,document:`mutation FlxBackupSettingsSave($input: FlxBackupSettingsSaveInput!) {
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
}`},y={id:`0c1e33d45c14005f514bea1ded294ed69a0d03119f779346d180c9953fb404d5`,name:`FlxBackupSettingsScreen`,kind:`query`,plugin:`flx-backup`,document:`query FlxBackupSettingsScreen {
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
}`},b={id:`6b6e92e138ef921340c8a8bd7a2c1f56d1fef85f289f28d8552e1a13c2f5afd2`,name:`FlxBackupStart`,kind:`mutation`,plugin:`flx-backup`,document:`mutation FlxBackupStart($input: FlxBackupStartInput!) {
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
}`},x={id:`3ca258ac5c8de7419cf97041ab75861522f24988dd7c92e5b855a4a785a25b1d`,name:`FlxBackupStep`,kind:`mutation`,plugin:`flx-backup`,document:`mutation FlxBackupStep {
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
}`};export{i as A,M as C,I as D,F as E,L as O,j as S,P as T,E as _,m as a,k as b,_ as c,b as d,x as f,T as g,w as h,p as i,a as j,l as k,v as l,C as m,d as n,h as o,S as p,f as r,g as s,u as t,y as u,D as v,N as w,A as x,O as y};