import{$ as e,tt as t}from"./flux-host-DxMV0p7O.js";var n=new Set([`UNAUTHENTICATED`,`FORBIDDEN`,`NOT_FOUND`,`INVALID_INPUT`,`CONFLICT`,`RATE_LIMITED`,`INTERNAL`,`PERSISTED_QUERY_NOT_FOUND`,`QUERY_NOT_ALLOWED`,`NETWORK`,`BAD_RESPONSE`]),r={401:`UNAUTHENTICATED`,403:`FORBIDDEN`,404:`NOT_FOUND`,429:`RATE_LIMITED`},i=class extends Error{constructor(e,i,a,o){let s=a[0];super(s?.message||o||`GraphQL request failed`),t(this,`code`,void 0),t(this,`field`,void 0),t(this,`errors`,void 0),t(this,`status`,void 0),t(this,`operation`,void 0),this.name=`FlxGraphQLError`,this.operation=e,this.status=i,this.errors=a;let c=s?.extensions?.code;this.code=c&&n.has(c)?c:o??r[i]??`INTERNAL`,this.field=typeof s?.extensions?.field==`string`?s.extensions.field:void 0}},a=e=>e instanceof i,o=`../graphql`,s=2e3,c=e=>Array.isArray(e)&&e.every(e=>typeof e==`object`&&!!e&&typeof e.message==`string`);function l(t){let n=t.persisted!==!1;async function r(e,n,r,i){let a=n&&typeof n==`object`&&Object.keys(n).length?n:void 0,c={raw:!0,throwOnError:!1,signal:i.signal,headers:{"X-Flux-GraphQL-Operation":e.name,...i.headers}};if(e.kind===`query`&&!r){let n={queryId:e.id,operationName:e.name,variables:a?JSON.stringify(a):void 0};if(Object.values(n).reduce((e,t)=>e+(t?encodeURIComponent(t).length+16:0),0)<s)return t.api(o,{...c,method:`GET`,query:n})}let l=r?{query:e.document,operationName:e.name,variables:a}:{queryId:e.id,operationName:e.name,variables:a};return t.api(o,{...c,method:`POST`,body:l})}async function a(t,...a){let o=a[0],s=a[1]??{},l;try{l=await r(t,o,!n,s),n&&l.data?.errors?.some(e=>e.extensions?.code===`PERSISTED_QUERY_NOT_FOUND`)&&(l=await r(t,o,!0,s))}catch(n){throw n instanceof e?new i(t.name,n.status,[{message:n.message}],n.code===`invalid_json`?`BAD_RESPONSE`:`NETWORK`):n}let u=l.data,d=c(u?.errors)?u.errors:[],f=u?.data;if(typeof f!=`object`||!f)throw new i(t.name,l.status,d.length?d:[{message:`Empty GraphQL response`}],d.length?void 0:`BAD_RESPONSE`);if(d.length){let e=new Set(d.map(e=>e.path?.[0]).filter(e=>typeof e==`string`));if([...e].some(e=>f[e]===null)||!e.size)throw new i(t.name,l.status,d)}return f}return{request:a}}var u={id:`7a2703962a923dbd66bbb68ae1b67a23777d1f64cd8eebb3f7f7e3ac839725c7`,name:`FlxCrmContact`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmContact($id: ID!) {
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
}`},d={id:`d64b39e87439228efb39cf454ec4b8156cf3e0fd2704751e0c30f58a4d4e8698`,name:`FlxCrmContactBulk`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactBulk($input: FlxContactBulkInput!) {
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
}`},f={id:`1ddbb8969d399e686efebc66dfd2669494b2a20c18beffe637a9d26bba6c9e94`,name:`FlxCrmContactCreate`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactCreate($input: FlxContactCreateInput!) {
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
}`},p={id:`e5ac09b6da29cd8a29870ad1eeecceb4e0930a083afacb5cb0f0823727443497`,name:`FlxCrmContactDelete`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactDelete($input: FlxContactDeleteInput!) {
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
}`},m={id:`5b37df020f5fcf7eb644d59e7d43255a10daf12b3e1e81becfe80e6be2b63666`,name:`FlxCrmContactMerge`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactMerge($input: FlxContactMergeInput!) {
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
}`},h={id:`78fef294f41cc0264573687c40208fb3d18ade9ed42cf1d4328c25414ceb2aa3`,name:`FlxCrmContacts`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmContacts($filter: FlxContactFilterInput, $orderBy: FlxContactOrderBy, $order: FlxContactOrder, $first: Int = 25, $after: String) {
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
}`},g={id:`5c13b0d3b065e0dd85f87fc9e53cbb77e20f0338223bcdd15c5a57a504623083`,name:`FlxCrmContactUpdate`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmContactUpdate($input: FlxContactUpdateInput!) {
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
}`},_={id:`d8cbff1ab3075cba9ae5ea057a6d68195e6aaa27a724bbf9c924efa5974873cd`,name:`FlxCrmDuplicates`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmDuplicates {
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
}`},v={id:`fe10d4a31eedbbd448e3769a47a190a8872c2bdad2256def5f7e54cdf091eae0`,name:`FlxCrmEventAdd`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmEventAdd($input: FlxContactEventAddInput!) {
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
}`},y={id:`a76a713193a0c8d25b06c56932ac7a945251d1c2966e1e35351c2b154124d10d`,name:`FlxCrmEventDelete`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmEventDelete($input: FlxContactEventDeleteInput!) {
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
}`},b={id:`e4410099ca450172284928b787d6653e3a20bb9fea759f3405dcc65b4b2d60fc`,name:`FlxCrmJourneyDelete`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmJourneyDelete($input: FlxJourneyDeleteInput!) {
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
}`},x={id:`81f33cb7df2b01fe473af742d3664bc98d855fff2d8d6c6bd10bf813b3126a00`,name:`FlxCrmJourneyFromTemplate`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmJourneyFromTemplate($input: FlxJourneyFromTemplateInput!) {
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
}`},S={id:`1d65e68f34e737d57ba69b8f75f693f31df5df46147478680767d4a886eba9a4`,name:`FlxCrmJourneys`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmJourneys {
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
}`},C={id:`bb7940aae0aa4487e6596f7310dfe9d6c66cd05f801d9d2571a2fbd024003e89`,name:`FlxCrmJourneySave`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmJourneySave($input: FlxJourneySaveInput!) {
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
}`},w={id:`271eb15db3d24fbf7acc5848820ed761c6fdcece95a02526b41ff2a7316997a2`,name:`FlxCrmJourneySetStatus`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmJourneySetStatus($input: FlxJourneySetStatusInput!) {
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
}`},T={id:`172de029371f50368a15f9bd18e6643d27d6d370593aeac6979a894b17a7bf8b`,name:`FlxCrmOverview`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmOverview {
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
}`},E={id:`e0edd7dec5bacd4fa0efa1061b492dfd4514405725805c3d2dad8e334f1fa625`,name:`FlxCrmScoreRun`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmScoreRun($input: FlxCrmScoreRunInput!) {
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
}`},D={id:`2f13b0c4d23cfcf18bb040c35a545c3899b8be9c777bef71b9a3a1889584e38c`,name:`FlxCrmSegmentDelete`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmSegmentDelete($input: FlxSegmentDeleteInput!) {
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
}`},O={id:`acc491cf70a4b00af7fce340efd2fd26dc087fe84690b01a364966abf7dea880`,name:`FlxCrmSegmentEditor`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmSegmentEditor {
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
}`},k={id:`564b5e761395c08e8045dbc65f96f00b7f4fa14bc2dcfa48e28a0beff5edd00e`,name:`FlxCrmSegmentPreview`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmSegmentPreview($match: FlxSegmentMatch, $rules: [FlxSegmentRuleInput!]!) {
  flxSegmentPreview(match: $match, rules: $rules) {
    count
    summary
    sample {
      databaseId
      name
    }
  }
}`},A={id:`6035e1dbb5301cebeb87fa3481cf44003f54cdd356668d7d22ff87b96ec87866`,name:`FlxCrmSegmentSave`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmSegmentSave($input: FlxSegmentSaveInput!) {
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
}`},j={id:`4e800655079b39d7b0c8f5dca828111815df5e95cd0e91406785150732efd4a3`,name:`FlxCrmSettings`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmSettings {
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
}`},M={id:`6a79c573c70d3bbbdef5121997576cc4a22dfbd6b0a88b874a7029fa85cccbea`,name:`FlxCrmSettingsSave`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmSettingsSave($input: FlxCrmSettingsSaveInput!) {
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
}`},N={id:`3a59d8e4195bf7ba635933d4e7baafd37f013da4a16f4d4cfc49b7501f3a7de4`,name:`FlxCrmShell`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmShell {
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
}`},P={id:`91833bc806371dcec389dec7ee846bdc3806edd84b27da2b516ee064b794c6a5`,name:`FlxCrmSyncStep`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmSyncStep($input: FlxCrmSyncStepInput!) {
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
}`},F={id:`803c73840e66e14fc6312eb733e95eb00bb1261c0fcdcaa93508c07d05751c35`,name:`FlxCrmTagAdd`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmTagAdd($input: FlxContactTagAddInput!) {
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
}`},I={id:`68836030d68ed1f45df0595812ec3fbbf361855944537c066a08b8ea641057fa`,name:`FlxCrmTagRemove`,kind:`mutation`,plugin:`flx-crm`,document:`mutation FlxCrmTagRemove($input: FlxContactTagRemoveInput!) {
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
}`},L={id:`b019ec38b06648c194238f86b99019a21dcdd4ae0092c44cddbc896a1421275c`,name:`FlxCrmTimeline`,kind:`query`,plugin:`flx-crm`,document:`query FlxCrmTimeline($id: ID!, $filter: String, $first: Int = 20, $after: String) {
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
}`};export{z as A,Y as B,M as C,I as D,F as E,W as F,l as G,Z as H,G as I,i as K,K as L,V as M,H as N,L as O,U as P,q as R,j as S,P as T,Q as U,X as V,$ as W,E as _,m as a,k as b,_ as c,b as d,x as f,T as g,S as h,p as i,B as j,R as k,v as l,w as m,d as n,g as o,C as p,a as q,f as r,h as s,u as t,y as u,D as v,N as w,A as x,O as y,J as z};