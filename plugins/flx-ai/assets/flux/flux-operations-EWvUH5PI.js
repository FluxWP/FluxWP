import{J as e,K as t}from"./flux-host-CfpxPfYo.js";var n=new Set([`UNAUTHENTICATED`,`FORBIDDEN`,`NOT_FOUND`,`INVALID_INPUT`,`CONFLICT`,`RATE_LIMITED`,`INTERNAL`,`PERSISTED_QUERY_NOT_FOUND`,`QUERY_NOT_ALLOWED`,`NETWORK`,`BAD_RESPONSE`]),r={401:`UNAUTHENTICATED`,403:`FORBIDDEN`,404:`NOT_FOUND`,429:`RATE_LIMITED`},i=class extends Error{constructor(t,i,a,o){let s=a[0];super(s?.message||o||`GraphQL request failed`),e(this,`code`,void 0),e(this,`field`,void 0),e(this,`errors`,void 0),e(this,`status`,void 0),e(this,`operation`,void 0),this.name=`FlxGraphQLError`,this.operation=t,this.status=i,this.errors=a;let c=s?.extensions?.code;this.code=c&&n.has(c)?c:o??r[i]??`INTERNAL`,this.field=typeof s?.extensions?.field==`string`?s.extensions.field:void 0}},a=e=>e instanceof i,o=`../graphql`,s=2e3,c=e=>Array.isArray(e)&&e.every(e=>typeof e==`object`&&!!e&&typeof e.message==`string`);function l(e){let n=e.persisted!==!1;async function r(t,n,r,i){let a=n&&typeof n==`object`&&Object.keys(n).length?n:void 0,c={raw:!0,throwOnError:!1,signal:i.signal,headers:{"X-Flux-GraphQL-Operation":t.name,...i.headers}};if(t.kind===`query`&&!r){let n={queryId:t.id,operationName:t.name,variables:a?JSON.stringify(a):void 0};if(Object.values(n).reduce((e,t)=>e+(t?encodeURIComponent(t).length+16:0),0)<s)return e.api(o,{...c,method:`GET`,query:n})}let l=r?{query:t.document,operationName:t.name,variables:a}:{queryId:t.id,operationName:t.name,variables:a};return e.api(o,{...c,method:`POST`,body:l})}async function a(e,...a){let o=a[0],s=a[1]??{},l;try{l=await r(e,o,!n,s),n&&l.data?.errors?.some(e=>e.extensions?.code===`PERSISTED_QUERY_NOT_FOUND`)&&(l=await r(e,o,!0,s))}catch(n){throw n instanceof t?new i(e.name,n.status,[{message:n.message}],n.code===`invalid_json`?`BAD_RESPONSE`:`NETWORK`):n}let u=l.data,d=c(u?.errors)?u.errors:[],f=u?.data;if(typeof f!=`object`||!f)throw new i(e.name,l.status,d.length?d:[{message:`Empty GraphQL response`}],d.length?void 0:`BAD_RESPONSE`);if(d.length){let t=new Set(d.map(e=>e.path?.[0]).filter(e=>typeof e==`string`));if([...t].some(e=>f[e]===null)||!t.size)throw new i(e.name,l.status,d)}return f}return{request:a}}var u={id:`2ce74187ffc690342b4345a3cde10aefb9efc6e12ea495d8531228dc9647e8fe`,name:`FlxAiBilling`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiBilling($month: String) {
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
}`},d={id:`ecfbbe459dc33b5d5788763a9b369b151e2c6fb1b03dcffe9f31e1fa29785ab3`,name:`FlxAiCreditsRefresh`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiCreditsRefresh {
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
}`},f={id:`580801a095e875c9d47b645c9cb73f0f6fdb1846df5ad9d786d2efa129e9a5c8`,name:`FlxAiLogBody`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiLogBody($id: Int!) {
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
}`},p={id:`eebe6c676c9d99c5200a53263539c163c3c4f838ca7e6fe125ce4b190711d0b5`,name:`FlxAiLogs`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiLogs($first: Int = 50, $after: String, $days: Int = 7, $kind: FlxAiLogKind, $source: String, $errorsOnly: Boolean = false) {
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
}`},m={id:`a016eb829167be15c5f406279dc7b461d1610db9bd6287d84a489d71f9cc1a17`,name:`FlxAiOverview`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiOverview($days: Int = 30, $source: String) {
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
}`},h={id:`97e294dc31a61739f94e276e07f3a784c69619c14137143d7fa6c20e6ffd6ab5`,name:`FlxAiSystems`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiSystems {
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
}`},g={id:`73431ff2a62e32d7f2e91309552ed1c18343d40e544dd7ad09c09618c1c845fb`,name:`FlxAiSystemsUpdate`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiSystemsUpdate($input: FlxAiSystemsUpdateInput!) {
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
}`},_={id:`89cc3100bddc0b80ca45c79713741b1e5c52fbff15fcc12aa33aa648bfa6b3cd`,name:`FlxAiToolPick`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiToolPick($input: FlxAiToolPickInput!) {
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
}`},v={id:`2ac9ecc70a8c53e97b50f2b9b4761536f293a681d6e3e0c4fe65ae7b83a2cacb`,name:`FlxAiTools`,kind:`query`,plugin:`flx-ai`,document:`query FlxAiTools {
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
}`},y={id:`fa6d026ab04301f37b80092e734c35cea8adfdf785d61057e8a0d53f71e4fdae`,name:`FlxAiToolsUpdate`,kind:`mutation`,plugin:`flx-ai`,document:`mutation FlxAiToolsUpdate($input: FlxAiToolsUpdateInput!) {
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
}`},b={id:`21a336969a0dc41b8caaa1be0a20b9bd740359347ff57bc8cb72f70ca6254f9e`,name:`FlxRoleCreate`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleCreate($input: FlxRoleCreateInput!) {
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
}`},x={id:`2bd5579ac1188eadb0dff5235840b66098c68b1b0d9e5d9a86c1a07b54d3acea`,name:`FlxRoleDelete`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleDelete($input: FlxRoleDeleteInput!) {
  flxRoleDelete(input: $input) {
    deletedKey
    errors {
      code
      message
      field
    }
  }
}`},S={id:`af09ea3f7594b6e37e1aa272f116e57dd7079ca275a754a8107fc230ce3014df`,name:`FlxRoleMemberAdd`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleMemberAdd($input: FlxRoleMemberAddInput!) {
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
}`},C={id:`6eab4d6f1f97457a8679a9b1dd92d47dc2e01a7f41163eff439dc854213b2a7c`,name:`FlxRoleMemberRemove`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleMemberRemove($input: FlxRoleMemberRemoveInput!) {
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
}`},w={id:`067ae709ce0675a0f8e8ab3ab7d21d722afa9f7449f358767be373133c98de6f`,name:`FlxRoleMembers`,kind:`query`,plugin:`flx-roles`,document:`query FlxRoleMembers($key: String!, $first: Int = 50, $after: String) {
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
}`},T={id:`20e6cdbb88f7639a8749e74cf75ef58a4ab704143f0cf0fa3474dbb2366cb1d0`,name:`FlxRoleReset`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleReset($input: FlxRoleResetInput!) {
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
}`},E={id:`0a4da13faad85546b92fab5b45716dab5b06e87791475a9dfaa4664888375658`,name:`FlxRolesSave`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRolesSave($input: FlxRolesSaveInput!) {
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
}`},D={id:`2c22565866bcebf0aaed510b75bec73444fc8257c8d2b327873e9c2963c59030`,name:`FlxRolesScreen`,kind:`query`,plugin:`flx-roles`,document:`query FlxRolesScreen($members: Int = 50) {
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
}`},O={id:`fca51d9372f393736cd5c7f3cc3a6c6f76bc42c714907f05360f59ce43283d8b`,name:`FlxTaskCommentCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCommentCreate($input: FlxTaskCommentCreateInput!) {
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
}`},k={id:`f4712b3032ffaf4580393cf0e6234154fa1eb66399b12784aa29f29b625d34bd`,name:`FlxTaskCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCreate($input: FlxTaskCreateInput!) {
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
}`},A={id:`157053946bdf55aa4bf5f22a676119ec48e7bbe04c053867b7e0eedb11e04ec2`,name:`FlxTaskDelete`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskDelete($input: FlxTaskDeleteInput!) {
  flxTaskDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},j={id:`fa1d3f71ca31ab2a7b72c1a987e295f209d6564e8a68e3643dee354a44544ecf`,name:`FlxTaskDetail`,kind:`query`,plugin:`flx-tasks`,document:`query FlxTaskDetail($id: ID!) {
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
}`},M={id:`d357c8fdb6c372508b17bb93fa22ec80224c0ff43854104149816d70a9e80870`,name:`FlxTaskNotificationsRead`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskNotificationsRead($input: FlxTaskNotificationsReadInput!) {
  flxTaskNotificationsRead(input: $input) {
    unread
    errors {
      code
      message
      field
    }
  }
}`},N={id:`004e85b9237b995957f75c2cfe2a996f5d2de6ca98b31c98511cb403df800ae3`,name:`FlxTaskUpdate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdate($input: FlxTaskUpdateInput!) {
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
}`},P={id:`7c644e53f296f0dbf859b7bfcb4748e8c5be3ef891474fb47a262a2cd993be7a`,name:`FlxTaskUpdateDetail`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdateDetail($input: FlxTaskUpdateInput!) {
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
}`},F={id:`a2c919c298da71f1634726a7c85c4344cf85f3602cbf54e4175bd8e45578e9b5`,name:`FlxTaskWatch`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskWatch($input: FlxTaskWatchInput!) {
  flxTaskWatch(input: $input) {
    watcherIds
    errors {
      code
      message
      field
    }
  }
}`};export{M as C,l as D,F as E,i as O,j as S,P as T,E as _,m as a,k as b,_ as c,b as d,x as f,T as g,w as h,p as i,a as k,v as l,C as m,d as n,h as o,S as p,f as r,g as s,u as t,y as u,D as v,N as w,A as x,O as y};