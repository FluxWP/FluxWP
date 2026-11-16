import{J as e,K as t}from"./flux-host-CfpxPfYo.js";var n=new Set([`UNAUTHENTICATED`,`FORBIDDEN`,`NOT_FOUND`,`INVALID_INPUT`,`CONFLICT`,`RATE_LIMITED`,`INTERNAL`,`PERSISTED_QUERY_NOT_FOUND`,`QUERY_NOT_ALLOWED`,`NETWORK`,`BAD_RESPONSE`]),r={401:`UNAUTHENTICATED`,403:`FORBIDDEN`,404:`NOT_FOUND`,429:`RATE_LIMITED`},i=class extends Error{constructor(t,i,a,o){let s=a[0];super(s?.message||o||`GraphQL request failed`),e(this,`code`,void 0),e(this,`field`,void 0),e(this,`errors`,void 0),e(this,`status`,void 0),e(this,`operation`,void 0),this.name=`FlxGraphQLError`,this.operation=t,this.status=i,this.errors=a;let c=s?.extensions?.code;this.code=c&&n.has(c)?c:o??r[i]??`INTERNAL`,this.field=typeof s?.extensions?.field==`string`?s.extensions.field:void 0}},a=e=>e instanceof i,o=`../graphql`,s=2e3,c=e=>Array.isArray(e)&&e.every(e=>typeof e==`object`&&!!e&&typeof e.message==`string`);function l(e){let n=e.persisted!==!1;async function r(t,n,r,i){let a=n&&typeof n==`object`&&Object.keys(n).length?n:void 0,c={raw:!0,throwOnError:!1,signal:i.signal,headers:{"X-Flux-GraphQL-Operation":t.name,...i.headers}};if(t.kind===`query`&&!r){let n={queryId:t.id,operationName:t.name,variables:a?JSON.stringify(a):void 0};if(Object.values(n).reduce((e,t)=>e+(t?encodeURIComponent(t).length+16:0),0)<s)return e.api(o,{...c,method:`GET`,query:n})}let l=r?{query:t.document,operationName:t.name,variables:a}:{queryId:t.id,operationName:t.name,variables:a};return e.api(o,{...c,method:`POST`,body:l})}async function a(e,...a){let o=a[0],s=a[1]??{},l;try{l=await r(e,o,!n,s),n&&l.data?.errors?.some(e=>e.extensions?.code===`PERSISTED_QUERY_NOT_FOUND`)&&(l=await r(e,o,!0,s))}catch(n){throw n instanceof t?new i(e.name,n.status,[{message:n.message}],n.code===`invalid_json`?`BAD_RESPONSE`:`NETWORK`):n}let u=l.data,d=c(u?.errors)?u.errors:[],f=u?.data;if(typeof f!=`object`||!f)throw new i(e.name,l.status,d.length?d:[{message:`Empty GraphQL response`}],d.length?void 0:`BAD_RESPONSE`);if(d.length){let t=new Set(d.map(e=>e.path?.[0]).filter(e=>typeof e==`string`));if([...t].some(e=>f[e]===null)||!t.size)throw new i(e.name,l.status,d)}return f}return{request:a}}var u={id:`61dca741b4cda6ec8f60505b8548df2e5d33c72546464ffa58e107cc85ba83d5`,name:`FlxFilesAiEdit`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesAiEdit($input: FlxFilesAiEditInput!) {
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
}`},d={id:`32abcee8e0a969a62b439f52fbce12c71c847005925cfae133f29ebb40068a31`,name:`FlxFilesAiExplain`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesAiExplain($input: FlxFilesAiExplainInput!) {
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
}`},f={id:`11c4bcaba7b6a88fc32514cdc6c72fa7eddee29ae51a30d924c9ef337ae01482`,name:`FlxFilesBootstrap`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesBootstrap {
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
}`},p={id:`4f023bb510e149c91f1629825589973c85cae4835fa812ad549677ba6980d5bc`,name:`FlxFilesChmod`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesChmod($input: FlxFilesChmodInput!) {
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
}`},m={id:`f68b3adcc3c580e155d2613c5ebd32eda97074945cf130cd3b0c3aa8b15796e9`,name:`FlxFilesCopy`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesCopy($input: FlxFilesCopyInput!) {
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
}`},h={id:`9a932876948ac21226ac489170974c79b8567a55bd985e88b64f69a0f76baee6`,name:`FlxFilesDisk`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesDisk($refresh: Boolean) {
  flxFilesDisk(refresh: $refresh) {
    usedBytes
    totalBytes
    fileCount
    measured
    scanning
  }
}`},g={id:`f669e131af94b4ab873afbea5a686bef1d50491e121c61abf6d622f7de7ee944`,name:`FlxFilesDuplicate`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesDuplicate($input: FlxFilesDuplicateInput!) {
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
}`},_={id:`68f93e146bd77ed04fea958b274e19984ff9fea5474d5c1818fea7a00976d3aa`,name:`FlxFilesFile`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesFile($path: String!) {
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
}`},v={id:`eff7fe14bc929cb4523e5cc13b4c9944073068de7ed0704080b8676ab83e75da`,name:`FlxFilesFolder`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesFolder($path: String!, $first: Int = 500, $after: String, $sort: FlxFilesSort, $order: FlxFilesOrder, $hidden: Boolean, $foldersOnly: Boolean, $countItems: Boolean) {
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
}`},y={id:`3df09f77ce66415c30bf3f11448063018063298f8afe8693354151f339789a2d`,name:`FlxFilesFolderCreate`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesFolderCreate($input: FlxFilesFolderCreateInput!) {
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
}`},b={id:`df297c445606cc8674482cb2a7dad93ef00b19f27724a00e88ff1013a86847bc`,name:`FlxFilesLog`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesLog($first: Int = 50, $after: String, $path: String, $via: FlxFilesVia) {
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
}`},x={id:`ed9598e1137f47c40a7f58d684fd9cd4a825c7389ea9a74ee2d41891f38db51c`,name:`FlxFilesMove`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesMove($input: FlxFilesMoveInput!) {
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
}`},S={id:`4fda84d50728f46b957ba8c54dd0ddfa92f51531d8881ae73a899645d69dda5b`,name:`FlxFilesRename`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesRename($input: FlxFilesRenameInput!) {
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
}`},C={id:`1a3f6c38af3fdde3ce542ed5459d58bc51e2e0531923742d050c3944e43fbfc6`,name:`FlxFilesRevert`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesRevert($input: FlxFilesRevertInput!) {
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
}`},w={id:`5356c293231db15f518a757f5eb788d353ec8f33108eb57039c272de7e36fff4`,name:`FlxFilesSearch`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesSearch($path: String!, $query: String!, $content: Boolean) {
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
}`},T={id:`47f5421ba6b9e20badaeb414b94d527088d2bfd140e32f7a147a3c730d4c5041`,name:`FlxFilesSettingsUpdate`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesSettingsUpdate($input: FlxFilesSettingsUpdateInput!) {
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
}`},E={id:`67739f018af821391785f275b9817550d051b7dad1879bcd1e491c84931c3314`,name:`FlxFilesShare`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesShare($input: FlxFilesShareInput!) {
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
}`},D={id:`714c52700ef0d6771f581011a651a3c23a0fe8e8ba99395c93d621c7cb988b4a`,name:`FlxFilesSize`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesSize($path: String!) {
  flxFilesSize(path: $path) {
    bytes
    files
    folders
    partial
  }
}`},O={id:`770f0507a338ab54a68921d53536c2521475117d015f2ecbae7c73c9836e5f8e`,name:`FlxFilesTrash`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesTrash($input: FlxFilesTrashInput!) {
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
}`},k={id:`e7695dfcf0d582a7b2aac6dff97e29d64d15b8aacebe1bb54905fc69dac327a1`,name:`FlxFilesTrashList`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesTrashList($first: Int = 200, $after: String) {
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
}`},A={id:`2f986646c02a7e0cbfc324e66a5f57e02434a3b14c8137f0586fb3800647cb98`,name:`FlxFilesTrashPurge`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesTrashPurge($input: FlxFilesTrashPurgeInput!) {
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
}`},j={id:`ae8ec6576f6d062c32245c2784087a446d828c8ed7f09e69761abbf62429ddaa`,name:`FlxFilesTrashRestore`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesTrashRestore($input: FlxFilesTrashRestoreInput!) {
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
}`},M={id:`d75223e3edb904ea34d2c371549694e223391ed1969dcb5eb23fa6e8350d6775`,name:`FlxFilesUnzip`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesUnzip($input: FlxFilesUnzipInput!) {
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
}`},N={id:`f0d2ddd90670e48e6ecda7e06b56f5ed9687520d1a05c430b6f7d6e2269e8e5d`,name:`FlxFilesVersionContent`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesVersionContent($path: String!, $versionId: String!) {
  flxFilesVersionContent(path: $path, versionId: $versionId)
}`},P={id:`6723e081addb1e78d9fbf04474e63ac1877473e63ee8fa3e83bd70f8d13b0272`,name:`FlxFilesVersions`,kind:`query`,plugin:`flx-files`,document:`query FlxFilesVersions($path: String!) {
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
}`},F={id:`723e828c659888ada86d76eaf190cb45ba9d65731e4474ab57051f8cd19db9b6`,name:`FlxFilesWrite`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesWrite($input: FlxFilesWriteInput!) {
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
}`},I={id:`32203b0cbb4dd23257d8028daf389e5819361d7e3352f1ddab908a7459ba9244`,name:`FlxFilesZip`,kind:`mutation`,plugin:`flx-files`,document:`mutation FlxFilesZip($input: FlxFilesZipInput!) {
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
}`},L={id:`21a336969a0dc41b8caaa1be0a20b9bd740359347ff57bc8cb72f70ca6254f9e`,name:`FlxRoleCreate`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleCreate($input: FlxRoleCreateInput!) {
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
}`},R={id:`2bd5579ac1188eadb0dff5235840b66098c68b1b0d9e5d9a86c1a07b54d3acea`,name:`FlxRoleDelete`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleDelete($input: FlxRoleDeleteInput!) {
  flxRoleDelete(input: $input) {
    deletedKey
    errors {
      code
      message
      field
    }
  }
}`},z={id:`af09ea3f7594b6e37e1aa272f116e57dd7079ca275a754a8107fc230ce3014df`,name:`FlxRoleMemberAdd`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleMemberAdd($input: FlxRoleMemberAddInput!) {
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
}`},B={id:`6eab4d6f1f97457a8679a9b1dd92d47dc2e01a7f41163eff439dc854213b2a7c`,name:`FlxRoleMemberRemove`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleMemberRemove($input: FlxRoleMemberRemoveInput!) {
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
}`},V={id:`067ae709ce0675a0f8e8ab3ab7d21d722afa9f7449f358767be373133c98de6f`,name:`FlxRoleMembers`,kind:`query`,plugin:`flx-roles`,document:`query FlxRoleMembers($key: String!, $first: Int = 50, $after: String) {
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
}`},H={id:`20e6cdbb88f7639a8749e74cf75ef58a4ab704143f0cf0fa3474dbb2366cb1d0`,name:`FlxRoleReset`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleReset($input: FlxRoleResetInput!) {
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
}`},U={id:`0a4da13faad85546b92fab5b45716dab5b06e87791475a9dfaa4664888375658`,name:`FlxRolesSave`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRolesSave($input: FlxRolesSaveInput!) {
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
}`},W={id:`2c22565866bcebf0aaed510b75bec73444fc8257c8d2b327873e9c2963c59030`,name:`FlxRolesScreen`,kind:`query`,plugin:`flx-roles`,document:`query FlxRolesScreen($members: Int = 50) {
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
}`},G={id:`fca51d9372f393736cd5c7f3cc3a6c6f76bc42c714907f05360f59ce43283d8b`,name:`FlxTaskCommentCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCommentCreate($input: FlxTaskCommentCreateInput!) {
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
}`},K={id:`f4712b3032ffaf4580393cf0e6234154fa1eb66399b12784aa29f29b625d34bd`,name:`FlxTaskCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCreate($input: FlxTaskCreateInput!) {
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
}`},q={id:`157053946bdf55aa4bf5f22a676119ec48e7bbe04c053867b7e0eedb11e04ec2`,name:`FlxTaskDelete`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskDelete($input: FlxTaskDeleteInput!) {
  flxTaskDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},J={id:`fa1d3f71ca31ab2a7b72c1a987e295f209d6564e8a68e3643dee354a44544ecf`,name:`FlxTaskDetail`,kind:`query`,plugin:`flx-tasks`,document:`query FlxTaskDetail($id: ID!) {
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
}`},Y={id:`d357c8fdb6c372508b17bb93fa22ec80224c0ff43854104149816d70a9e80870`,name:`FlxTaskNotificationsRead`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskNotificationsRead($input: FlxTaskNotificationsReadInput!) {
  flxTaskNotificationsRead(input: $input) {
    unread
    errors {
      code
      message
      field
    }
  }
}`},X={id:`004e85b9237b995957f75c2cfe2a996f5d2de6ca98b31c98511cb403df800ae3`,name:`FlxTaskUpdate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdate($input: FlxTaskUpdateInput!) {
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
}`},Z={id:`7c644e53f296f0dbf859b7bfcb4748e8c5be3ef891474fb47a262a2cd993be7a`,name:`FlxTaskUpdateDetail`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdateDetail($input: FlxTaskUpdateInput!) {
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
}`},Q={id:`a2c919c298da71f1634726a7c85c4344cf85f3602cbf54e4175bd8e45578e9b5`,name:`FlxTaskWatch`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskWatch($input: FlxTaskWatchInput!) {
  flxTaskWatch(input: $input) {
    watcherIds
    errors {
      code
      message
      field
    }
  }
}`};export{z as A,Y as B,M as C,I as D,F as E,W as F,i as G,Z as H,G as I,a as K,K as L,V as M,H as N,L as O,U as P,q as R,j as S,P as T,Q as U,X as V,l as W,E as _,m as a,k as b,_ as c,b as d,x as f,T as g,w as h,p as i,B as j,R as k,v as l,C as m,d as n,h as o,S as p,f as r,g as s,u as t,y as u,D as v,N as w,A as x,O as y,J as z};