import { useMemo } from 'react'
import { createSeoClient } from '@duxbo/seo-core'
import type { SeoClient, SeoClientOptions } from '@duxbo/seo-core'

/**
 * Memoises a client for the component's lifetime.
 *
 * `options` is expected to be referentially stable — a literal passed inline
 * on every render defeats the memo and rebuilds the client each time. Define
 * it outside the component, or wrap it in `useMemo` yourself.
 */
export function useSeoClient(options: SeoClientOptions): SeoClient {
  // eslint-disable-next-line react-hooks/exhaustive-deps
  return useMemo(() => createSeoClient(options), [options.baseUrl, options.token, options.prefix])
}
