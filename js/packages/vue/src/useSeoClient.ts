import { computed, type ComputedRef, type MaybeRefOrGetter, toValue } from 'vue'
import { createSeoClient } from '@duxbo/seo-core'
import type { SeoClient, SeoClientOptions } from '@duxbo/seo-core'

/**
 * A client rebuilt only when the resolved options actually change.
 */
export function useSeoClient(options: MaybeRefOrGetter<SeoClientOptions>): ComputedRef<SeoClient> {
  return computed(() => createSeoClient(toValue(options)))
}
