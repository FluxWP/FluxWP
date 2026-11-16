import{$ as e,tt as t}from"./flux-host-Cs8oN1tZ.js";var n=new Set([`UNAUTHENTICATED`,`FORBIDDEN`,`NOT_FOUND`,`INVALID_INPUT`,`CONFLICT`,`RATE_LIMITED`,`INTERNAL`,`PERSISTED_QUERY_NOT_FOUND`,`QUERY_NOT_ALLOWED`,`NETWORK`,`BAD_RESPONSE`]),r={401:`UNAUTHENTICATED`,403:`FORBIDDEN`,404:`NOT_FOUND`,429:`RATE_LIMITED`},i=class extends Error{constructor(e,i,a,o){let s=a[0];super(s?.message||o||`GraphQL request failed`),t(this,`code`,void 0),t(this,`field`,void 0),t(this,`errors`,void 0),t(this,`status`,void 0),t(this,`operation`,void 0),this.name=`FlxGraphQLError`,this.operation=e,this.status=i,this.errors=a;let c=s?.extensions?.code;this.code=c&&n.has(c)?c:o??r[i]??`INTERNAL`,this.field=typeof s?.extensions?.field==`string`?s.extensions.field:void 0}},a=e=>e instanceof i,o=`../graphql`,s=2e3,c=e=>Array.isArray(e)&&e.every(e=>typeof e==`object`&&!!e&&typeof e.message==`string`);function l(t){let n=t.persisted!==!1;async function r(e,n,r,i){let a=n&&typeof n==`object`&&Object.keys(n).length?n:void 0,c={raw:!0,throwOnError:!1,signal:i.signal,headers:{"X-Flux-GraphQL-Operation":e.name,...i.headers}};if(e.kind===`query`&&!r){let n={queryId:e.id,operationName:e.name,variables:a?JSON.stringify(a):void 0};if(Object.values(n).reduce((e,t)=>e+(t?encodeURIComponent(t).length+16:0),0)<s)return t.api(o,{...c,method:`GET`,query:n})}let l=r?{query:e.document,operationName:e.name,variables:a}:{queryId:e.id,operationName:e.name,variables:a};return t.api(o,{...c,method:`POST`,body:l})}async function a(t,...a){let o=a[0],s=a[1]??{},l;try{l=await r(t,o,!n,s),n&&l.data?.errors?.some(e=>e.extensions?.code===`PERSISTED_QUERY_NOT_FOUND`)&&(l=await r(t,o,!0,s))}catch(n){throw n instanceof e?new i(t.name,n.status,[{message:n.message}],n.code===`invalid_json`?`BAD_RESPONSE`:`NETWORK`):n}let u=l.data,d=c(u?.errors)?u.errors:[],f=u?.data;if(typeof f!=`object`||!f)throw new i(t.name,l.status,d.length?d:[{message:`Empty GraphQL response`}],d.length?void 0:`BAD_RESPONSE`);if(d.length){let e=new Set(d.map(e=>e.path?.[0]).filter(e=>typeof e==`string`));if([...e].some(e=>f[e]===null)||!e.size)throw new i(t.name,l.status,d)}return f}return{request:a}}var u={id:`1dd252522e2e7e352cbe31ebb7ef763661f7f52d407b0023f418e1365e0d5aa8`,name:`FlxAppAccess`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAccess($all: Boolean) {
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
}`},ee={id:`762de9d5115d73d8f01452c7b5f8a8ac15213df2102f3986d735018483a7670b`,name:`FlxAppAdminAppearanceSave`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminAppearanceSave($input: FlxAppAdminAppearanceSaveInput!) {
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
}`},te={id:`14ba5b9cfa34c05e5bef9a80d5087b56019d5b4c4a5a22ce7810d12dd6089f8c`,name:`FlxAppAdminClientCreate`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminClientCreate($input: FlxAppAdminClientCreateInput!) {
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
}`},p={id:`0acad378aa0aa39d0d878475c0e4c2c5e8a5a09045e3bd946641c722c6b0f44b`,name:`FlxAppAdminClientDelete`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminClientDelete($input: FlxAppAdminClientDeleteInput!) {
  flxAppAdminClientDelete(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},m={id:`0aa8eaf58f40a410644147b5f052aa2441f94a7c247af742f281bd5590f5b61f`,name:`FlxAppAdminClients`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminClients {
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
}`},h={id:`67b0edec2f4ef8fb57c5f796df9e32ec167f35dae7bec4196df4edcdff650a82`,name:`FlxAppAdminMenu`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminMenu {
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
}`},g={id:`e27de38feec02d07f97aa0a6cee1f3782bf39e4b41f0fd3cf84edb52d04a7d74`,name:`FlxAppAdminMobile`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminMobile {
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
}`},_={id:`3140882c7e64b03a33a9bdeaab1dc3b5eb7c50ba5709a2a95778672679795ae2`,name:`FlxAppAdminMobileSave`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminMobileSave($input: FlxAppAdminMobileSaveInput!) {
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
}`},v={id:`23953eab015437dc0f727a62ee3d73cb1e6cb45be22fc7f6b79e101318571d10`,name:`FlxAppAdminModules`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminModules {
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
}`},y={id:`a7aad76ad18dbda2567f29ad4d8b488efe4043659ae782a8d0f92eefba54349c`,name:`FlxAppAdminModulesSave`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminModulesSave($input: FlxAppAdminModulesSaveInput!) {
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
}`},b={id:`b9dddc6a4c12070645fd30a6fbdf39863d2b58ea1cbe71f7ebb5ee2db6bc9b3a`,name:`FlxAppAdminPushTest`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminPushTest {
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
}`},x={id:`b7d4bf6833511227a38948aef18cf38f05a29043d4dbfc3b04e49fcc1878ec06`,name:`FlxAppAdminSettings`,kind:`query`,plugin:`flx-app`,document:`query FlxAppAdminSettings {
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
}`},S={id:`fc53aca192620408e9133dd653741e741a10c7d19ada18e38845f088b88e1788`,name:`FlxAppAdminSettingsSave`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppAdminSettingsSave($input: FlxAppAdminSettingsSaveInput!) {
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
}`},C={id:`9f61478d3bc50857048da2499c97ed5de0bdc51362012d2153e2363175cb7d37`,name:`FlxAppDeviceRevoke`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppDeviceRevoke($input: FlxAppDeviceRevokeInput!) {
  flxAppDeviceRevoke(input: $input) {
    ok
    errors {
      code
      message
      field
    }
  }
}`},w={id:`49577b1d3b994160a8f4f89898086f3ffb70db6242a809e48b3515c7268d6e21`,name:`FlxAppKeyCreate`,kind:`mutation`,plugin:`flx-app`,document:`mutation FlxAppKeyCreate($input: FlxAppKeyCreateInput!) {
  flxAppKeyCreate(input: $input) {
    key
    errors {
      code
      message
      field
    }
  }
}`},T={id:`0cdd28e36785f9a36c07bcff67a19c55e28b55316009f469fc97857134e4a2b4`,name:`FlxBlocksBlockTypeSetActive`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksBlockTypeSetActive($input: FlxBlocksBlockTypeSetActiveInput!) {
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
}`},E={id:`a707f69c55e13b00163c2d5f835704b3a9234102fefdc59f7e9bf0e1861e5d87`,name:`FlxBlocksDashboard`,kind:`query`,plugin:`flx-blocks`,document:`query FlxBlocksDashboard {
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
}`},D={id:`ee8041abf4f646b0db2b506f7ede107c7bf199332d81ac3023f8a0e204408ad3`,name:`FlxBlocksLibraryPatterns`,kind:`query`,plugin:`flx-blocks`,document:`query FlxBlocksLibraryPatterns($source: ID, $category: String, $search: String, $first: Int = 24, $after: String, $refresh: Boolean) {
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
}`},O={id:`340869fd203dcf45e0db366bded93136560b5e3c7b8ee4e7f0efce9a99a933d1`,name:`FlxBlocksLibraryShare`,kind:`query`,plugin:`flx-blocks`,document:`query FlxBlocksLibraryShare {
  flxBlocksLibraryShare {
    ...FlxBlocksShareFields
  }
}

fragment FlxBlocksShareFields on FlxBlocksLibraryShare {
  enabled
  key
  url
}`},k={id:`a3ae64b495e2c2a1dc0d327b08e2df372ccea65c7c1827f676f6445bd7356177`,name:`FlxBlocksLibraryShareUpdate`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksLibraryShareUpdate($input: FlxBlocksLibraryShareUpdateInput!) {
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
}`},A={id:`aab0d512355bb050e77a897874c870786b849b10ce29b327b1d353869d17292c`,name:`FlxBlocksLibrarySourceAdd`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksLibrarySourceAdd($input: FlxBlocksLibrarySourceAddInput!) {
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
}`},j={id:`5406d45161cd50d86861128e261f0716f087150902c42c151688c7ad997ab317`,name:`FlxBlocksLibrarySourceRemove`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksLibrarySourceRemove($input: FlxBlocksLibrarySourceRemoveInput!) {
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
}`},M={id:`cebc38b2c4a42ddcdabe0145d488d587190ee259688daac8f0b2ea1e7599d82e`,name:`FlxBlocksPatternPreview`,kind:`query`,plugin:`flx-blocks`,document:`query FlxBlocksPatternPreview($id: String!, $source: ID) {
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
}`},N={id:`c22bde71875f8c21a1d1477eb43dad65257b059c911c8ad41284ffe3ba8c18e8`,name:`FlxBlocksSettingsUpdate`,kind:`mutation`,plugin:`flx-blocks`,document:`mutation FlxBlocksSettingsUpdate($input: FlxBlocksSettingsUpdateInput!) {
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
}`},P={id:`7a2703962a923dbd66bbb68ae1b67a23777d1f64cd8eebb3f7f7e3ac839725c7`,name:`FlxCrmContact`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmContact($id: ID!) {
  flxContact(id: $id) {
    ...FlxCrmContactCard
  }
}

fragment FlxCrmContactCard on FlxContact {
  ...FlxCrmContactRow
  rawName
  country
  source
  sourceLabel
  utmSource
  utmCampaign
  userId
  ownerId
  ownerName
  note
  paidDays
  firstPaidAt {
    ...FlxCrmWhen
  }
  avgGapDays
  rfm {
    recency
    frequency
    monetary
  }
  consentAt {
    ...FlxCrmWhen
  }
  editUrl
  sources {
    label
    url
    date
  }
}

fragment FlxCrmContactRow on FlxContact {
  id
  databaseId
  name
  email
  phone {
    e164
    display
  }
  avatar
  company
  city
  roles
  donations
  orders
  segment
  segmentLabel
  ltv {
    ...FlxCrmMoney
  }
  churn
  atRisk
  status
  lastActivityAt {
    ...FlxCrmWhen
  }
  lastPaidAt {
    ...FlxCrmWhen
  }
  createdAt {
    ...FlxCrmWhen
  }
  consent {
    email
    sms
    whatsapp
    ads
  }
  tags {
    ...FlxCrmTag
  }
}

fragment FlxCrmMoney on FlxMoney {
  amount
  currency
  formatted
}

fragment FlxCrmTag on FlxTag {
  databaseId
  name
  color
  count
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  label
  relative
}`},F={id:`d64b39e87439228efb39cf454ec4b8156cf3e0fd2704751e0c30f58a4d4e8698`,name:`FlxCrmContactBulk`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactBulk($input: FlxContactBulkInput!) {
  flxContactBulk(input: $input) {
    changed
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},I={id:`1ddbb8969d399e686efebc66dfd2669494b2a20c18beffe637a9d26bba6c9e94`,name:`FlxCrmContactCreate`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactCreate($input: FlxContactCreateInput!) {
  flxContactCreate(input: $input) {
    contact {
      ...FlxCrmContactCard
    }
    existed
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmContactCard on FlxContact {
  ...FlxCrmContactRow
  rawName
  country
  source
  sourceLabel
  utmSource
  utmCampaign
  userId
  ownerId
  ownerName
  note
  paidDays
  firstPaidAt {
    ...FlxCrmWhen
  }
  avgGapDays
  rfm {
    recency
    frequency
    monetary
  }
  consentAt {
    ...FlxCrmWhen
  }
  editUrl
  sources {
    label
    url
    date
  }
}

fragment FlxCrmContactRow on FlxContact {
  id
  databaseId
  name
  email
  phone {
    e164
    display
  }
  avatar
  company
  city
  roles
  donations
  orders
  segment
  segmentLabel
  ltv {
    ...FlxCrmMoney
  }
  churn
  atRisk
  status
  lastActivityAt {
    ...FlxCrmWhen
  }
  lastPaidAt {
    ...FlxCrmWhen
  }
  createdAt {
    ...FlxCrmWhen
  }
  consent {
    email
    sms
    whatsapp
    ads
  }
  tags {
    ...FlxCrmTag
  }
}

fragment FlxCrmMoney on FlxMoney {
  amount
  currency
  formatted
}

fragment FlxCrmTag on FlxTag {
  databaseId
  name
  color
  count
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  label
  relative
}`},L={id:`e5ac09b6da29cd8a29870ad1eeecceb4e0930a083afacb5cb0f0823727443497`,name:`FlxCrmContactDelete`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactDelete($input: FlxContactDeleteInput!) {
  flxContactDelete(input: $input) {
    deletedId
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},R={id:`5b37df020f5fcf7eb644d59e7d43255a10daf12b3e1e81becfe80e6be2b63666`,name:`FlxCrmContactMerge`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactMerge($input: FlxContactMergeInput!) {
  flxContactMerge(input: $input) {
    merged
    contact {
      id
      databaseId
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},z={id:`78fef294f41cc0264573687c40208fb3d18ade9ed42cf1d4328c25414ceb2aa3`,name:`FlxCrmContacts`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmContacts($filter: FlxContactFilterInput, $orderBy: FlxContactOrderBy, $order: FlxContactOrder, $first: Int = 25, $after: String) {
  flxContacts(
    filter: $filter
    orderBy: $orderBy
    order: $order
    first: $first
    after: $after
  ) {
    totalCount
    nodes {
      ...FlxCrmContactRow
    }
    pageInfo {
      hasNextPage
      hasPreviousPage
      startCursor
      endCursor
    }
  }
}

fragment FlxCrmContactRow on FlxContact {
  id
  databaseId
  name
  email
  phone {
    e164
    display
  }
  avatar
  company
  city
  roles
  donations
  orders
  segment
  segmentLabel
  ltv {
    ...FlxCrmMoney
  }
  churn
  atRisk
  status
  lastActivityAt {
    ...FlxCrmWhen
  }
  lastPaidAt {
    ...FlxCrmWhen
  }
  createdAt {
    ...FlxCrmWhen
  }
  consent {
    email
    sms
    whatsapp
    ads
  }
  tags {
    ...FlxCrmTag
  }
}

fragment FlxCrmMoney on FlxMoney {
  amount
  currency
  formatted
}

fragment FlxCrmTag on FlxTag {
  databaseId
  name
  color
  count
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  label
  relative
}`},B={id:`5c13b0d3b065e0dd85f87fc9e53cbb77e20f0338223bcdd15c5a57a504623083`,name:`FlxCrmContactUpdate`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactUpdate($input: FlxContactUpdateInput!) {
  flxContactUpdate(input: $input) {
    contact {
      ...FlxCrmContactCard
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmContactCard on FlxContact {
  ...FlxCrmContactRow
  rawName
  country
  source
  sourceLabel
  utmSource
  utmCampaign
  userId
  ownerId
  ownerName
  note
  paidDays
  firstPaidAt {
    ...FlxCrmWhen
  }
  avgGapDays
  rfm {
    recency
    frequency
    monetary
  }
  consentAt {
    ...FlxCrmWhen
  }
  editUrl
  sources {
    label
    url
    date
  }
}

fragment FlxCrmContactRow on FlxContact {
  id
  databaseId
  name
  email
  phone {
    e164
    display
  }
  avatar
  company
  city
  roles
  donations
  orders
  segment
  segmentLabel
  ltv {
    ...FlxCrmMoney
  }
  churn
  atRisk
  status
  lastActivityAt {
    ...FlxCrmWhen
  }
  lastPaidAt {
    ...FlxCrmWhen
  }
  createdAt {
    ...FlxCrmWhen
  }
  consent {
    email
    sms
    whatsapp
    ads
  }
  tags {
    ...FlxCrmTag
  }
}

fragment FlxCrmMoney on FlxMoney {
  amount
  currency
  formatted
}

fragment FlxCrmTag on FlxTag {
  databaseId
  name
  color
  count
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  label
  relative
}`},V={id:`d8cbff1ab3075cba9ae5ea057a6d68195e6aaa27a724bbf9c924efa5974873cd`,name:`FlxCrmDuplicates`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmDuplicates {
  flxContactDuplicates {
    why
    items {
      usedBy
      contact {
        ...FlxCrmContactRow
      }
    }
  }
}

fragment FlxCrmContactRow on FlxContact {
  id
  databaseId
  name
  email
  phone {
    e164
    display
  }
  avatar
  company
  city
  roles
  donations
  orders
  segment
  segmentLabel
  ltv {
    ...FlxCrmMoney
  }
  churn
  atRisk
  status
  lastActivityAt {
    ...FlxCrmWhen
  }
  lastPaidAt {
    ...FlxCrmWhen
  }
  createdAt {
    ...FlxCrmWhen
  }
  consent {
    email
    sms
    whatsapp
    ads
  }
  tags {
    ...FlxCrmTag
  }
}

fragment FlxCrmMoney on FlxMoney {
  amount
  currency
  formatted
}

fragment FlxCrmTag on FlxTag {
  databaseId
  name
  color
  count
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  label
  relative
}`},H={id:`fe10d4a31eedbbd448e3769a47a190a8872c2bdad2256def5f7e54cdf091eae0`,name:`FlxCrmEventAdd`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmEventAdd($input: FlxContactEventAddInput!) {
  flxContactEventAdd(input: $input) {
    event {
      ...FlxCrmEvent
    }
    contact {
      id
      lastActivityAt {
        ...FlxCrmWhen
      }
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmEvent on FlxContactEvent {
  databaseId
  contactId
  contactName
  type
  typeLabel
  icon
  source
  title
  body
  amount {
    ...FlxCrmMoney
  }
  paid
  status
  url
  userName
  at {
    ...FlxCrmWhen
  }
  answers {
    question
    answer
  }
  deletable
}

fragment FlxCrmMoney on FlxMoney {
  amount
  currency
  formatted
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  label
  relative
}`},U={id:`a76a713193a0c8d25b06c56932ac7a945251d1c2966e1e35351c2b154124d10d`,name:`FlxCrmEventDelete`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmEventDelete($input: FlxContactEventDeleteInput!) {
  flxContactEventDelete(input: $input) {
    deletedId
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},W={id:`e4410099ca450172284928b787d6653e3a20bb9fea759f3405dcc65b4b2d60fc`,name:`FlxCrmJourneyDelete`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmJourneyDelete($input: FlxJourneyDeleteInput!) {
  flxJourneyDelete(input: $input) {
    deletedId
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},G={id:`81f33cb7df2b01fe473af742d3664bc98d855fff2d8d6c6bd10bf813b3126a00`,name:`FlxCrmJourneyFromTemplate`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmJourneyFromTemplate($input: FlxJourneyFromTemplateInput!) {
  flxJourneyFromTemplate(input: $input) {
    journey {
      ...FlxCrmJourneyRow
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmJourneyRow on FlxJourney {
  databaseId
  name
  status
  description
  triggerType
  triggerValue
  steps {
    type
    days
    hours
    subject
    text
    condition
    value
    tag
  }
  opts {
    goal
    reenterDays
    quietFrom
    quietTo
    capWeek
  }
  stats {
    entered
    finished
    goal
    sent
    skipped
    active
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},K={id:`1d65e68f34e737d57ba69b8f75f693f31df5df46147478680767d4a886eba9a4`,name:`FlxCrmJourneys`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmJourneys {
  flxJourneys {
    ...FlxCrmJourneyRow
  }
  flxJourneyConfig {
    triggers {
      key
      label
    }
    stepTypes {
      key
      label
    }
    conditions {
      key
      label
    }
    statuses {
      key
      label
    }
    goals {
      key
      label
    }
    templates {
      key
      label
    }
    defaults {
      goal
      reenterDays
      quietFrom
      quietTo
      capWeek
    }
    triggerValues {
      trigger
      values {
        value
        label
      }
    }
    mailActive
  }
}

fragment FlxCrmJourneyRow on FlxJourney {
  databaseId
  name
  status
  description
  triggerType
  triggerValue
  steps {
    type
    days
    hours
    subject
    text
    condition
    value
    tag
  }
  opts {
    goal
    reenterDays
    quietFrom
    quietTo
    capWeek
  }
  stats {
    entered
    finished
    goal
    sent
    skipped
    active
  }
}`},q={id:`bb7940aae0aa4487e6596f7310dfe9d6c66cd05f801d9d2571a2fbd024003e89`,name:`FlxCrmJourneySave`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmJourneySave($input: FlxJourneySaveInput!) {
  flxJourneySave(input: $input) {
    journey {
      ...FlxCrmJourneyRow
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmJourneyRow on FlxJourney {
  databaseId
  name
  status
  description
  triggerType
  triggerValue
  steps {
    type
    days
    hours
    subject
    text
    condition
    value
    tag
  }
  opts {
    goal
    reenterDays
    quietFrom
    quietTo
    capWeek
  }
  stats {
    entered
    finished
    goal
    sent
    skipped
    active
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},J={id:`271eb15db3d24fbf7acc5848820ed761c6fdcece95a02526b41ff2a7316997a2`,name:`FlxCrmJourneySetStatus`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmJourneySetStatus($input: FlxJourneySetStatusInput!) {
  flxJourneySetStatus(input: $input) {
    journey {
      ...FlxCrmJourneyRow
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmJourneyRow on FlxJourney {
  databaseId
  name
  status
  description
  triggerType
  triggerValue
  steps {
    type
    days
    hours
    subject
    text
    condition
    value
    tag
  }
  opts {
    goal
    reenterDays
    quietFrom
    quietTo
    capWeek
  }
  stats {
    entered
    finished
    goal
    sent
    skipped
    active
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},Y={id:`172de029371f50368a15f9bd18e6643d27d6d370593aeac6979a894b17a7bf8b`,name:`FlxCrmOverview`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmOverview {
  flxCrmOverview {
    total
    new30
    newPrev
    active
    risk
    ltv {
      ...FlxCrmMoney
    }
    paying
    repeat
    currency
    activeDays
    riskThreshold
    scoredAt {
      ...FlxCrmWhen
    }
    syncedAt {
      ...FlxCrmWhen
    }
    needsSync
    syncSources
    roles {
      key
      label
      color
      count
    }
    segments {
      key
      label
      hint
      tone
      count
      value {
        ...FlxCrmMoney
      }
    }
    sources {
      key
      label
      count
    }
  }
  flxCrmRecentEvents(limit: 8) {
    ...FlxCrmEvent
  }
  atRisk: flxContacts(
    first: 6
    filter: { atRisk: true }
    orderBy: LTV
    order: DESC
  ) {
    nodes {
      ...FlxCrmContactRow
    }
  }
}

fragment FlxCrmContactRow on FlxContact {
  id
  databaseId
  name
  email
  phone {
    e164
    display
  }
  avatar
  company
  city
  roles
  donations
  orders
  segment
  segmentLabel
  ltv {
    ...FlxCrmMoney
  }
  churn
  atRisk
  status
  lastActivityAt {
    ...FlxCrmWhen
  }
  lastPaidAt {
    ...FlxCrmWhen
  }
  createdAt {
    ...FlxCrmWhen
  }
  consent {
    email
    sms
    whatsapp
    ads
  }
  tags {
    ...FlxCrmTag
  }
}

fragment FlxCrmEvent on FlxContactEvent {
  databaseId
  contactId
  contactName
  type
  typeLabel
  icon
  source
  title
  body
  amount {
    ...FlxCrmMoney
  }
  paid
  status
  url
  userName
  at {
    ...FlxCrmWhen
  }
  answers {
    question
    answer
  }
  deletable
}

fragment FlxCrmMoney on FlxMoney {
  amount
  currency
  formatted
}

fragment FlxCrmTag on FlxTag {
  databaseId
  name
  color
  count
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  label
  relative
}`},X={id:`e0edd7dec5bacd4fa0efa1061b492dfd4514405725805c3d2dad8e334f1fa625`,name:`FlxCrmScoreRun`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmScoreRun($input: FlxCrmScoreRunInput!) {
  flxCrmScoreRun(input: $input) {
    result {
      scored
      changed
      done
      total
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},Z={id:`2f13b0c4d23cfcf18bb040c35a545c3899b8be9c777bef71b9a3a1889584e38c`,name:`FlxCrmSegmentDelete`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmSegmentDelete($input: FlxSegmentDeleteInput!) {
  flxSegmentDelete(input: $input) {
    deletedId
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},Q={id:`acc491cf70a4b00af7fce340efd2fd26dc087fe84690b01a364966abf7dea880`,name:`FlxCrmSegmentEditor`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmSegmentEditor {
  flxSegmentEditor {
    fields {
      key
      label
      type
      ops
    }
    ops {
      key
      label
    }
    options {
      type
      values {
        value
        label
      }
    }
  }
}`},ne={id:`564b5e761395c08e8045dbc65f96f00b7f4fa14bc2dcfa48e28a0beff5edd00e`,name:`FlxCrmSegmentPreview`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmSegmentPreview($match: FlxSegmentMatch, $rules: [FlxSegmentRuleInput!]!) {
  flxSegmentPreview(match: $match, rules: $rules) {
    count
    summary
    sample {
      databaseId
      name
    }
  }
}`},re={id:`6035e1dbb5301cebeb87fa3481cf44003f54cdd356668d7d22ff87b96ec87866`,name:`FlxCrmSegmentSave`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmSegmentSave($input: FlxSegmentSaveInput!) {
  flxSegmentSave(input: $input) {
    segment {
      ...FlxCrmSegmentRow
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmSegmentRow on FlxSegment {
  databaseId
  name
  description
  count
  summary
  match
  rules {
    field
    op
    value
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},ie={id:`4e800655079b39d7b0c8f5dca828111815df5e95cd0e91406785150732efd4a3`,name:`FlxCrmSettings`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmSettings {
  flxCrmSettings {
    chatEvents
    aiTools
    aiWrite
    aiDestructive
    riskThreshold
    activeDays
    baseCurrency
    currency
    sources {
      key
      label
      description
      installed
      enabled
      hasRole
      role
    }
    roles {
      key
      label
      color
      builtin
    }
    syncedAt {
      ...FlxCrmWhen
    }
    scoredAt {
      ...FlxCrmWhen
    }
  }
  flxCrmAi {
    aiInstalled
    mcpInstalled
    mcpEnabled
    mcpUrl
    mcpAdminUrl
    toolsOn
    tools {
      name
      title
      kind
      kindLabel
      enabled
    }
  }
  flxCrmAiLog(limit: 15) {
    at {
      ...FlxCrmWhen
    }
    who
    channel
    channelLabel
    action
    target
    ok
    error
  }
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  label
  relative
}`},ae={id:`6a79c573c70d3bbbdef5121997576cc4a22dfbd6b0a88b874a7029fa85cccbea`,name:`FlxCrmSettingsSave`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmSettingsSave($input: FlxCrmSettingsSaveInput!) {
  flxCrmSettingsSave(input: $input) {
    settings {
      chatEvents
      aiTools
      aiWrite
      aiDestructive
      riskThreshold
      activeDays
      baseCurrency
      currency
      sources {
        key
        label
        description
        installed
        enabled
        hasRole
        role
      }
      roles {
        key
        label
        color
        builtin
      }
      syncedAt {
        ...FlxCrmWhen
      }
      scoredAt {
        ...FlxCrmWhen
      }
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  label
  relative
}`},oe={id:`3a59d8e4195bf7ba635933d4e7baafd37f013da4a16f4d4cfc49b7501f3a7de4`,name:`FlxCrmShell`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmShell {
  flxCrmMeta {
    roles {
      key
      label
      color
      builtin
      hidden
    }
    rfm {
      key
      label
      hint
      tone
    }
    eventTypes {
      key
      label
      icon
      manual
    }
    sources {
      key
      label
    }
    consents {
      key
      label
    }
    timelineFilters {
      key
      label
    }
    currency
    canWrite
    hasPackageSections
    owners {
      databaseId
      name
    }
  }
  flxTags {
    ...FlxCrmTag
  }
  flxSegments {
    ...FlxCrmSegmentRow
  }
}

fragment FlxCrmSegmentRow on FlxSegment {
  databaseId
  name
  description
  count
  summary
  match
  rules {
    field
    op
    value
  }
}

fragment FlxCrmTag on FlxTag {
  databaseId
  name
  color
  count
}`},se={id:`91833bc806371dcec389dec7ee846bdc3806edd84b27da2b516ee064b794c6a5`,name:`FlxCrmSyncStep`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmSyncStep($input: FlxCrmSyncStepInput!) {
  flxCrmSyncStep(input: $input) {
    result {
      step
      label
      scanned
      total
      next
      page
      finished
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},ce={id:`803c73840e66e14fc6312eb733e95eb00bb1261c0fcdcaa93508c07d05751c35`,name:`FlxCrmTagAdd`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmTagAdd($input: FlxContactTagAddInput!) {
  flxContactTagAdd(input: $input) {
    tag {
      ...FlxCrmTag
    }
    contact {
      id
      tags {
        ...FlxCrmTag
      }
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmTag on FlxTag {
  databaseId
  name
  color
  count
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},le={id:`68836030d68ed1f45df0595812ec3fbbf361855944537c066a08b8ea641057fa`,name:`FlxCrmTagRemove`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmTagRemove($input: FlxContactTagRemoveInput!) {
  flxContactTagRemove(input: $input) {
    contact {
      id
      tags {
        ...FlxCrmTag
      }
    }
    errors {
      ...FlxCrmUserError
    }
  }
}

fragment FlxCrmTag on FlxTag {
  databaseId
  name
  color
  count
}

fragment FlxCrmUserError on FlxUserError {
  code
  message
  field
}`},ue={id:`b019ec38b06648c194238f86b99019a21dcdd4ae0092c44cddbc896a1421275c`,name:`FlxCrmTimeline`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmTimeline($id: ID!, $filter: String, $first: Int = 20, $after: String) {
  flxContact(id: $id) {
    id
    timeline(filter: $filter, first: $first, after: $after) {
      nodes {
        ...FlxCrmEvent
      }
      pageInfo {
        hasNextPage
        endCursor
      }
    }
  }
}

fragment FlxCrmEvent on FlxContactEvent {
  databaseId
  contactId
  contactName
  type
  typeLabel
  icon
  source
  title
  body
  amount {
    ...FlxCrmMoney
  }
  paid
  status
  url
  userName
  at {
    ...FlxCrmWhen
  }
  answers {
    question
    answer
  }
  deletable
}

fragment FlxCrmMoney on FlxMoney {
  amount
  currency
  formatted
}

fragment FlxCrmWhen on FlxDateTime {
  iso
  label
  relative
}`},de={id:`14ae18c34134e93d807ec1e3d21907a4c002555d4d327011a6cbdd05c13743ab`,name:`FlxLanguageSettingsScreen`,kind:`query`,plugin:`flx-lang`,document:`query FlxLanguageSettingsScreen {
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
}`},fe={id:`0da4e1f193deec851174ea768e4699d956a94e7670b172e44dafe5bf9f788a52`,name:`FlxLanguageSettingsUpdate`,kind:`mutation`,plugin:`flx-lang`,document:`mutation FlxLanguageSettingsUpdate($input: FlxLanguageSettingsUpdateInput!) {
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
}`},pe={id:`21a336969a0dc41b8caaa1be0a20b9bd740359347ff57bc8cb72f70ca6254f9e`,name:`FlxRoleCreate`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleCreate($input: FlxRoleCreateInput!) {
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
}`},me={id:`2bd5579ac1188eadb0dff5235840b66098c68b1b0d9e5d9a86c1a07b54d3acea`,name:`FlxRoleDelete`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleDelete($input: FlxRoleDeleteInput!) {
  flxRoleDelete(input: $input) {
    deletedKey
    errors {
      code
      message
      field
    }
  }
}`},he={id:`af09ea3f7594b6e37e1aa272f116e57dd7079ca275a754a8107fc230ce3014df`,name:`FlxRoleMemberAdd`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleMemberAdd($input: FlxRoleMemberAddInput!) {
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
}`},ge={id:`6eab4d6f1f97457a8679a9b1dd92d47dc2e01a7f41163eff439dc854213b2a7c`,name:`FlxRoleMemberRemove`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleMemberRemove($input: FlxRoleMemberRemoveInput!) {
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
}`},_e={id:`067ae709ce0675a0f8e8ab3ab7d21d722afa9f7449f358767be373133c98de6f`,name:`FlxRoleMembers`,kind:`query`,plugin:`flx-roles`,document:`query FlxRoleMembers($key: String!, $first: Int = 50, $after: String) {
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
}`},ve={id:`20e6cdbb88f7639a8749e74cf75ef58a4ab704143f0cf0fa3474dbb2366cb1d0`,name:`FlxRoleReset`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRoleReset($input: FlxRoleResetInput!) {
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
}`},ye={id:`0a4da13faad85546b92fab5b45716dab5b06e87791475a9dfaa4664888375658`,name:`FlxRolesSave`,kind:`mutation`,plugin:`flx-roles`,document:`mutation FlxRolesSave($input: FlxRolesSaveInput!) {
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
}`},be={id:`2c22565866bcebf0aaed510b75bec73444fc8257c8d2b327873e9c2963c59030`,name:`FlxRolesScreen`,kind:`query`,plugin:`flx-roles`,document:`query FlxRolesScreen($members: Int = 50) {
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
}`},xe={id:`24ef9e0380385f71b2af51a8163f15a8362c7a02a3fee076051efdd289a4d7a4`,name:`FlxSeoAuditRun`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoAuditRun($input: FlxSeoAuditRunInput!) {
  flxSeoAuditRun(input: $input) {
    audit {
      ...FlxSeoAuditFields
    }
    history {
      ...FlxSeoHistoryFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSeoAuditFields on FlxSeoAudit {
  counts {
    ...FlxSeoCountsFields
  }
  local {
    time {
      iso
      label
      relative
    }
    url
    durationMs
    httpStatus
  }
  pageSpeed {
    strategy
    time {
      iso
      label
      relative
    }
    url
    lighthouse
    scores {
      category
      label
      score
      passed
      total
    }
    metrics {
      key
      label
      value
      score
    }
  }
  agentic {
    id
    label
    passed
  }
  agenticPassed
  auditUrl
  mcpServerOn
  llmsUrl
  findings {
    id
    title
    priority
    priorityLabel
    category
    categoryLabel
    also
    detail
    fix
    owner
    ownerLabel
    items
    sources
    fixId
    fixLabel
  }
}

fragment FlxSeoCountsFields on FlxSeoCounts {
  critical
  high
  medium
  low
}

fragment FlxSeoHistoryFields on FlxSeoHistory {
  total
  running
  errors
  next {
    iso
    label
  }
  entries {
    time {
      iso
      label
      relative
    }
    trigger
    triggerLabel
    url
    counts {
      ...FlxSeoCountsFields
    }
    scores {
      strategy
      category
      label
      score
    }
    metrics {
      strategy
      fcp
      lcp
      tbt
      cls
      si
    }
    agentic
    added {
      id
      title
    }
    resolved {
      id
      title
    }
  }
}`},Se={id:`25585531a77819b9e9b681a85e767cb408d01dcab0ecb5e5e3717d30b20fb89e`,name:`FlxSeoAuditScreen`,kind:`query`,plugin:`flx-seo`,document:`query FlxSeoAuditScreen($history: Int = 30) {
  flxSeoAudit {
    ...FlxSeoAuditFields
  }
  flxSeoAuditHistory(limit: $history) {
    ...FlxSeoHistoryFields
  }
}

fragment FlxSeoAuditFields on FlxSeoAudit {
  counts {
    ...FlxSeoCountsFields
  }
  local {
    time {
      iso
      label
      relative
    }
    url
    durationMs
    httpStatus
  }
  pageSpeed {
    strategy
    time {
      iso
      label
      relative
    }
    url
    lighthouse
    scores {
      category
      label
      score
      passed
      total
    }
    metrics {
      key
      label
      value
      score
    }
  }
  agentic {
    id
    label
    passed
  }
  agenticPassed
  auditUrl
  mcpServerOn
  llmsUrl
  findings {
    id
    title
    priority
    priorityLabel
    category
    categoryLabel
    also
    detail
    fix
    owner
    ownerLabel
    items
    sources
    fixId
    fixLabel
  }
}

fragment FlxSeoCountsFields on FlxSeoCounts {
  critical
  high
  medium
  low
}

fragment FlxSeoHistoryFields on FlxSeoHistory {
  total
  running
  errors
  next {
    iso
    label
  }
  entries {
    time {
      iso
      label
      relative
    }
    trigger
    triggerLabel
    url
    counts {
      ...FlxSeoCountsFields
    }
    scores {
      strategy
      category
      label
      score
    }
    metrics {
      strategy
      fcp
      lcp
      tbt
      cls
      si
    }
    agentic
    added {
      id
      title
    }
    resolved {
      id
      title
    }
  }
}`},Ce={id:`97d49670dd4df105502f5013764c377eaadec82d4f58ba3d9d0f8d3a00566bd2`,name:`FlxSeoContentCheck`,kind:`query`,plugin:`flx-seo`,document:`query FlxSeoContentCheck($limit: Int = 300) {
  flxSeoContentCheck(limit: $limit) {
    checked
    total
    items {
      databaseId
      title
      postType
      url
      editUrl
      issues {
        level
        code
        message
      }
    }
  }
}`},we={id:`aca5c530650ff0a558a94968a069e5d1d183315910cf69556396462fb63d557a`,name:`FlxSeoFixApply`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoFixApply($input: FlxSeoFixApplyInput!) {
  flxSeoFixApply(input: $input) {
    audit {
      ...FlxSeoAuditFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSeoAuditFields on FlxSeoAudit {
  counts {
    ...FlxSeoCountsFields
  }
  local {
    time {
      iso
      label
      relative
    }
    url
    durationMs
    httpStatus
  }
  pageSpeed {
    strategy
    time {
      iso
      label
      relative
    }
    url
    lighthouse
    scores {
      category
      label
      score
      passed
      total
    }
    metrics {
      key
      label
      value
      score
    }
  }
  agentic {
    id
    label
    passed
  }
  agenticPassed
  auditUrl
  mcpServerOn
  llmsUrl
  findings {
    id
    title
    priority
    priorityLabel
    category
    categoryLabel
    also
    detail
    fix
    owner
    ownerLabel
    items
    sources
    fixId
    fixLabel
  }
}

fragment FlxSeoCountsFields on FlxSeoCounts {
  critical
  high
  medium
  low
}`},Te={id:`fa7f2e42636c7fd69983e7637be5625f01102cc336c76b7e751dfc6b25299aea`,name:`FlxSeoGuide`,kind:`query`,plugin:`flx-seo`,document:`query FlxSeoGuide($lang: FlxSeoGuideLang) {
  flxSeoGuide(lang: $lang) {
    lang
    markdown
    sections
  }
}`},Ee={id:`84dfec5a3b859e284998fc8124abae04e2fdd4419dac91ab6cb5971882673a9d`,name:`FlxSeoIndexNowSubmit`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoIndexNowSubmit($input: FlxSeoIndexNowSubmitInput!) {
  flxSeoIndexNowSubmit(input: $input) {
    submitted
    setup {
      ...FlxSeoSetupFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSeoSetupFields on FlxSeoSetup {
  otherPlugin
  aiReady
  blogName
  homeUrl
  sitemapUrl
  logoDefault
  imageDefault
  homeTitle
  homeDesc
  sitekit
  sitePublic
  indexnowKey
  indexnowUrl
  indexnowLast
  indexnowReady
  psiKeySaved
  adminEmail
  guideSections
  engines {
    key
    field
    name
    url
    link
    placeholder
    help
  }
}`},De={id:`59e6780cadb6bc0d4dcecc09e482056c52aeb00879b5b1c00fa35049f815dea2`,name:`FlxSeoPsiKeySave`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoPsiKeySave($input: FlxSeoPsiKeySaveInput!) {
  flxSeoPsiKeySave(input: $input) {
    saved
    errors {
      code
      message
      field
    }
  }
}`},Oe={id:`ffd79b34482e7fd6c534446f2a81d044658a4eea3fafffa2d65eb14c8b4101fd`,name:`FlxSeoSettingsSave`,kind:`mutation`,plugin:`flx-seo`,document:`mutation FlxSeoSettingsSave($flxseo: FlxSettingsFlxseoInput!) {
  flxSettingsUpdate(input: { flxseo: $flxseo }) {
    settings {
      flxseo {
        ...FlxSeoSettingsFields
      }
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxSeoSettingsFields on FlxSettingsFlxseo {
  enabled
  siteName
  altName
  sep
  homeTitle
  homeDesc
  ogImage
  verifyGoogle
  verifyBing
  verifyYandex
  verifyNaver
  verifySeznam
  verifyBaidu
  indexnow
  noindexShop
  noindexSearch
  noindexAuthor
  noindexTags
  autoAlt
  orgName
  orgType
  founding
  logo
  street
  locality
  postal
  country
  phone
  email
  sameAs
  twitter
  knowsAbout
  areaServed
  donateLabel
  donateUrl
  llms
  llmsIntro
  webmcp
  markdown
  contentSignal
  csSearch
  csAiInput
  csAiTrain
  hdrFrame
  hdrCoop
  hdrCsp
  hstsSub
  hstsPreload
  auditFreq
  auditDay
  auditHour
  auditPsi
  auditMail
  auditMailTo
}`},ke={id:`86bc810d43babcb5cefcbb0d86511dc9661c0d5431662164e77220a7f6f5e3b8`,name:`FlxSeoSettingsScreen`,kind:`query`,plugin:`flx-seo`,document:`query FlxSeoSettingsScreen {
  flxSettings {
    flxseo {
      ...FlxSeoSettingsFields
    }
  }
  flxSeoSetup {
    ...FlxSeoSetupFields
  }
}

fragment FlxSeoSettingsFields on FlxSettingsFlxseo {
  enabled
  siteName
  altName
  sep
  homeTitle
  homeDesc
  ogImage
  verifyGoogle
  verifyBing
  verifyYandex
  verifyNaver
  verifySeznam
  verifyBaidu
  indexnow
  noindexShop
  noindexSearch
  noindexAuthor
  noindexTags
  autoAlt
  orgName
  orgType
  founding
  logo
  street
  locality
  postal
  country
  phone
  email
  sameAs
  twitter
  knowsAbout
  areaServed
  donateLabel
  donateUrl
  llms
  llmsIntro
  webmcp
  markdown
  contentSignal
  csSearch
  csAiInput
  csAiTrain
  hdrFrame
  hdrCoop
  hdrCsp
  hstsSub
  hstsPreload
  auditFreq
  auditDay
  auditHour
  auditPsi
  auditMail
  auditMailTo
}

fragment FlxSeoSetupFields on FlxSeoSetup {
  otherPlugin
  aiReady
  blogName
  homeUrl
  sitemapUrl
  logoDefault
  imageDefault
  homeTitle
  homeDesc
  sitekit
  sitePublic
  indexnowKey
  indexnowUrl
  indexnowLast
  indexnowReady
  psiKeySaved
  adminEmail
  guideSections
  engines {
    key
    field
    name
    url
    link
    placeholder
    help
  }
}`},Ae={id:`b8f91c52dab2869ea091bb25833ba93b1b5ba0eb62b369c3cff557466b3478ba`,name:`FlxSetupAutoUpdateSet`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupAutoUpdateSet($input: FlxSetupAutoUpdateSetInput!) {
  flxSetupAutoUpdateSet(input: $input) {
    on
    label
    errors {
      ...FlxSetupErrors
    }
  }
}

fragment FlxSetupErrors on FlxUserError {
  code
  message
  field
}`},je={id:`327a745eaebc60ab14f76d453be58e38f0399b212c0c15b87d2dbd1488a731c4`,name:`FlxSetupBackupsDelete`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupBackupsDelete($input: FlxSetupBackupsDeleteInput!) {
  flxSetupBackupsDelete(input: $input) {
    deleted
    retention {
      ...FlxSetupRetentionFields
    }
    errors {
      ...FlxSetupErrors
    }
  }
}

fragment FlxSetupErrors on FlxUserError {
  code
  message
  field
}

fragment FlxSetupRetentionFields on FlxSetupRetention {
  available
  days
  choices
  count
  size
  settingsUrl
}`},Me={id:`5c3dea411ce3bbfc57b8945d04dd11c857a00fe726bc27e95ea52175cd11ac3f`,name:`FlxSetupCheck`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupCheck {
  flxSetupCheck(input: {  }) {
    ok
    pendingCount
    checkedLabel
    summary
    errors {
      ...FlxSetupErrors
    }
  }
}

fragment FlxSetupErrors on FlxUserError {
  code
  message
  field
}`},Ne={id:`fea5c46697571e499c36f813537e124a23f33e2a3b616d09264af5ef3fec1d26`,name:`FlxSetupHistory`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupHistory($page: Int) {
  flxSetupHistory(page: $page) {
    rows {
      time {
        iso
        label
        relative
      }
      name
      action
      success
      message
      by
      backup {
        id
        deleted
        size
        locked
        keepUntil {
          label
        }
        deletable
        selectable
        url
      }
    }
    total
    pages
    page
    canDelete
    backupPage
    retention {
      ...FlxSetupRetentionFields
    }
  }
}

fragment FlxSetupRetentionFields on FlxSetupRetention {
  available
  days
  choices
  count
  size
  settingsUrl
}`},Pe={id:`6c9941eefcb59ba827d304f3336844f4a348ecb3f1b549a96d805b1c48412b6b`,name:`FlxSetupManage`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupManage($input: FlxSetupManageInput!) {
  flxSetupManage(input: $input) {
    done
    skipped
    failed
    summary
    errors {
      ...FlxSetupErrors
    }
  }
}

fragment FlxSetupErrors on FlxUserError {
  code
  message
  field
}`},Fe={id:`1641f892cadf8664b2298b4af2847db998d4c838826ebc0f08572a5ea648e637`,name:`FlxSetupOverview`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupOverview($search: String) {
  flxSetupOverview {
    connection {
      ...FlxSetupConnectionFields
    }
    tabs(search: $search) {
      tab
      label
      count
      updates
    }
    updateCount
    autoCheck
    failingSources {
      name
      error
    }
    backup {
      ready
      installed
      activatable
      installable
    }
    backupUrl
    settingsUrl
    wordpressUpdatesUrl
    canManageSources
  }
}

fragment FlxSetupConnectionFields on FlxSetupConnection {
  state
  title
  message
  host
  site
  checked {
    iso
    label
    relative
  }
  checkedLabel
  backingOff
  hasKey
  keyFromConfig
  packageCount
  updateCount
}`},Ie={id:`e8e1935e414a5f61d5841b22119e7874da774b8e7905d19e76333469f34eff77`,name:`FlxSetupPackages`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupPackages($tab: FlxSetupPackageTab, $search: String, $onlyUpdates: Boolean, $first: Int = 200, $after: String) {
  flxSetupPackages(
    tab: $tab
    search: $search
    onlyUpdates: $onlyUpdates
    first: $first
    after: $after
  ) {
    nodes {
      ...FlxSetupPackageRow
    }
    pageInfo {
      hasNextPage
      endCursor
    }
    totalCount
  }
}

fragment FlxSetupPackageRow on FlxSetupPackage {
  key
  group
  kind
  id
  name
  subtitle
  author
  state
  installed
  latest
  released {
    iso
    label
    relative
  }
  active
  iconUrl
  tags {
    label
    kind
  }
  category
  sourceName
  showSource
  sourceCount
  sourceNote
  requires {
    slug
    name
    state
    flux
  }
  autoUpdate {
    mode
    type
    id
    canChange
  }
  canUpdate
  canInstall
  canManage
  canChangeVersion
  versions {
    version
    older
    released {
      iso
      label
    }
  }
  description
  website
  images
  info {
    label
    value
  }
}`},Le={id:`91629093982ae065468fb3f63497bc77ac4c8950e085938617acebb155eb692e`,name:`FlxSetupPlan`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupPlan($slugs: [String!], $wpKeys: [String!], $all: Boolean, $activate: Boolean) {
  flxSetupPlan(slugs: $slugs, wpKeys: $wpKeys, all: $all, activate: $activate) {
    kind
    slug
    id
    name
    why
    version
  }
}`},Re={id:`20b2e2929728ec932fd886ce76780a3b35a35fc0045391ff82428916aa37984b`,name:`FlxSetupRetentionSave`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupRetentionSave($input: FlxSetupRetentionSaveInput!) {
  flxSetupRetentionSave(input: $input) {
    deleted
    retention {
      ...FlxSetupRetentionFields
    }
    errors {
      ...FlxSetupErrors
    }
  }
}

fragment FlxSetupErrors on FlxUserError {
  code
  message
  field
}

fragment FlxSetupRetentionFields on FlxSetupRetention {
  available
  days
  choices
  count
  size
  settingsUrl
}`},ze={id:`33e46578e9a32d1bff6a6ad7f8785580eb6208cbc4b806543fc964bf1a3048f7`,name:`FlxSetupSettingsSave`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupSettingsSave($input: FlxSetupSettingsSaveInput!) {
  flxSetupSettingsSave(input: $input) {
    settings {
      ...FlxSetupSettingsFields
    }
    connection {
      ...FlxSetupConnectionFields
    }
    sources {
      ...FlxSetupSourceFields
    }
    errors {
      ...FlxSetupErrors
    }
  }
}

fragment FlxSetupConnectionFields on FlxSetupConnection {
  state
  title
  message
  host
  site
  checked {
    iso
    label
    relative
  }
  checkedLabel
  backingOff
  hasKey
  keyFromConfig
  packageCount
  updateCount
}

fragment FlxSetupErrors on FlxUserError {
  code
  message
  field
}

fragment FlxSetupSettingsFields on FlxSetupSettings {
  server
  defaultServer
  serverFromConfig
  effectiveServer
  hasKey
  keyFromConfig
  auto
  health
  usageAvailable
  usage
  legal {
    kvkk
    privacy
    terms
  }
}

fragment FlxSetupSourceFields on FlxSetupSource {
  id
  name
  url
  type
  main
  enabled
  hasKey
  state
  message
  packageCount
  canMoveUp
}`},Be={id:`d8622d9c1c248a1d4c9dbc1667856303277e0b4f7b03ae820895203ddc6aa9ee`,name:`FlxSetupSettingsScreen`,kind:`query`,plugin:`flx-setup`,document:`query FlxSetupSettingsScreen {
  flxSetupSettings {
    ...FlxSetupSettingsFields
  }
  flxSetupConnection {
    ...FlxSetupConnectionFields
  }
  flxSetupSources {
    ...FlxSetupSourceFields
  }
  flxSetupGraphqlStatus {
    state
    version
    phpVersion
    phpMin
    phpOk
    actionUrl
    actionLabel
  }
}

fragment FlxSetupConnectionFields on FlxSetupConnection {
  state
  title
  message
  host
  site
  checked {
    iso
    label
    relative
  }
  checkedLabel
  backingOff
  hasKey
  keyFromConfig
  packageCount
  updateCount
}

fragment FlxSetupSettingsFields on FlxSetupSettings {
  server
  defaultServer
  serverFromConfig
  effectiveServer
  hasKey
  keyFromConfig
  auto
  health
  usageAvailable
  usage
  legal {
    kvkk
    privacy
    terms
  }
}

fragment FlxSetupSourceFields on FlxSetupSource {
  id
  name
  url
  type
  main
  enabled
  hasKey
  state
  message
  packageCount
  canMoveUp
}`},Ve={id:`f8f67e0723c3045d9ba05b6454009c8f931f5f3a6a25a5680ccb8e478d3b2f3e`,name:`FlxSetupSourceAdd`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupSourceAdd($input: FlxSetupSourceAddInput!) {
  flxSetupSourceAdd(input: $input) {
    sources {
      ...FlxSetupSourceFields
    }
    errors {
      ...FlxSetupErrors
    }
  }
}

fragment FlxSetupErrors on FlxUserError {
  code
  message
  field
}

fragment FlxSetupSourceFields on FlxSetupSource {
  id
  name
  url
  type
  main
  enabled
  hasKey
  state
  message
  packageCount
  canMoveUp
}`},$={id:`758bf6b4e9d2b43b0a4a2613eb688c94c8b73f0dd94f8e6efafd99fb74d5aa06`,name:`FlxSetupSourceChange`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupSourceChange($input: FlxSetupSourceChangeInput!) {
  flxSetupSourceChange(input: $input) {
    sources {
      ...FlxSetupSourceFields
    }
    errors {
      ...FlxSetupErrors
    }
  }
}

fragment FlxSetupErrors on FlxUserError {
  code
  message
  field
}

fragment FlxSetupSourceFields on FlxSetupSource {
  id
  name
  url
  type
  main
  enabled
  hasKey
  state
  message
  packageCount
  canMoveUp
}`},He={id:`562e895123bcf4698f2e0b64d5f322aeecdf71358e7d66ef3677e3ed3e190a89`,name:`FlxSetupStepRun`,kind:`mutation`,plugin:`flx-setup`,document:`mutation FlxSetupStepRun($input: FlxSetupStepRunInput!) {
  flxSetupStepRun(input: $input) {
    ok
    lines {
      ok
      text
    }
    pendingCount
    package {
      ...FlxSetupPackageRow
    }
    errors {
      ...FlxSetupErrors
    }
  }
}

fragment FlxSetupErrors on FlxUserError {
  code
  message
  field
}

fragment FlxSetupPackageRow on FlxSetupPackage {
  key
  group
  kind
  id
  name
  subtitle
  author
  state
  installed
  latest
  released {
    iso
    label
    relative
  }
  active
  iconUrl
  tags {
    label
    kind
  }
  category
  sourceName
  showSource
  sourceCount
  sourceNote
  requires {
    slug
    name
    state
    flux
  }
  autoUpdate {
    mode
    type
    id
    canChange
  }
  canUpdate
  canInstall
  canManage
  canChangeVersion
  versions {
    version
    older
    released {
      iso
      label
    }
  }
  description
  website
  images
  info {
    label
    value
  }
}`},Ue={id:`fca51d9372f393736cd5c7f3cc3a6c6f76bc42c714907f05360f59ce43283d8b`,name:`FlxTaskCommentCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCommentCreate($input: FlxTaskCommentCreateInput!) {
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
}`},We={id:`f4712b3032ffaf4580393cf0e6234154fa1eb66399b12784aa29f29b625d34bd`,name:`FlxTaskCreate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskCreate($input: FlxTaskCreateInput!) {
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
}`},Ge={id:`157053946bdf55aa4bf5f22a676119ec48e7bbe04c053867b7e0eedb11e04ec2`,name:`FlxTaskDelete`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskDelete($input: FlxTaskDeleteInput!) {
  flxTaskDelete(input: $input) {
    deletedId
    errors {
      code
      message
      field
    }
  }
}`},Ke={id:`fa1d3f71ca31ab2a7b72c1a987e295f209d6564e8a68e3643dee354a44544ecf`,name:`FlxTaskDetail`,kind:`query`,plugin:`flx-tasks`,document:`query FlxTaskDetail($id: ID!) {
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
}`},qe={id:`d357c8fdb6c372508b17bb93fa22ec80224c0ff43854104149816d70a9e80870`,name:`FlxTaskNotificationsRead`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskNotificationsRead($input: FlxTaskNotificationsReadInput!) {
  flxTaskNotificationsRead(input: $input) {
    unread
    errors {
      code
      message
      field
    }
  }
}`},Je={id:`004e85b9237b995957f75c2cfe2a996f5d2de6ca98b31c98511cb403df800ae3`,name:`FlxTaskUpdate`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdate($input: FlxTaskUpdateInput!) {
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
}`},Ye={id:`7c644e53f296f0dbf859b7bfcb4748e8c5be3ef891474fb47a262a2cd993be7a`,name:`FlxTaskUpdateDetail`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskUpdateDetail($input: FlxTaskUpdateInput!) {
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
}`},Xe={id:`a2c919c298da71f1634726a7c85c4344cf85f3602cbf54e4175bd8e45578e9b5`,name:`FlxTaskWatch`,kind:`mutation`,plugin:`flx-tasks`,document:`mutation FlxTaskWatch($input: FlxTaskWatchInput!) {
  flxTaskWatch(input: $input) {
    watcherIds
    errors {
      code
      message
      field
    }
  }
}`},Ze={id:`82a77154cc559fdb176a8880985bf3a64706b404515780458ee98ec4b66f3870`,name:`FlxThemeMigrate`,kind:`mutation`,plugin:`fluxwp`,document:`mutation FlxThemeMigrate($input: FlxThemeMigrateInput!) {
  flxThemeMigrate(input: $input) {
    log
    errors {
      code
      message
      field
    }
  }
}`},Qe={id:`fc5031368340a38a66f0982b81b94cbed677d41d75d84f75da83d39cbf71af4e`,name:`FlxThemeMigrationSources`,kind:`query`,plugin:`fluxwp`,document:`query FlxThemeMigrationSources {
  flxThemeMigrationSources {
    stylesheet
    lastRun {
      label
    }
  }
}`},$e={id:`3ed6b0ac119ee2d69851b315aa6bf0e730f23ff9b8a1b58d8df96549912a99fb`,name:`FlxThemePayLogosSave`,kind:`mutation`,plugin:`fluxwp`,document:`mutation FlxThemePayLogosSave($input: FlxThemePayLogosSaveInput!) {
  flxThemePayLogosSave(input: $input) {
    logos {
      ...FlxThemePayLogosFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxThemePayLogoRow on FlxThemePayLogo {
  key
  label
  url
  chip
}

fragment FlxThemePayLogosFields on FlxThemePayLogos {
  automatic
  current {
    ...FlxThemePayLogoRow
  }
  builtin {
    ...FlxThemePayLogoRow
  }
  automaticList {
    ...FlxThemePayLogoRow
  }
}`},et={id:`37837540607fffefd13b6fbddd55ddff4839e6df404bedaaef4da41084b3352f`,name:`FlxThemeSettings`,kind:`query`,plugin:`fluxwp`,document:`query FlxThemeSettings {
  flxThemeSettings {
    ...FlxThemeSettingsFields
  }
  pages(
    first: 100
    where: { status: PUBLISH, orderby: { field: TITLE, order: ASC } }
  ) {
    nodes {
      databaseId
      title
    }
  }
  flxThemePayLogos {
    ...FlxThemePayLogosFields
  }
}

fragment FlxThemePayLogoRow on FlxThemePayLogo {
  key
  label
  url
  chip
}

fragment FlxThemePayLogosFields on FlxThemePayLogos {
  automatic
  current {
    ...FlxThemePayLogoRow
  }
  builtin {
    ...FlxThemePayLogoRow
  }
  automaticList {
    ...FlxThemePayLogoRow
  }
}

fragment FlxThemeSettingsFields on FlxThemeSettings {
  visibility
  comingSoonTitle
  comingSoonText
  storeNoticeTitle
  storeNoticeText
  storeNoticeTitleDefault
  storeNoticeTextDefault
  contactEmail
  siteType
  pattern
  tableHead
  newsIntro
  darkMode
  animations
  pagination
  activeCard
  activeCampaign
  cookieEnabled
  cookieTitle
  cookieText
  cookieAccept
  cookieReject
  cookieMore
  cookiePage
  cookiePosition
  cookieRevision
  ga4Id
  gaMode
  consentScripts
  bankHolder
  bankIban
  bankInUse
  woocommerceActive
  activeCardAvailable
  canEditScripts
  presetsUrl
  campaigns {
    databaseId
    title
  }
}`},tt={id:`e8bf1a5cfdcfc9f66d6c5707ad00151b20e558b30ea400989b9547e3acac1ae6`,name:`FlxThemeSettingsSave`,kind:`mutation`,plugin:`fluxwp`,document:`mutation FlxThemeSettingsSave($input: FlxThemeSettingsSaveInput!) {
  flxThemeSettingsSave(input: $input) {
    settings {
      ...FlxThemeSettingsFields
    }
    errors {
      code
      message
      field
    }
  }
}

fragment FlxThemeSettingsFields on FlxThemeSettings {
  visibility
  comingSoonTitle
  comingSoonText
  storeNoticeTitle
  storeNoticeText
  storeNoticeTitleDefault
  storeNoticeTextDefault
  contactEmail
  siteType
  pattern
  tableHead
  newsIntro
  darkMode
  animations
  pagination
  activeCard
  activeCampaign
  cookieEnabled
  cookieTitle
  cookieText
  cookieAccept
  cookieReject
  cookieMore
  cookiePage
  cookiePosition
  cookieRevision
  ga4Id
  gaMode
  consentScripts
  bankHolder
  bankIban
  bankInUse
  woocommerceActive
  activeCardAvailable
  canEditScripts
  presetsUrl
  campaigns {
    databaseId
    title
  }
}`},nt={id:`2319101e3a871af11052c6fe8dcae1de118834cc9f13b232bd573001daca537c`,name:`FlxThemeSystem`,kind:`query`,plugin:`fluxwp`,document:`query FlxThemeSystem {
  flxThemeSystem {
    checkedAt {
      iso
      timestamp
      label
    }
    checks {
      id
      source
      sourceLabel
      group
      label
      status
      message
      actionUrl
      actionLabel
    }
    fixLogAvailable
    fixLog {
      time {
        timestamp
        label
      }
      user
      label
      value
      before
      after
      success
      message
    }
  }
}`};export{le as $,L as A,Be as At,J as B,Ye as Bt,A as C,Ne as Ct,P as D,Le as Dt,N as E,Ie as Et,H as F,We as Ft,Q as G,et as Gt,Y as H,Ze as Ht,U as I,Ge as It,ie as J,l as Jt,ne as K,tt as Kt,W as L,Ke as Lt,B as M,$ as Mt,z as N,He as Nt,F as O,Re as Ot,V as P,Ue as Pt,ce as Q,G as R,qe as Rt,k as S,Me as St,M as T,Fe as Tt,X as U,Qe as Ut,K as V,Xe as Vt,Z as W,$e as Wt,oe as X,a as Xt,ae as Y,i as Yt,se as Z,w as _,De as _t,te as a,he as at,D as b,Ae as bt,h as c,ve as ct,v as d,xe as dt,ue as et,y as f,Se as ft,C as g,Ee as gt,S as h,Te as ht,ee as i,me as it,R as j,Ve as jt,I as k,ze as kt,g as l,ye as lt,x as m,we as mt,d as n,fe as nt,p as o,ge as ot,b as p,Ce as pt,re as q,nt as qt,f as r,pe as rt,m as s,_e as st,u as t,de as tt,_ as u,be as ut,T as v,Oe as vt,j as w,Pe as wt,O as x,je as xt,E as y,ke as yt,q as z,Je as zt};