# @duxbo/seo-vue

Vue composables and a Tailwind-styled panel for [duxbo/laravel-seo](https://github.com/Dungnecauoi/laravel-seo),
built on `@duxbo/seo-core`.

```bash
npm install @duxbo/seo-vue
```

```vue
<script setup lang="ts">
import { useSeoClient, SeoPanel } from '@duxbo/seo-vue'

const props = defineProps<{ postId: number; postBody: string }>()
const client = useSeoClient({ baseUrl: 'https://example.com', token })
</script>

<template>
  <SeoPanel :client="client" :target="{ type: 'post', id: postId }" :content="postBody" @saved="onSaved" />
</template>
```

`SeoPanel` is a render-function component (`h()`), not a `.vue` SFC — the
package builds with plain `tsc`, no bundler or SFC compiler needed for one
component.

Or use the composable directly for a custom layout:

```ts
const { store, set, save } = useMetaStore(client, { type: 'post', id: postId })
store.draft.title
store.isDirty
set('title', 'Tiêu đề mới')
```

`target` also accepts a getter, which is what a page that navigates between
records without remounting needs — a plain object is only read once, at
`setup()` time:

```ts
useMetaStore(client, () => ({ type: 'post', id: route.params.id }))
```

## Admin shell

`SeoPanel` edits one record. Five more components build the rest of an admin
surface — a dashboard, a content list, redirect CRUD, a 404 monitor, and a
read-only settings view — each self-contained, fetching through the same
`SeoClient`, and each a render-function component like `SeoPanel`:

```vue
<script setup lang="ts">
import { SeoDashboard, SeoContentList, SeoRedirects, SeoNotFoundMonitor, SeoSettings } from '@duxbo/seo-vue'
</script>

<template>
  <SeoDashboard :client="client" @select-type="(type) => router.push(`/admin/seo/content?type=${type}`)" />
  <SeoContentList :client="client" type="post" @edit="(type, id) => router.push(`/admin/seo/${type}/${id}`)" />
  <SeoRedirects :client="client" />
  <SeoNotFoundMonitor :client="client" />
  <SeoSettings :client="client" />
</template>
```

None of them route — `selectType` and `edit` emit navigation intent back to
the host app rather than assuming a router. They talk to `/api/seo/v1`, the
same REST API `useMetaStore` uses, so they need `seo.api.enabled = true` and
the same Gate as everything else in this package. `NotFoundEntry.path` (and
its `referrer`/`user_agent`) is already HTML-escaped by the API —
`SeoNotFoundMonitor` renders it with `v-html` for that reason, not despite
it: plain text interpolation would double-escape it into literal `&lt;` text
instead of the path Google actually requested.

## Tailwind

`SeoPanel` renders utility classes; it ships no CSS of its own. Add this
package's output to Tailwind's content globs, or the classes are purged and
the panel renders unstyled:

```js
// tailwind.config.js
content: ['./resources/**/*.blade.php', './node_modules/@duxbo/seo-vue/dist/*.js']
```
