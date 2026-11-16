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
}`},v={id:`ac5bddd734508bac19a20d5d020bdbd2ec57e3dd9b351b65d12e009a3cf72068`,name:`FlxSocialAccountDelete`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialAccountDelete($input: FlxSocialAccountDeleteInput!) {
  flxSocialAccountDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},y={id:`9bec0a5cfe1a3cb3321b0913feb2586f8948d0fb81cdfa85c46c0d6c1d380492`,name:`FlxSocialAccountRefresh`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialAccountRefresh($input: FlxSocialAccountRefreshInput!) {
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
}`},b={id:`fb8b8e12765fec1841e78ad598cbabafad6ad06e26ae41571b78081081bb35d0`,name:`FlxSocialAccountReorder`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialAccountReorder($input: FlxSocialAccountReorderInput!) {
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
}`},x={id:`ddde3fb7af5fc653690c8d162de6a4a70f367d608c25e10e234f43d2b2eeeecb`,name:`FlxSocialAccountUpdate`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialAccountUpdate($input: FlxSocialAccountUpdateInput!) {
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
}`},S={id:`3d420b1c8992d44b15a62c551e463f57a3ff6e9cb8a480d28e5ba6d956724b5a`,name:`FlxSocialAiWrite`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialAiWrite($input: FlxSocialAiWriteInput!) {
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
}`},C={id:`d152736fe8890c423eaa3d4481b02a64c2827c46b46be9b2f4111b313b3411e3`,name:`FlxSocialAppUpdate`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialAppUpdate($input: FlxSocialAppUpdateInput!) {
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
}`},w={id:`777e5e347a4e283420534a0c3123fcc967ba15ca3e8799e50a3dfd4105fdde68`,name:`FlxSocialBoot`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialBoot {
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
}`},T={id:`7a97a59e91842fa3a8db49e219db99e0cc8df12bccaf04637a5cb01abd82b25f`,name:`FlxSocialConnectUrl`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialConnectUrl($for: String!) {
  flxSocialConnectUrl(for: $for)
}`},E={id:`cefaf931508b3dfca6c7addc0f5c6e7e55091746577f16dcb1de6270fa7606ce`,name:`FlxSocialCounts`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialCounts {
  flxSocialCounts {
    pending
    scheduled
    failed
    due
  }
}`},D={id:`05849f5c15939169ff6d203b2ef91e4c8467f528073099294c6c4799af4422fe`,name:`FlxSocialLog`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialLog {
  flxSocialLog {
    databaseId
    time {
      timestamp
    }
    level
    accountId
    message
  }
}`},O={id:`ef5f39879b17171c3946b6fe7f72b3ec1a072e805cecc6ab5ead805f804ffd5d`,name:`FlxSocialMedia`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialMedia($ids: [Int!], $search: String, $page: Int) {
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
}`},k={id:`65c5355402f955e3c229ad9d455e6c81023d21f910725625c69d71b1676e95c6`,name:`FlxSocialMediaAdd`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialMediaAdd($input: FlxSocialMediaAddInput!) {
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
}`},A={id:`6be8bb6227b0ea3f5fb3a6b4cf402977622023b1544fde917e0bd16c72506afd`,name:`FlxSocialMediaImport`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialMediaImport($input: FlxSocialMediaImportInput!) {
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
}`},j={id:`17e002c255a2a86aa0d738a5603ec081a9c987125aa1ac32b6cbcd4ae376faff`,name:`FlxSocialPost`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialPost($id: ID!) {
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
}`},M={id:`bad63e76be4a72da349259fd37da2b216637760e2043864da9ba72b10c722535`,name:`FlxSocialPostAct`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialPostAct($input: FlxSocialPostActInput!) {
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
}`},N={id:`9198337975631125c80b4d3144ff8c95c87f78bcced0a1239a914dfec0fe9053`,name:`FlxSocialPostBulkAct`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialPostBulkAct($input: FlxSocialPostBulkActInput!) {
  flxSocialPostBulkAct(input: $input) {
    done
    issues
    errors {
      code
      message
      field
    }
  }
}`},ee={id:`6fbb3d188ae4f3ac1afb8bdb803de5afa89ad9d81f4597a97c0c5bf752c43cfc`,name:`FlxSocialPostCreate`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialPostCreate($input: FlxSocialPostCreateInput!) {
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
}`},P={id:`501365bb59297864329d808e41b73469e0fca66f789c2838584ee8464787ae48`,name:`FlxSocialPostDelete`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialPostDelete($input: FlxSocialPostDeleteInput!) {
  flxSocialPostDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},F={id:`4043b0e0cad9ce1b24b08e084041e854742976d792fce3aed127b5e39b3d7076`,name:`FlxSocialPostDuplicate`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialPostDuplicate($input: FlxSocialPostDuplicateInput!) {
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
}`},I={id:`96bfd9683efd78e4c882e801d09d73f2d9600d382fa9c094fc0697b135507705`,name:`FlxSocialPosts`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialPosts($first: Int, $after: String, $status: [FlxSocialPostStatus!], $accountId: Int, $provider: String, $search: String, $tag: String, $mine: Boolean, $from: Int, $to: Int, $ascending: Boolean) {
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
}`},L={id:`b1d81348826f953c3c5c4c13a46de500f7862d6edb2e50b5d892b2b4b9f85d07`,name:`FlxSocialPostUpdate`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialPostUpdate($input: FlxSocialPostUpdateInput!) {
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
}`},R={id:`6a244ceffd24ffbaa730873fc84b76c1943f19b81f949f48ee591584d5450a38`,name:`FlxSocialSettings`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialSettings {
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
}`},z={id:`dc8548114e3ee4319fb5256091aa79de47ea2bf608ebc84792773869110d7c11`,name:`FlxSocialSettingsUpdate`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialSettingsUpdate($input: FlxSocialSettingsUpdateInput!) {
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
}`},B={id:`f751908be074aa0bf4d1dd045535dd240d0474b9eb994dc13d20449a847acf40`,name:`FlxSocialStats`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialStats($days: Int) {
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
}`},V={id:`cec3bd8f7f67ca6ade378ae4c96239c14fb729e6acb36deaae2f619716abbff3`,name:`FlxSocialStatsRefresh`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialStatsRefresh($input: FlxSocialStatsRefreshInput!) {
  flxSocialStatsRefresh(input: $input) {
    updated
    errors {
      code
      message
      field
    }
  }
}`},H={id:`66b1e332cd2d0ad55c6d27fe7d60d1b50aadd3cbaa44a561e8029892cd063639`,name:`FlxSocialStudioMedia`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialStudioMedia {
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
}`},U={id:`b7859e5459cd00b1b2e64810c3eaf209d3a9773569d2667851f5f82371cf11f1`,name:`FlxSocialTargetRemoteDelete`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialTargetRemoteDelete($input: FlxSocialTargetRemoteDeleteInput!) {
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
}`},W={id:`5badf33fcf805a44c7c661263f28a611ea7d53e8fd03fecfad7829a2c4c916b6`,name:`FlxSocialTargetRemoteEdit`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialTargetRemoteEdit($input: FlxSocialTargetRemoteEditInput!) {
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
}`},G={id:`c28e9e66d529c5b571ac1c2184279c81c13ac6e28ed1d93b5bdafa8872a1354f`,name:`FlxSocialTargetRetry`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialTargetRetry($input: FlxSocialTargetRetryInput!) {
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
}`},K={id:`f8d604a8bbf847a49e9b83a886cc1f5c1f6bc02be42716dd0ad361dacdfdd65f`,name:`FlxSocialTickKeyReveal`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialTickKeyReveal($input: FlxSocialTickKeyRevealInput!) {
  flxSocialTickKeyReveal(input: $input) {
    url
    errors {
      code
      message
      field
    }
  }
}`},q={id:`df8b65ba6af7222044b383bcd58eb659a7dbcfc1aef6943b475750d6f51562a2`,name:`FlxSocialTickRun`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialTickRun($input: FlxSocialTickRunInput!) {
  flxSocialTickRun(input: $input) {
    processed
    due
    errors {
      code
      message
      field
    }
  }
}`},J={id:`1431f1fc9a3b21f5302c2d3cb267ddc62497d15d14a8c8ea57ce7cb8070d5cd3`,name:`FlxSocialWhatsAppAdd`,kind:`mutation`,plugin:`flx-social`,document:`mutation FlxSocialWhatsAppAdd($input: FlxSocialWhatsAppAddInput!) {
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
}`},Y={id:`770bd0bc66b661e8dbf40c7acceacf1953312994effd37432fcfc1edf5ed5bf4`,name:`FlxSocialWhatsAppTargets`,kind:`query`,plugin:`flx-social`,document:`query FlxSocialWhatsAppTargets {
  flxSocialWhatsAppTargets {
    extId
    name
    kind
    avatar
    exists
  }
}`},X={id:`fca51d9372f393736cd5c7f3cc3a6c6f76bc42c714907f05360f59ce43283d8b`,name:`FlxTaskCommentCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCommentCreate($input: FlxTaskCommentCreateInput!) {
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
}`},Z={id:`f4712b3032ffaf4580393cf0e6234154fa1eb66399b12784aa29f29b625d34bd`,name:`FlxTaskCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCreate($input: FlxTaskCreateInput!) {
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
}`},Q={id:`157053946bdf55aa4bf5f22a676119ec48e7bbe04c053867b7e0eedb11e04ec2`,name:`FlxTaskDelete`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskDelete($input: FlxTaskDeleteInput!) {
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
}`},te={id:`d357c8fdb6c372508b17bb93fa22ec80224c0ff43854104149816d70a9e80870`,name:`FlxTaskNotificationsRead`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskNotificationsRead($input: FlxTaskNotificationsReadInput!) {
  flxTaskNotificationsRead(input: $input) {
    unread
    errors {
      code
      message
      field
    }
  }
}`},ne={id:`004e85b9237b995957f75c2cfe2a996f5d2de6ca98b31c98511cb403df800ae3`,name:`FlxTaskUpdate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdate($input: FlxTaskUpdateInput!) {
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
}`},re={id:`7c644e53f296f0dbf859b7bfcb4748e8c5be3ef891474fb47a262a2cd993be7a`,name:`FlxTaskUpdateDetail`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdateDetail($input: FlxTaskUpdateInput!) {
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
}`},ie={id:`a2c919c298da71f1634726a7c85c4344cf85f3602cbf54e4175bd8e45578e9b5`,name:`FlxTaskWatch`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskWatch($input: FlxTaskWatchInput!) {
  flxTaskWatch(input: $input) {
    watcherIds
    errors {
      code
      message
      field
    }
  }
}`};export{R as A,J as B,M as C,F as D,P as E,U as F,$ as G,X as H,W as I,re as J,te as K,G as L,B as M,V as N,L as O,H as P,a as Q,K as R,j as S,ee as T,Z as U,Y as V,Q as W,l as X,ie as Y,i as Z,E as _,m as a,k as b,_ as c,b as d,x as f,T as g,w as h,p as i,z as j,I as k,v as l,C as m,d as n,h as o,S as p,ne as q,f as r,g as s,u as t,y as u,D as v,N as w,A as x,O as y,q as z};