import{J as e,K as t}from"./flux-host-ChJJuh98.js";var n=new Set([`UNAUTHENTICATED`,`FORBIDDEN`,`NOT_FOUND`,`INVALID_INPUT`,`CONFLICT`,`RATE_LIMITED`,`INTERNAL`,`PERSISTED_QUERY_NOT_FOUND`,`QUERY_NOT_ALLOWED`,`NETWORK`,`BAD_RESPONSE`]),r={401:`UNAUTHENTICATED`,403:`FORBIDDEN`,404:`NOT_FOUND`,429:`RATE_LIMITED`},i=class extends Error{constructor(t,i,a,o){let s=a[0];super(s?.message||o||`GraphQL request failed`),e(this,`code`,void 0),e(this,`field`,void 0),e(this,`errors`,void 0),e(this,`status`,void 0),e(this,`operation`,void 0),this.name=`FlxGraphQLError`,this.operation=t,this.status=i,this.errors=a;let c=s?.extensions?.code;this.code=c&&n.has(c)?c:o??r[i]??`INTERNAL`,this.field=typeof s?.extensions?.field==`string`?s.extensions.field:void 0}},a=e=>e instanceof i,o=`../graphql`,s=2e3,c=e=>Array.isArray(e)&&e.every(e=>typeof e==`object`&&!!e&&typeof e.message==`string`);function l(e){let n=e.persisted!==!1;async function r(t,n,r,i){let a=n&&typeof n==`object`&&Object.keys(n).length?n:void 0,c={raw:!0,throwOnError:!1,signal:i.signal,headers:{"X-Flux-GraphQL-Operation":t.name,...i.headers}};if(t.kind===`query`&&!r){let n={queryId:t.id,operationName:t.name,variables:a?JSON.stringify(a):void 0};if(Object.values(n).reduce((e,t)=>e+(t?encodeURIComponent(t).length+16:0),0)<s)return e.api(o,{...c,method:`GET`,query:n})}let l=r?{query:t.document,operationName:t.name,variables:a}:{queryId:t.id,operationName:t.name,variables:a};return e.api(o,{...c,method:`POST`,body:l})}async function a(e,...a){let o=a[0],s=a[1]??{},l;try{l=await r(e,o,!n,s),n&&l.data?.errors?.some(e=>e.extensions?.code===`PERSISTED_QUERY_NOT_FOUND`)&&(l=await r(e,o,!0,s))}catch(n){throw n instanceof t?new i(e.name,n.status,[{message:n.message}],n.code===`invalid_json`?`BAD_RESPONSE`:`NETWORK`):n}let u=l.data,d=c(u?.errors)?u.errors:[],f=u?.data;if(typeof f!=`object`||!f)throw new i(e.name,l.status,d.length?d:[{message:`Empty GraphQL response`}],d.length?void 0:`BAD_RESPONSE`);if(d.length){let t=new Set(d.map(e=>e.path?.[0]).filter(e=>typeof e==`string`));if([...t].some(e=>f[e]===null)||!t.size)throw new i(e.name,l.status,d)}return f}return{request:a}}var u={id:`1dd252522e2e7e352cbe31ebb7ef763661f7f52d407b0023f418e1365e0d5aa8`,name:`FlxAppAccess`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAccess($all: Boolean) {
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
}`},d={id:`0410fe39ca78d5cd046a033c326dd6bd357096aaab36212f8c0f44f9056de2c1`,name:`FlxAppAccessRevoke`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAccessRevoke($input: FlxAppAccessRevokeInput!) {
  flxAppAccessRevoke(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},f={id:`1860c58a8fdc3e9a90238fafaef5f1ecdc4bcf298ce323ef674dd35eefbe2e6c`,name:`FlxAppAdminAppearance`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminAppearance {
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
}`},p={id:`762de9d5115d73d8f01452c7b5f8a8ac15213df2102f3986d735018483a7670b`,name:`FlxAppAdminAppearanceSave`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminAppearanceSave($input: FlxAppAdminAppearanceSaveInput!) {
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
}`},m={id:`14ba5b9cfa34c05e5bef9a80d5087b56019d5b4c4a5a22ce7810d12dd6089f8c`,name:`FlxAppAdminClientCreate`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminClientCreate($input: FlxAppAdminClientCreateInput!) {
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
}`},h={id:`0acad378aa0aa39d0d878475c0e4c2c5e8a5a09045e3bd946641c722c6b0f44b`,name:`FlxAppAdminClientDelete`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminClientDelete($input: FlxAppAdminClientDeleteInput!) {
  flxAppAdminClientDelete(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},g={id:`0aa8eaf58f40a410644147b5f052aa2441f94a7c247af742f281bd5590f5b61f`,name:`FlxAppAdminClients`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminClients {
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
}`},_={id:`67b0edec2f4ef8fb57c5f796df9e32ec167f35dae7bec4196df4edcdff650a82`,name:`FlxAppAdminMenu`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminMenu {
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
}`},v={id:`e27de38feec02d07f97aa0a6cee1f3782bf39e4b41f0fd3cf84edb52d04a7d74`,name:`FlxAppAdminMobile`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminMobile {
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
}`},y={id:`3140882c7e64b03a33a9bdeaab1dc3b5eb7c50ba5709a2a95778672679795ae2`,name:`FlxAppAdminMobileSave`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminMobileSave($input: FlxAppAdminMobileSaveInput!) {
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
}`},b={id:`23953eab015437dc0f727a62ee3d73cb1e6cb45be22fc7f6b79e101318571d10`,name:`FlxAppAdminModules`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminModules {
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
}`},x={id:`a7aad76ad18dbda2567f29ad4d8b488efe4043659ae782a8d0f92eefba54349c`,name:`FlxAppAdminModulesSave`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminModulesSave($input: FlxAppAdminModulesSaveInput!) {
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
}`},S={id:`b9dddc6a4c12070645fd30a6fbdf39863d2b58ea1cbe71f7ebb5ee2db6bc9b3a`,name:`FlxAppAdminPushTest`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminPushTest {
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
}`},C={id:`b7d4bf6833511227a38948aef18cf38f05a29043d4dbfc3b04e49fcc1878ec06`,name:`FlxAppAdminSettings`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminSettings {
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
}`},w={id:`fc53aca192620408e9133dd653741e741a10c7d19ada18e38845f088b88e1788`,name:`FlxAppAdminSettingsSave`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminSettingsSave($input: FlxAppAdminSettingsSaveInput!) {
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
}`},T={id:`9f61478d3bc50857048da2499c97ed5de0bdc51362012d2153e2363175cb7d37`,name:`FlxAppDeviceRevoke`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppDeviceRevoke($input: FlxAppDeviceRevokeInput!) {
  flxAppDeviceRevoke(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},E={id:`49577b1d3b994160a8f4f89898086f3ffb70db6242a809e48b3515c7268d6e21`,name:`FlxAppKeyCreate`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppKeyCreate($input: FlxAppKeyCreateInput!) {
  flxAppKeyCreate(input: $input) {
    key
    errors {
      code
      message
      field
    }
  }
}`},D={id:`0cdd28e36785f9a36c07bcff67a19c55e28b55316009f469fc97857134e4a2b4`,name:`FlxBlocksBlockTypeSetActive`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksBlockTypeSetActive($input: FlxBlocksBlockTypeSetActiveInput!) {
  flxBlocksBlockTypeSetActive(input: $input) {
    blockType {
      ...FlxBlocksBlockTypeRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxBlocksBlockTypeRow on FlxBlocksBlockType {
  slug
  name
  description
  active
  link
  linkText
}`},O={id:`a707f69c55e13b00163c2d5f835704b3a9234102fefdc59f7e9bf0e1861e5d87`,name:`FlxBlocksDashboard`,kind:`query`,plugin:`flx-blocks`,document:`query FlxBlocksDashboard {
  flxBlocksBlockTypes {
    ...FlxBlocksBlockTypeRow
  }
  flxBlocksSettings {
    ...FlxBlocksSettingsFields
  }
  flxBlocksLibrarySources {
    ...FlxBlocksSourceRow
  }
  flxViewer {
    canManageOptions: can(capability: "manage_options")
  }
}

fragment FlxBlocksBlockTypeRow on FlxBlocksBlockType {
  slug
  name
  description
  active
  link
  linkText
}

fragment FlxBlocksSettingsFields on FlxBlocksSettings {
  showHeadersInAdminBar
  showAdminBarSetting
  styleLibraryUrl
  editorWidth {
    enabled
    small
    large
    postDefault
    pageDefault
    contentWidth
  }
}

fragment FlxBlocksSourceRow on FlxBlocksLibrarySource {
  id
  name
  url
  hasKey
}`},k={id:`ee8041abf4f646b0db2b506f7ede107c7bf199332d81ac3023f8a0e204408ad3`,name:`FlxBlocksLibraryPatterns`,kind:`query`,plugin:`flx-blocks`,document:`query FlxBlocksLibraryPatterns($source: ID, $category: String, $search: String, $first: Int = 24, $after: String, $refresh: Boolean) {
  flxBlocksLibrary(source: $source, refresh: $refresh) {
    name
    url
    source
    categories {
      slug
      label
      count
    }
    patterns(category: $category, search: $search, first: $first, after: $after) {
      totalCount
      nodes {
        ...FlxBlocksPatternRow
      }
      pageInfo {
        hasNextPage
        endCursor
      }
    }
  }
}

fragment FlxBlocksPatternRow on FlxBlocksPattern {
  id
  title
  description
  categories
  viewportWidth
  source
}`},A={id:`340869fd203dcf45e0db366bded93136560b5e3c7b8ee4e7f0efce9a99a933d1`,name:`FlxBlocksLibraryShare`,kind:`query`,plugin:`flx-blocks`,document:`query FlxBlocksLibraryShare {
  flxBlocksLibraryShare {
    ...FlxBlocksShareFields
  }
}

fragment FlxBlocksShareFields on FlxBlocksLibraryShare {
  enabled
  key
  url
}`},j={id:`a3ae64b495e2c2a1dc0d327b08e2df372ccea65c7c1827f676f6445bd7356177`,name:`FlxBlocksLibraryShareUpdate`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksLibraryShareUpdate($input: FlxBlocksLibraryShareUpdateInput!) {
  flxBlocksLibraryShareUpdate(input: $input) {
    share {
      ...FlxBlocksShareFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxBlocksShareFields on FlxBlocksLibraryShare {
  enabled
  key
  url
}`},M={id:`aab0d512355bb050e77a897874c870786b849b10ce29b327b1d353869d17292c`,name:`FlxBlocksLibrarySourceAdd`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksLibrarySourceAdd($input: FlxBlocksLibrarySourceAddInput!) {
  flxBlocksLibrarySourceAdd(input: $input) {
    source {
      ...FlxBlocksSourceRow
    }
    sources {
      ...FlxBlocksSourceRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxBlocksSourceRow on FlxBlocksLibrarySource {
  id
  name
  url
  hasKey
}`},N={id:`5406d45161cd50d86861128e261f0716f087150902c42c151688c7ad997ab317`,name:`FlxBlocksLibrarySourceRemove`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksLibrarySourceRemove($input: FlxBlocksLibrarySourceRemoveInput!) {
  flxBlocksLibrarySourceRemove(input: $input) {
    sources {
      ...FlxBlocksSourceRow
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxBlocksSourceRow on FlxBlocksLibrarySource {
  id
  name
  url
  hasKey
}`},P={id:`cebc38b2c4a42ddcdabe0145d488d587190ee259688daac8f0b2ea1e7599d82e`,name:`FlxBlocksPatternPreview`,kind:`query`,plugin:`flx-blocks`,document:`query FlxBlocksPatternPreview($id: String!, $source: ID) {
  flxBlocksPattern(id: $id, source: $source) {
    ...FlxBlocksPatternRow
    content
    rendered {
      html
      headHtml
      footerHtml
    }
  }
}

fragment FlxBlocksPatternRow on FlxBlocksPattern {
  id
  title
  description
  categories
  viewportWidth
  source
}`},F={id:`c22bde71875f8c21a1d1477eb43dad65257b059c911c8ad41284ffe3ba8c18e8`,name:`FlxBlocksSettingsUpdate`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksSettingsUpdate($input: FlxBlocksSettingsUpdateInput!) {
  flxBlocksSettingsUpdate(input: $input) {
    settings {
      ...FlxBlocksSettingsFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxBlocksSettingsFields on FlxBlocksSettings {
  showHeadersInAdminBar
  showAdminBarSetting
  styleLibraryUrl
  editorWidth {
    enabled
    small
    large
    postDefault
    pageDefault
    contentWidth
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
}`},K={id:`fca51d9372f393736cd5c7f3cc3a6c6f76bc42c714907f05360f59ce43283d8b`,name:`FlxTaskCommentCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCommentCreate($input: FlxTaskCommentCreateInput!) {
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
}`},q={id:`f4712b3032ffaf4580393cf0e6234154fa1eb66399b12784aa29f29b625d34bd`,name:`FlxTaskCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCreate($input: FlxTaskCreateInput!) {
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
}`},J={id:`157053946bdf55aa4bf5f22a676119ec48e7bbe04c053867b7e0eedb11e04ec2`,name:`FlxTaskDelete`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskDelete($input: FlxTaskDeleteInput!) {
  flxTaskDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},Y={id:`fa1d3f71ca31ab2a7b72c1a987e295f209d6564e8a68e3643dee354a44544ecf`,name:`FlxTaskDetail`,kind:`query`,plugin:`flx-tasks`,document:`query FlxTaskDetail($id: ID!) {
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
}`},X={id:`d357c8fdb6c372508b17bb93fa22ec80224c0ff43854104149816d70a9e80870`,name:`FlxTaskNotificationsRead`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskNotificationsRead($input: FlxTaskNotificationsReadInput!) {
  flxTaskNotificationsRead(input: $input) {
    unread
    errors {
      code
      message
      field
    }
  }
}`},Z={id:`004e85b9237b995957f75c2cfe2a996f5d2de6ca98b31c98511cb403df800ae3`,name:`FlxTaskUpdate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdate($input: FlxTaskUpdateInput!) {
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
}`},Q={id:`7c644e53f296f0dbf859b7bfcb4748e8c5be3ef891474fb47a262a2cd993be7a`,name:`FlxTaskUpdateDetail`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdateDetail($input: FlxTaskUpdateInput!) {
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
}`},$={id:`a2c919c298da71f1634726a7c85c4344cf85f3602cbf54e4175bd8e45578e9b5`,name:`FlxTaskWatch`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskWatch($input: FlxTaskWatchInput!) {
  flxTaskWatch(input: $input) {
    watcherIds
    errors {
      code
      message
      field
    }
  }
}`};export{z as A,Y as B,M as C,I as D,F as E,W as F,l as G,Z as H,G as I,i as K,K as L,V as M,H as N,L as O,U as P,q as R,j as S,P as T,Q as U,X as V,$ as W,E as _,m as a,k as b,_ as c,b as d,x as f,T as g,w as h,p as i,B as j,R as k,v as l,C as m,d as n,h as o,S as p,a as q,f as r,g as s,u as t,y as u,D as v,N as w,A as x,O as y,J as z};