import{Y as e,q as t}from"./flux-host-Cvfh42ms.js";var n=new Set([`UNAUTHENTICATED`,`FORBIDDEN`,`NOT_FOUND`,`INVALID_INPUT`,`CONFLICT`,`RATE_LIMITED`,`INTERNAL`,`PERSISTED_QUERY_NOT_FOUND`,`QUERY_NOT_ALLOWED`,`NETWORK`,`BAD_RESPONSE`]),r={401:`UNAUTHENTICATED`,403:`FORBIDDEN`,404:`NOT_FOUND`,429:`RATE_LIMITED`},i=class extends Error{constructor(t,i,a,o){let s=a[0];super(s?.message||o||`GraphQL request failed`),e(this,`code`,void 0),e(this,`field`,void 0),e(this,`errors`,void 0),e(this,`status`,void 0),e(this,`operation`,void 0),this.name=`FlxGraphQLError`,this.operation=t,this.status=i,this.errors=a;let c=s?.extensions?.code;this.code=c&&n.has(c)?c:o??r[i]??`INTERNAL`,this.field=typeof s?.extensions?.field==`string`?s.extensions.field:void 0}},a=e=>e instanceof i,o=`../graphql`,s=2e3,c=e=>Array.isArray(e)&&e.every(e=>typeof e==`object`&&!!e&&typeof e.message==`string`);function l(e){let n=e.persisted!==!1;async function r(t,n,r,i){let a=n&&typeof n==`object`&&Object.keys(n).length?n:void 0,c={raw:!0,throwOnError:!1,signal:i.signal,headers:{"X-Flux-GraphQL-Operation":t.name,...i.headers}};if(t.kind===`query`&&!r){let n={queryId:t.id,operationName:t.name,variables:a?JSON.stringify(a):void 0};if(Object.values(n).reduce((e,t)=>e+(t?encodeURIComponent(t).length+16:0),0)<s)return e.api(o,{...c,method:`GET`,query:n})}let l=r?{query:t.document,operationName:t.name,variables:a}:{queryId:t.id,operationName:t.name,variables:a};return e.api(o,{...c,method:`POST`,body:l})}async function a(e,...a){let o=a[0],s=a[1]??{},l;try{l=await r(e,o,!n,s),n&&l.data?.errors?.some(e=>e.extensions?.code===`PERSISTED_QUERY_NOT_FOUND`)&&(l=await r(e,o,!0,s))}catch(n){throw n instanceof t?new i(e.name,n.status,[{message:n.message}],n.code===`invalid_json`?`BAD_RESPONSE`:`NETWORK`):n}let u=l.data,d=c(u?.errors)?u.errors:[],f=u?.data;if(typeof f!=`object`||!f)throw new i(e.name,l.status,d.length?d:[{message:`Empty GraphQL response`}],d.length?void 0:`BAD_RESPONSE`);if(d.length){let t=new Set(d.map(e=>e.path?.[0]).filter(e=>typeof e==`string`));if([...t].some(e=>f[e]===null)||!t.size)throw new i(e.name,l.status,d)}return f}return{request:a}}var u={id:`1ca49b34f117b7ac552c929bde0588899c760959aa9b79609f629786e0b10362`,name:`FlxAdminCapsRestore`,kind:`mutation`,plugin:`flx-admin`,document:`mutation FlxAdminCapsRestore($input: FlxAdminCapsRestoreInput!) {
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
}`},d={id:`e13320fd86a143dffd7f3d635d5c1d71e5fb878e8704d8d973d71573220d6105`,name:`FlxAdminCapsSave`,kind:`mutation`,plugin:`flx-admin`,document:`mutation FlxAdminCapsSave($input: FlxAdminCapsSaveInput!) {
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
}`},f={id:`0466b867177e171b259ec3667c8becb5d880f4519688510e3af1bbb8e6a7a427`,name:`FlxAdminCapsScreen`,kind:`query`,plugin:`flx-admin`,document:`query FlxAdminCapsScreen {
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
}`},_={id:`21a336969a0dc41b8caaa1be0a20b9bd740359347ff57bc8cb72f70ca6254f9e`,name:`FlxRoleCreate`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleCreate($input: FlxRoleCreateInput!) {
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
}`},v={id:`2bd5579ac1188eadb0dff5235840b66098c68b1b0d9e5d9a86c1a07b54d3acea`,name:`FlxRoleDelete`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleDelete($input: FlxRoleDeleteInput!) {
  flxRoleDelete(input: $input) {
    deletedKey
    errors {
      code
      message
      field
    }
  }
}`},y={id:`af09ea3f7594b6e37e1aa272f116e57dd7079ca275a754a8107fc230ce3014df`,name:`FlxRoleMemberAdd`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleMemberAdd($input: FlxRoleMemberAddInput!) {
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
}`},b={id:`6eab4d6f1f97457a8679a9b1dd92d47dc2e01a7f41163eff439dc854213b2a7c`,name:`FlxRoleMemberRemove`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleMemberRemove($input: FlxRoleMemberRemoveInput!) {
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
}`},x={id:`067ae709ce0675a0f8e8ab3ab7d21d722afa9f7449f358767be373133c98de6f`,name:`FlxRoleMembers`,kind:`query`,plugin:`flx-roles`,document:`query FlxRoleMembers($key: String!, $first: Int = 50, $after: String) {
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
}`},S={id:`20e6cdbb88f7639a8749e74cf75ef58a4ab704143f0cf0fa3474dbb2366cb1d0`,name:`FlxRoleReset`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleReset($input: FlxRoleResetInput!) {
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
}`},C={id:`0a4da13faad85546b92fab5b45716dab5b06e87791475a9dfaa4664888375658`,name:`FlxRolesSave`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRolesSave($input: FlxRolesSaveInput!) {
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
}`},w={id:`2c22565866bcebf0aaed510b75bec73444fc8257c8d2b327873e9c2963c59030`,name:`FlxRolesScreen`,kind:`query`,plugin:`flx-roles`,document:`query FlxRolesScreen($members: Int = 50) {
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
}`},T={id:`fca51d9372f393736cd5c7f3cc3a6c6f76bc42c714907f05360f59ce43283d8b`,name:`FlxTaskCommentCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCommentCreate($input: FlxTaskCommentCreateInput!) {
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
}`},E={id:`f4712b3032ffaf4580393cf0e6234154fa1eb66399b12784aa29f29b625d34bd`,name:`FlxTaskCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCreate($input: FlxTaskCreateInput!) {
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
}`},D={id:`157053946bdf55aa4bf5f22a676119ec48e7bbe04c053867b7e0eedb11e04ec2`,name:`FlxTaskDelete`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskDelete($input: FlxTaskDeleteInput!) {
  flxTaskDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},O={id:`fa1d3f71ca31ab2a7b72c1a987e295f209d6564e8a68e3643dee354a44544ecf`,name:`FlxTaskDetail`,kind:`query`,plugin:`flx-tasks`,document:`query FlxTaskDetail($id: ID!) {
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
}`},k={id:`d357c8fdb6c372508b17bb93fa22ec80224c0ff43854104149816d70a9e80870`,name:`FlxTaskNotificationsRead`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskNotificationsRead($input: FlxTaskNotificationsReadInput!) {
  flxTaskNotificationsRead(input: $input) {
    unread
    errors {
      code
      message
      field
    }
  }
}`},A={id:`004e85b9237b995957f75c2cfe2a996f5d2de6ca98b31c98511cb403df800ae3`,name:`FlxTaskUpdate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdate($input: FlxTaskUpdateInput!) {
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
}`},j={id:`7c644e53f296f0dbf859b7bfcb4748e8c5be3ef891474fb47a262a2cd993be7a`,name:`FlxTaskUpdateDetail`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdateDetail($input: FlxTaskUpdateInput!) {
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
}`},M={id:`a2c919c298da71f1634726a7c85c4344cf85f3602cbf54e4175bd8e45578e9b5`,name:`FlxTaskWatch`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskWatch($input: FlxTaskWatchInput!) {
  flxTaskWatch(input: $input) {
    watcherIds
    errors {
      code
      message
      field
    }
  }
}`};export{M as C,a as E,j as S,i as T,E as _,m as a,k as b,_ as c,b as d,x as f,T as g,w as h,p as i,v as l,C as m,d as n,h as o,S as p,f as r,g as s,u as t,y as u,D as v,l as w,A as x,O as y};