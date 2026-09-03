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

## Tailwind

`SeoPanel` renders utility classes; it ships no CSS of its own. Add this
package's output to Tailwind's content globs, or the classes are purged and
the panel renders unstyled:

```js
// tailwind.config.js
content: ['./resources/**/*.blade.php', './node_modules/@duxbo/seo-vue/dist/*.js']
```
