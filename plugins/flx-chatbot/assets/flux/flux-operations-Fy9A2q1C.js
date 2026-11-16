import{it as e,nt as t}from"./flux-host-Diq2Km_y.js";var n=new Set([`UNAUTHENTICATED`,`FORBIDDEN`,`NOT_FOUND`,`INVALID_INPUT`,`CONFLICT`,`RATE_LIMITED`,`INTERNAL`,`PERSISTED_QUERY_NOT_FOUND`,`QUERY_NOT_ALLOWED`,`NETWORK`,`BAD_RESPONSE`]),r={401:`UNAUTHENTICATED`,403:`FORBIDDEN`,404:`NOT_FOUND`,429:`RATE_LIMITED`},i=class extends Error{constructor(t,i,a,o){let s=a[0];super(s?.message||o||`GraphQL request failed`),e(this,`code`,void 0),e(this,`field`,void 0),e(this,`errors`,void 0),e(this,`status`,void 0),e(this,`operation`,void 0),this.name=`FlxGraphQLError`,this.operation=t,this.status=i,this.errors=a;let c=s?.extensions?.code;this.code=c&&n.has(c)?c:o??r[i]??`INTERNAL`,this.field=typeof s?.extensions?.field==`string`?s.extensions.field:void 0}},a=e=>e instanceof i,o=`../graphql`,s=2e3,c=e=>Array.isArray(e)&&e.every(e=>typeof e==`object`&&!!e&&typeof e.message==`string`);function l(e){let n=e.persisted!==!1;async function r(t,n,r,i){let a=n&&typeof n==`object`&&Object.keys(n).length?n:void 0,c={raw:!0,throwOnError:!1,signal:i.signal,headers:{"X-Flux-GraphQL-Operation":t.name,...i.headers}};if(t.kind===`query`&&!r){let n={queryId:t.id,operationName:t.name,variables:a?JSON.stringify(a):void 0};if(Object.values(n).reduce((e,t)=>e+(t?encodeURIComponent(t).length+16:0),0)<s)return e.api(o,{...c,method:`GET`,query:n})}let l=r?{query:t.document,operationName:t.name,variables:a}:{queryId:t.id,operationName:t.name,variables:a};return e.api(o,{...c,method:`POST`,body:l})}async function a(e,...a){let o=a[0],s=a[1]??{},l;try{l=await r(e,o,!n,s),n&&l.data?.errors?.some(e=>e.extensions?.code===`PERSISTED_QUERY_NOT_FOUND`)&&(l=await r(e,o,!0,s))}catch(n){throw n instanceof t?new i(e.name,n.status,[{message:n.message}],n.code===`invalid_json`?`BAD_RESPONSE`:`NETWORK`):n}let u=l.data,d=c(u?.errors)?u.errors:[],f=u?.data;if(typeof f!=`object`||!f)throw new i(e.name,l.status,d.length?d:[{message:`Empty GraphQL response`}],d.length?void 0:`BAD_RESPONSE`);if(d.length){let t=new Set(d.map(e=>e.path?.[0]).filter(e=>typeof e==`string`));if([...t].some(e=>f[e]===null)||!t.size)throw new i(e.name,l.status,d)}return f}return{request:a}}var u={id:`edb4984f25c406a9143913da2ef715d97887a74bbacdcda82da3f7a8c9707ca2`,name:`FlxChatbotConversationClose`,kind:`mutation`,plugin:`flx-chatbot`,document:`mutation FlxChatbotConversationClose($input: FlxChatbotConversationCloseInput!) {
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
}`},d={id:`06f77cf96adcd58c306be0a2123151c07f7b20c102c675ac503f4e0f2193d3aa`,name:`FlxChatbotConversationDelete`,kind:`mutation`,plugin:`flx-chatbot`,document:`mutation FlxChatbotConversationDelete($input: FlxChatbotConversationDeleteInput!) {
  flxChatbotConversationDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},f={id:`0159e5c0272f4a76eb58d3908ee9e42355887bca6384a602ca16fa3ded368fb7`,name:`FlxChatbotConversationList`,kind:`query`,plugin:`flx-chatbot`,document:`query FlxChatbotConversationList($filter: FlxChatbotConversationFilter, $search: String, $first: Int = 50) {
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
}`},p={id:`c22dcbbc9132d33808d9995d8744230f8914601afae91ff9f71b5aa8e40f71f4`,name:`FlxChatbotConversationOpen`,kind:`query`,plugin:`flx-chatbot`,document:`query FlxChatbotConversationOpen($id: ID!, $afterId: Int = 0, $peek: Boolean = false) {
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
}`},m={id:`019b8ae4942af3eb50b362f80c7946ad71090cbad2a0b11a8383c6dc969665f8`,name:`FlxChatbotConversationReply`,kind:`mutation`,plugin:`flx-chatbot`,document:`mutation FlxChatbotConversationReply($input: FlxChatbotConversationReplyInput!) {
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
}`},h={id:`4b73e1d9351052ac89be5c9f77fe281fc9c69498e55c1b83e984e65c49ddcafd`,name:`FlxChatbotConversationTyping`,kind:`mutation`,plugin:`flx-chatbot`,document:`mutation FlxChatbotConversationTyping($input: FlxChatbotConversationTypingInput!) {
  flxChatbotConversationTyping(input: $input) {
    ok
  }
}`},g={id:`2578214d2427732b0bca1b77938018e459d2b57a4a4b50fab18baa2c9c90171f`,name:`FlxChatbotInbox`,kind:`query`,plugin:`flx-chatbot`,document:`query FlxChatbotInbox {
  flxChatbotInbox {
    fluxChatUrl
    settingsUrl
    canDelete
    agentName
    maxUploadMb
  }
}`},_={id:`58380f4eafb8b23ee0ed48cc1b49d596c5a05fda9b8cce508d5cc3a3ebcd0b4c`,name:`FlxChatbotSettingsSave`,kind:`mutation`,plugin:`flx-chatbot`,document:`mutation FlxChatbotSettingsSave($input: FlxChatbotSettingsSaveInput!) {
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
}`},v={id:`c116623608453060a2014d87ec9efcc566c9956ce079c536f86803f37e4f56e7`,name:`FlxChatbotSettingsScreen`,kind:`query`,plugin:`flx-chatbot`,document:`query FlxChatbotSettingsScreen($days: Int = 30) {
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
}`},y={id:`21a336969a0dc41b8caaa1be0a20b9bd740359347ff57bc8cb72f70ca6254f9e`,name:`FlxRoleCreate`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleCreate($input: FlxRoleCreateInput!) {
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
}`},b={id:`2bd5579ac1188eadb0dff5235840b66098c68b1b0d9e5d9a86c1a07b54d3acea`,name:`FlxRoleDelete`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleDelete($input: FlxRoleDeleteInput!) {
  flxRoleDelete(input: $input) {
    deletedKey
    errors {
      code
      message
      field
    }
  }
}`},x={id:`af09ea3f7594b6e37e1aa272f116e57dd7079ca275a754a8107fc230ce3014df`,name:`FlxRoleMemberAdd`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleMemberAdd($input: FlxRoleMemberAddInput!) {
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
}`},S={id:`6eab4d6f1f97457a8679a9b1dd92d47dc2e01a7f41163eff439dc854213b2a7c`,name:`FlxRoleMemberRemove`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleMemberRemove($input: FlxRoleMemberRemoveInput!) {
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
}`},C={id:`067ae709ce0675a0f8e8ab3ab7d21d722afa9f7449f358767be373133c98de6f`,name:`FlxRoleMembers`,kind:`query`,plugin:`flx-roles`,document:`query FlxRoleMembers($key: String!, $first: Int = 50, $after: String) {
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
}`},w={id:`20e6cdbb88f7639a8749e74cf75ef58a4ab704143f0cf0fa3474dbb2366cb1d0`,name:`FlxRoleReset`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleReset($input: FlxRoleResetInput!) {
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
}`},T={id:`0a4da13faad85546b92fab5b45716dab5b06e87791475a9dfaa4664888375658`,name:`FlxRolesSave`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRolesSave($input: FlxRolesSaveInput!) {
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
}`},E={id:`2c22565866bcebf0aaed510b75bec73444fc8257c8d2b327873e9c2963c59030`,name:`FlxRolesScreen`,kind:`query`,plugin:`flx-roles`,document:`query FlxRolesScreen($members: Int = 50) {
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
}`},D={id:`fca51d9372f393736cd5c7f3cc3a6c6f76bc42c714907f05360f59ce43283d8b`,name:`FlxTaskCommentCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCommentCreate($input: FlxTaskCommentCreateInput!) {
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
}`},O={id:`f4712b3032ffaf4580393cf0e6234154fa1eb66399b12784aa29f29b625d34bd`,name:`FlxTaskCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCreate($input: FlxTaskCreateInput!) {
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
}`},k={id:`157053946bdf55aa4bf5f22a676119ec48e7bbe04c053867b7e0eedb11e04ec2`,name:`FlxTaskDelete`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskDelete($input: FlxTaskDeleteInput!) {
  flxTaskDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},A={id:`fa1d3f71ca31ab2a7b72c1a987e295f209d6564e8a68e3643dee354a44544ecf`,name:`FlxTaskDetail`,kind:`query`,plugin:`flx-tasks`,document:`query FlxTaskDetail($id: ID!) {
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
}`},j={id:`d357c8fdb6c372508b17bb93fa22ec80224c0ff43854104149816d70a9e80870`,name:`FlxTaskNotificationsRead`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskNotificationsRead($input: FlxTaskNotificationsReadInput!) {
  flxTaskNotificationsRead(input: $input) {
    unread
    errors {
      code
      message
      field
    }
  }
}`},M={id:`004e85b9237b995957f75c2cfe2a996f5d2de6ca98b31c98511cb403df800ae3`,name:`FlxTaskUpdate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdate($input: FlxTaskUpdateInput!) {
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
}`},N={id:`7c644e53f296f0dbf859b7bfcb4748e8c5be3ef891474fb47a262a2cd993be7a`,name:`FlxTaskUpdateDetail`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdateDetail($input: FlxTaskUpdateInput!) {
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
}`},P={id:`a2c919c298da71f1634726a7c85c4344cf85f3602cbf54e4175bd8e45578e9b5`,name:`FlxTaskWatch`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskWatch($input: FlxTaskWatchInput!) {
  flxTaskWatch(input: $input) {
    watcherIds
    errors {
      code
      message
      field
    }
  }
}`};export{M as C,i as D,l as E,a as O,j as S,P as T,E as _,m as a,k as b,_ as c,b as d,x as f,T as g,w as h,p as i,v as l,C as m,d as n,h as o,S as p,f as r,g as s,u as t,y as u,D as v,N as w,A as x,O as y};