# @duxbo/seo-core

Client for [duxbo/laravel-seo](https://github.com/Dungnecauoi/laravel-seo).

**Logic only — this package renders nothing.** It holds the API client, the
types, and the state handling that every front end needs regardless of
framework: dirty tracking, debounced analysis, contract-version checking.

That split is deliberate. The hard part of an SEO panel is not the markup; it
is knowing what changed, when to re-score, and how to keep a Vietnamese
keyword matching after a copy-paste. Writing that once here means a React or
Vue adapter is a few hundred lines of rendering rather than a reimplementation.

```bash
npm install @duxbo/seo-core
```

```ts
import { createSeoClient, createMetaStore } from '@duxbo/seo-core'

const seo = createSeoClient({ baseUrl: 'https://api.example.com', token })

// Metadata for a page, in the shape your framework wants
const metadata = await seo.resolve('/bai-viet/xin-chao', { format: 'next' })

// An editing session: what changed, and what it scores
const store = createMetaStore(seo, { type: 'post', id: 42 })
await store.load()
store.set('title', 'Tiêu đề mới')
store.isDirty            // true
await store.save()
```

No dependencies. Requires `fetch`, so Node 18+ or any current browser.
