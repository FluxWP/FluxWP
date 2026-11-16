import{g as e,m as t}from"./flux-host-CO0v48SL.js";var n=new Set([`UNAUTHENTICATED`,`FORBIDDEN`,`NOT_FOUND`,`INVALID_INPUT`,`CONFLICT`,`RATE_LIMITED`,`INTERNAL`,`PERSISTED_QUERY_NOT_FOUND`,`QUERY_NOT_ALLOWED`,`NETWORK`,`BAD_RESPONSE`]),r={401:`UNAUTHENTICATED`,403:`FORBIDDEN`,404:`NOT_FOUND`,429:`RATE_LIMITED`},i=class extends Error{constructor(t,i,a,o){let s=a[0];super(s?.message||o||`GraphQL request failed`),e(this,`code`,void 0),e(this,`field`,void 0),e(this,`errors`,void 0),e(this,`status`,void 0),e(this,`operation`,void 0),this.name=`FlxGraphQLError`,this.operation=t,this.status=i,this.errors=a;let c=s?.extensions?.code;this.code=c&&n.has(c)?c:o??r[i]??`INTERNAL`,this.field=typeof s?.extensions?.field==`string`?s.extensions.field:void 0}},a=e=>e instanceof i,o=`../graphql`,s=2e3,c=e=>Array.isArray(e)&&e.every(e=>typeof e==`object`&&!!e&&typeof e.message==`string`);function l(e){let n=e.persisted!==!1;async function r(t,n,r,i){let a=n&&typeof n==`object`&&Object.keys(n).length?n:void 0,c={raw:!0,throwOnError:!1,signal:i.signal,headers:{"X-Flux-GraphQL-Operation":t.name,...i.headers}};if(t.kind===`query`&&!r){let n={queryId:t.id,operationName:t.name,variables:a?JSON.stringify(a):void 0};if(Object.values(n).reduce((e,t)=>e+(t?encodeURIComponent(t).length+16:0),0)<s)return e.api(o,{...c,method:`GET`,query:n})}let l=r?{query:t.document,operationName:t.name,variables:a}:{queryId:t.id,operationName:t.name,variables:a};return e.api(o,{...c,method:`POST`,body:l})}async function a(e,...a){let o=a[0],s=a[1]??{},l;try{l=await r(e,o,!n,s),n&&l.data?.errors?.some(e=>e.extensions?.code===`PERSISTED_QUERY_NOT_FOUND`)&&(l=await r(e,o,!0,s))}catch(n){throw n instanceof t?new i(e.name,n.status,[{message:n.message}],n.code===`invalid_json`?`BAD_RESPONSE`:`NETWORK`):n}let u=l.data,d=c(u?.errors)?u.errors:[],f=u?.data;if(typeof f!=`object`||!f)throw new i(e.name,l.status,d.length?d:[{message:`Empty GraphQL response`}],d.length?void 0:`BAD_RESPONSE`);if(d.length){let t=new Set(d.map(e=>e.path?.[0]).filter(e=>typeof e==`string`));if([...t].some(e=>f[e]===null)||!t.size)throw new i(e.name,l.status,d)}return f}return{request:a}}var u={id:`21a336969a0dc41b8caaa1be0a20b9bd740359347ff57bc8cb72f70ca6254f9e`,name:`FlxRoleCreate`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleCreate($input: FlxRoleCreateInput!) {
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
}`},v={id:`7e536f64c2f2071ee080da4c303e3ff3df331d4ee5454e2d32e6ca2cfa558491`,name:`FlxStudioBrainstorm`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioBrainstorm($input: FlxStudioBrainstormInput!) {
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
}`},y={id:`164e3eaaab653626f4560a3b3c33be0334bea85986ca1777daa2d7b6bd689aab`,name:`FlxStudioCancel`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioCancel($input: FlxStudioCancelInput!) {
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
}`},b={id:`412a1cf0eb420e33030acdc395810cb1317af6d7530bddff047baab4845ff908`,name:`FlxStudioEnhance`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioEnhance($input: FlxStudioEnhanceInput!) {
  flxStudioEnhance(input: $input) {
    prompt
    errors {
      code
      message
      field
    }
  }
}`},x={id:`93c9aa7f8a19fc0358f9c0f5fce98107b3cb6406d5c75a3e23d35bafc2030270`,name:`FlxStudioFolderCreate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioFolderCreate($input: FlxStudioFolderCreateInput!) {
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
}`},S={id:`9ecd95b4c0e16c01a049c04b82f79013ad067a1dd99cd5a8186be641a6f36288`,name:`FlxStudioFolderDelete`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioFolderDelete($input: FlxStudioFolderDeleteInput!) {
  flxStudioFolderDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},C={id:`ac9f3ba4ecb20b0da721b057651d3f6d4670fade63fa567ffff21a55bbfa1e4f`,name:`FlxStudioFolderUpdate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioFolderUpdate($input: FlxStudioFolderUpdateInput!) {
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
}`},w={id:`9bc13e2c994ded586d4eee73ff381a8d1e21c82a079689d5e02053485353496a`,name:`FlxStudioGenerate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioGenerate($input: FlxStudioGenerateInput!) {
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
}`},T={id:`639f7d0675693c31cb95b48aabe5b674671e4f6df5db262ee98e9354c4f2941f`,name:`FlxStudioItems`,kind:`query`,plugin:`flx-studio`,document:`query FlxStudioItems($first: Int = 60, $after: String, $folderId: Int, $kind: FlxStudioKind, $favorite: Boolean, $batch: String, $search: String, $allOwners: Boolean) {
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
}`},E={id:`5c12139fff325a5d541ffe31a43bbdab23d6b3e372fff9323d164547a5d0c353`,name:`FlxStudioItemsDelete`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioItemsDelete($input: FlxStudioItemsDeleteInput!) {
  flxStudioItemsDelete(input: $input) {
    deletedIds
    errors {
      code
      message
      field
    }
  }
}`},D={id:`ed4c508fa39d29a80a706703e8aecf3b0830064acc79dc3824204f955ee94ae1`,name:`FlxStudioItemsMove`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioItemsMove($input: FlxStudioItemsMoveInput!) {
  flxStudioItemsMove(input: $input) {
    movedIds
    errors {
      code
      message
      field
    }
  }
}`},O={id:`b6814f6729f348c9e0b952a2b4487db6976a118af605e0a4a6f300163c33332e`,name:`FlxStudioItemToLibrary`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioItemToLibrary($input: FlxStudioItemToLibraryInput!) {
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
}`},k={id:`82ed1fcbf3ca9509636cc0cc2917dc945f9c446c42ee0461571e8e6826727144`,name:`FlxStudioItemUpdate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioItemUpdate($input: FlxStudioItemUpdateInput!) {
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
}`},A={id:`df8d9048b9a909150a5b758644ccfaae13f920a46ff85df0a5de0a5edef196d5`,name:`FlxStudioPersonCreate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioPersonCreate($input: FlxStudioPersonCreateInput!) {
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
}`},j={id:`715fd6d84c8d95994b9cf336dfa63288931d4f28adc31c3b5e0074cba19aa55c`,name:`FlxStudioPersonDelete`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioPersonDelete($input: FlxStudioPersonDeleteInput!) {
  flxStudioPersonDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},M={id:`b929a0c4365da430782991c15105207e5722bfaa9c23583e7535c6a823c9b8a8`,name:`FlxStudioPersonRename`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioPersonRename($input: FlxStudioPersonRenameInput!) {
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
}`},N={id:`8592ab98d0e723bac97426d1c9853126e94192f67de8787c77cf8a65a5707321`,name:`FlxStudioPoll`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioPoll($input: FlxStudioPollInput!) {
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
}`},P={id:`076f0f83b765ad61ba0d1034777af88df4d15742683110a1390fc3320e815248`,name:`FlxStudioRefCreate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioRefCreate($input: FlxStudioRefCreateInput!) {
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
}`},F={id:`4cc7a8b993dd67b840279ee0ecdb4ad0059d3de9fb975053551e0907c9b35778`,name:`FlxStudioRefDelete`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioRefDelete($input: FlxStudioRefDeleteInput!) {
  flxStudioRefDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},I={id:`7bcbc27e22f195e5ce14ab38eedd554807ce1e4f5d27c4bd6837a7f823fed355`,name:`FlxStudioRefUpdate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioRefUpdate($input: FlxStudioRefUpdateInput!) {
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
}`},L={id:`5658cd768af0cfcae70f66f69eab5a1f47657f446c922a166dcb4249182de2e6`,name:`FlxStudioSettingsUpdate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioSettingsUpdate($input: FlxStudioSettingsUpdateInput!) {
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
}`},R={id:`2b2f30d9b3b3929647767a5232da353836f4ffcc9bab3945162b4237ad8aa50a`,name:`FlxStudioStats`,kind:`query`,plugin:`flx-studio`,document:`query FlxStudioStats($days: Int = 30) {
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
}`},z={id:`f3d78587f86d7f71592a658e92d7281ccb4626ba12a84ac511a444c7cd8df6ba`,name:`FlxStudioTemplateCreate`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioTemplateCreate($input: FlxStudioTemplateCreateInput!) {
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
}`},B={id:`ddb8a4be1c12b292124e41747f467cc316dbb805cd3949d366715dabf5be628e`,name:`FlxStudioTemplateDelete`,kind:`mutation`,plugin:`flx-studio`,document:`mutation FlxStudioTemplateDelete($input: FlxStudioTemplateDeleteInput!) {
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
}`},V={id:`2bacebe6d3f0fa55575f79f7abb0d22d42c7def0e3bf004c11657ff248dd3bcc`,name:`FlxStudioWorkspace`,kind:`query`,plugin:`flx-studio`,document:`query FlxStudioWorkspace {
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
}`},H={id:`fca51d9372f393736cd5c7f3cc3a6c6f76bc42c714907f05360f59ce43283d8b`,name:`FlxTaskCommentCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCommentCreate($input: FlxTaskCommentCreateInput!) {
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
}`},U={id:`f4712b3032ffaf4580393cf0e6234154fa1eb66399b12784aa29f29b625d34bd`,name:`FlxTaskCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCreate($input: FlxTaskCreateInput!) {
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
}`},W={id:`157053946bdf55aa4bf5f22a676119ec48e7bbe04c053867b7e0eedb11e04ec2`,name:`FlxTaskDelete`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskDelete($input: FlxTaskDeleteInput!) {
  flxTaskDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},G={id:`fa1d3f71ca31ab2a7b72c1a987e295f209d6564e8a68e3643dee354a44544ecf`,name:`FlxTaskDetail`,kind:`query`,plugin:`flx-tasks`,document:`query FlxTaskDetail($id: ID!) {
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
}`},K={id:`d357c8fdb6c372508b17bb93fa22ec80224c0ff43854104149816d70a9e80870`,name:`FlxTaskNotificationsRead`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskNotificationsRead($input: FlxTaskNotificationsReadInput!) {
  flxTaskNotificationsRead(input: $input) {
    unread
    errors {
      code
      message
      field
    }
  }
}`},q={id:`004e85b9237b995957f75c2cfe2a996f5d2de6ca98b31c98511cb403df800ae3`,name:`FlxTaskUpdate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdate($input: FlxTaskUpdateInput!) {
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
}`},J={id:`7c644e53f296f0dbf859b7bfcb4748e8c5be3ef891474fb47a262a2cd993be7a`,name:`FlxTaskUpdateDetail`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdateDetail($input: FlxTaskUpdateInput!) {
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
}`},Y={id:`a2c919c298da71f1634726a7c85c4344cf85f3602cbf54e4175bd8e45578e9b5`,name:`FlxTaskWatch`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskWatch($input: FlxTaskWatchInput!) {
  flxTaskWatch(input: $input) {
    watcherIds
    errors {
      code
      message
      field
    }
  }
}`};export{z as A,Y as B,M as C,I as D,F as E,W as F,i as H,G as I,K as L,V as M,H as N,L as O,U as P,q as R,j as S,P as T,a as U,l as V,k as _,m as a,D as b,_ as c,b as d,x as f,O as g,w as h,p as i,B as j,R as k,v as l,C as m,d as n,h as o,S as p,f as r,g as s,u as t,y as u,T as v,N as w,A as x,E as y,J as z};