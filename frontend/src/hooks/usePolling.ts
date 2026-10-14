import { useCallback, useEffect, useRef, useState } from 'react'

interface PollState<T> {
  data: T | null
  error: string | null
  loading: boolean
  /** True when the last refresh failed but older data is still on screen. */
  stale: boolean
  lastUpdated: Date | null
  refresh: () => void
}

/**
 * Polls the API on an interval.
 *
 * Polling (rather than WebSockets) is deliberate: the device is a sleeping,
 * battery-powered sensor that reports every few minutes, so a sub-minute
 * refresh is already faster than the data itself. A previous successful
 * response is kept visible if a later refresh fails.
 */
export function usePolling<T>(
  fetcher: () => Promise<T>,
  intervalMs = 15000,
  enabled = true,
): PollState<T> {
  const [data, setData] = useState<T | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [stale, setStale] = useState(false)
  const [lastUpdated, setLastUpdated] = useState<Date | null>(null)
  const [tick, setTick] = useState(0)

  const fetcherRef = useRef(fetcher)
  fetcherRef.current = fetcher

  useEffect(() => {
    if (!enabled) return

    let cancelled = false
    let timer: number | undefined

    const run = async () => {
      try {
        const result = await fetcherRef.current()
        if (cancelled) return
        setData(result)
        setError(null)
        setStale(false)
        setLastUpdated(new Date())
      } catch (err) {
        if (cancelled) return
        const message = err instanceof Error ? err.message : 'Unknown error'
        setError(message)
        // Only call the view stale when we still have something to show.
        setStale((previous) => previous || true)
      } finally {
        if (!cancelled) setLoading(false)
        if (!cancelled && intervalMs > 0) {
          timer = window.setTimeout(run, intervalMs)
        }
      }
    }

    void run()

    return () => {
      cancelled = true
      if (timer) window.clearTimeout(timer)
    }
  }, [intervalMs, enabled, tick])

  const refresh = useCallback(() => setTick((value) => value + 1), [])

  return { data, error, loading, stale, lastUpdated, refresh }
}
