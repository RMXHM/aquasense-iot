import { api } from '../api/client'
import { usePolling } from '../hooks/usePolling'
import { ConnectionNotice, DemoBanner, EmptyState, ErrorState, Spinner } from '../components/Ui'
import { TankCard } from '../components/TankCard'
import type { Tank } from '../api/types'

/**
 * Customer overview: every registered tank as a card.
 *
 * All values come from the API. Nothing here is seeded or hardcoded - if the
 * backend has no tanks, the empty state explains what to do next.
 */
export function Overview({
  onOpenTank,
  onActivate,
}: {
  onOpenTank: (id: number) => void
  onActivate: () => void
}) {
  const { data, error, loading, stale, lastUpdated, refresh } = usePolling(
    () => api.overview(),
    15000,
  )

  const tanks = data?.data ?? []

  const totalLitres = tanks.reduce((sum: number, tank: Tank) => {
    return sum + (tank.current?.volume_litres ?? 0)
  }, 0)
  const totalCapacity = tanks.reduce((sum: number, tank: Tank) => sum + tank.capacity_litres, 0)
  const needsAttention = tanks.filter(
    (tank: Tank) => tank.condition === 'Low Water' || tank.condition === 'Sensor Offline',
  ).length

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">Your tanks</h1>
          <p className="mt-1 text-sm text-slate-400">
            Live water level, estimated volume and device health for every registered tank.
          </p>
        </div>
        <div className="flex items-center gap-3">
          <ConnectionNotice lastUpdated={lastUpdated} />
          <button
            type="button"
            onClick={refresh}
            className="rounded-lg border border-depth-400/50 px-3 py-1.5 text-xs font-medium text-aqua-300 transition hover:border-aqua-400 hover:bg-aqua-400/10"
          >
            Refresh
          </button>
        </div>
      </header>

      <DemoBanner />

      {tanks.length > 0 && (
        <div className="grid gap-4 sm:grid-cols-3">
          <Stat label="Tanks monitored" value={String(tanks.length)} />
          <Stat
            label="Water available"
            value={`${Math.round(totalLitres).toLocaleString('en-GB')} L`}
            hint={`of ${Math.round(totalCapacity).toLocaleString('en-GB')} L total capacity`}
          />
          <Stat
            label="Needs attention"
            value={String(needsAttention)}
            hint={needsAttention === 0 ? 'all tanks nominal' : 'low water or offline'}
            tone={needsAttention > 0 ? 'warn' : 'ok'}
          />
        </div>
      )}

      {stale && tanks.length > 0 && (
        <p className="rounded-lg border border-amber-300/30 bg-amber-300/10 px-4 py-2 text-xs text-amber-100">
          Showing the last successful data — the API could not be reached just now.
        </p>
      )}

      {loading && <Spinner label="Loading your tanks" />}

      {!loading && error && <ErrorState message={error} onRetry={refresh} />}

      {!loading && !error && tanks.length === 0 && (
        <EmptyState
          title="No tanks yet"
          description="Register an AquaSense device to start monitoring a tank. In demo mode you can claim the demo device with the credentials in the setup guide."
          action={
            <button
              type="button"
              onClick={onActivate}
              className="mt-2 rounded-lg bg-depth-500 px-4 py-2 text-sm font-semibold text-foam-100 transition hover:bg-depth-400"
            >
              Register a device
            </button>
          }
        />
      )}

      {!loading && tanks.length > 0 && (
        <div className="grid gap-4 lg:grid-cols-2">
          {tanks.map((tank: Tank) => (
            <TankCard key={tank.id} tank={tank} onOpen={onOpenTank} />
          ))}
        </div>
      )}

      <div className="flex flex-wrap gap-3">
        <button
          type="button"
          onClick={onActivate}
          className="rounded-lg border border-depth-400/50 px-4 py-2 text-sm font-medium text-aqua-300 transition hover:border-aqua-400 hover:bg-aqua-400/10"
        >
          Register another device
        </button>
      </div>
    </div>
  )
}

function Stat({
  label,
  value,
  hint,
  tone = 'ok',
}: {
  label: string
  value: string
  hint?: string
  tone?: 'ok' | 'warn'
}) {
  return (
    <div className="panel px-4 py-3">
      <p className="text-xs uppercase tracking-wide text-slate-400">{label}</p>
      <p
        className={`mt-1 text-xl font-semibold tabular-nums ${
          tone === 'warn' ? 'text-amber-200' : 'text-foam-100'
        }`}
      >
        {value}
      </p>
      {hint && <p className="mt-0.5 text-xs text-slate-500">{hint}</p>}
    </div>
  )
}
