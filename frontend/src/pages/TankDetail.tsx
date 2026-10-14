import { api } from '../api/client'
import { usePolling } from '../hooks/usePolling'
import { ConditionBadge, DemoBanner, ErrorState, Spinner } from '../components/Ui'
import { TankVisual } from '../components/TankVisual'
import { HistoryChart } from '../components/HistoryChart'
import { SimulationPanel } from '../components/SimulationPanel'
import { DevicePanel } from '../components/DevicePanel'
import {
  formatCubicMeters,
  formatDate,
  formatLitres,
  formatPercent,
  formatRelative,
} from '../lib/format'
import { useState } from 'react'
import type { Alert } from '../api/types'

/**
 * Tank detail: large visualisation, history, recent measurements, alerts,
 * device health and editable configuration.
 */
export function TankDetailPage({ tankId, onBack }: { tankId: number; onBack: () => void }) {
  const [tab, setTab] = useState<'history' | 'settings'>('history')

  const { data, error, loading, refresh } = usePolling(() => api.tank(tankId), 12000)

  if (loading && !data) return <Spinner label="Loading tank" />
  if (error && !data) return <ErrorState message={error} onRetry={refresh} />
  if (!data) return null

  const tank = data.data
  const { state, current } = tank
  const offline = state.readings_stale || !state.device_online

  return (
    <div className="space-y-6">
      <button
        type="button"
        onClick={onBack}
        className="text-sm text-aqua-300 transition hover:text-aqua-400"
      >
        ← All tanks
      </button>

      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <div className="flex flex-wrap items-center gap-3">
            <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">{tank.name}</h1>
            <ConditionBadge condition={tank.condition} />
          </div>
          <p className="mt-1 text-sm text-slate-400">
            {formatLitres(tank.capacity_litres)} L capacity ·{' '}
            {tank.shape === 'cylinder' ? 'vertical cylinder' : 'rectangular'} · tank height{' '}
            {tank.height_cm} cm · sensor mounted {tank.sensor_height_cm} cm above the tank bottom
          </p>
        </div>
        <button
          type="button"
          onClick={refresh}
          className="rounded-lg border border-depth-400/50 px-3 py-1.5 text-xs font-medium text-aqua-300 transition hover:border-aqua-400 hover:bg-aqua-400/10"
        >
          Refresh
        </button>
      </header>

      {tank.demo && <DemoBanner />}

      {offline && current?.measured_at && (
        <p className="rounded-lg border border-amber-300/35 bg-amber-300/10 px-4 py-3 text-sm text-amber-100">
          <strong className="font-semibold">Readings are not current.</strong> The last measurement
          arrived {formatRelative(current.measured_at)}
          {state.minutes_since_reading !== null && ` (${Math.round(state.minutes_since_reading)} minutes ago)`}.
          The level below is the last known good value, not a live reading. A battery-powered device
          sleeps between reports, so a short gap can be normal.
        </p>
      )}

      <div className="grid gap-4 lg:grid-cols-[auto_1fr]">
        <div className="panel flex flex-col items-center justify-center p-6">
          <TankVisual
            percent={current?.percent_full ?? null}
            capacityLitres={tank.capacity_litres}
            volumeLitres={current?.volume_litres ?? null}
            size="lg"
            shape={tank.shape}
            condition={tank.condition}
            stale={state.readings_stale}
          />
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <Metric
            label="Estimated water remaining"
            value={current?.volume_litres != null ? `${formatLitres(current.volume_litres)} L` : '--'}
            hint={formatCubicMeters(current?.volume_cubic_meters)}
          />
          <Metric
            label="Filled"
            value={formatPercent(current?.percent_full ?? null)}
            hint={`${current?.water_height_cm != null ? `${current.water_height_cm} cm water column` : 'water column unknown'}`}
          />
          <Metric
            label="Last measurement"
            value={current?.measured_at ? formatRelative(current.measured_at) : 'never'}
            hint={current?.measured_at ? `${formatDate(current.measured_at)} · sensor ${current.distance_cm} cm from surface` : undefined}
          />
          <Metric
            label="Low-water warning at"
            value={`${tank.low_water_threshold_percent}%`}
            hint={`about ${formatLitres((tank.capacity_litres * tank.low_water_threshold_percent) / 100)} L`}
          />
        </div>
      </div>

      <HistoryChart history={data.history} lowWaterThreshold={tank.low_water_threshold_percent} />

      <div className="flex gap-2 border-b border-depth-400/25">
        {(['history', 'settings'] as const).map((key) => (
          <button
            key={key}
            type="button"
            onClick={() => setTab(key)}
            className={`-mb-px border-b-2 px-4 py-2 text-sm font-medium capitalize transition ${
              tab === key
                ? 'border-aqua-400 text-aqua-300'
                : 'border-transparent text-slate-400 hover:text-slate-200'
            }`}
          >
            {key === 'history' ? 'Measurements & alerts' : 'Device & settings'}
          </button>
        ))}
      </div>

      {tab === 'history' ? (
        <div className="grid gap-4 lg:grid-cols-2">
          <AlertList alerts={data.alerts} onAcknowledged={refresh} />
          <ReadingsTable readings={data.history.slice().reverse()} capacity={tank.capacity_litres} />
        </div>
      ) : (
        <div className="grid gap-4 lg:grid-cols-2">
          <DevicePanel tank={tank} onChanged={refresh} />
          <SimulationPanel tank={tank} onChanged={refresh} />
        </div>
      )}
    </div>
  )
}

function Metric({ label, value, hint }: { label: string; value: string; hint?: string }) {
  return (
    <div className="panel px-4 py-3">
      <p className="text-xs uppercase tracking-wide text-slate-400">{label}</p>
      <p className="mt-1 text-xl font-semibold tabular-nums text-foam-100">{value}</p>
      {hint && <p className="mt-0.5 text-xs text-slate-500">{hint}</p>}
    </div>
  )
}

const SEVERITY_STYLES: Record<string, string> = {
  info: 'border-aqua-400/35 bg-aqua-400/10 text-aqua-200',
  warning: 'border-amber-300/35 bg-amber-300/10 text-amber-100',
  critical: 'border-rose-400/40 bg-rose-400/12 text-rose-100',
}

export function AlertList({ alerts, onAcknowledged }: { alerts: Alert[]; onAcknowledged: () => void }) {
  const [busyId, setBusyId] = useState<number | null>(null)

  const acknowledge = async (id: number) => {
    setBusyId(id)
    try {
      await api.acknowledgeAlert(id)
      onAcknowledged()
    } finally {
      setBusyId(null)
    }
  }

  return (
    <div className="panel p-5">
      <h3 className="text-sm font-semibold text-foam-100">Warnings and events</h3>
      {alerts.length === 0 ? (
        <p className="mt-4 text-sm text-slate-400">
          No alerts recorded. Warnings about low water, unusual level changes, sensor errors and lost
          contact will appear here.
        </p>
      ) : (
        <ul className="mt-4 space-y-3">
          {alerts.map((alert) => (
            <li
              key={alert.id}
              className={`rounded-lg border px-3 py-2.5 ${
                SEVERITY_STYLES[alert.severity] ?? SEVERITY_STYLES.info
              } ${alert.acknowledged ? 'opacity-55' : ''}`}
            >
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="text-sm font-semibold">
                    {alert.title}
                    {alert.acknowledged && (
                      <span className="ml-2 text-xs font-normal text-slate-400">acknowledged</span>
                    )}
                  </p>
                  <p className="mt-1 text-xs leading-relaxed">{alert.message}</p>
                  <p className="mt-1.5 text-[11px] text-slate-400">
                    {formatDate(alert.created_at)} ·{' '}
                    {new Date(alert.created_at).toLocaleTimeString('en-GB', {
                      hour: '2-digit',
                      minute: '2-digit',
                    })}{' '}
                    · {alert.type.replace(/_/g, ' ')}
                  </p>
                </div>
                {!alert.acknowledged && (
                  <button
                    type="button"
                    onClick={() => void acknowledge(alert.id)}
                    disabled={busyId === alert.id}
                    className="shrink-0 rounded-lg border border-current/40 px-2.5 py-1 text-xs font-medium transition hover:bg-white/10 disabled:opacity-50"
                  >
                    {busyId === alert.id ? '…' : 'Acknowledge'}
                  </button>
                )}
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

interface ReadingRow {
  measured_at: string
  percent_full: number | null
  volume_litres: number | null
  distance_cm: number | null
  sensor_status: string
  valid: boolean
}

export function ReadingsTable({ readings, capacity }: { readings: ReadingRow[]; capacity: number }) {
  const rows = readings.slice(0, 12)

  return (
    <div className="panel p-5">
      <h3 className="text-sm font-semibold text-foam-100">Recent measurements</h3>
      {rows.length === 0 ? (
        <p className="mt-4 text-sm text-slate-400">
          No measurements yet. Use the simulation controls to generate readings.
        </p>
      ) : (
        <div className="mt-4 overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead>
              <tr className="text-slate-400">
                <th className="pb-2 font-medium">Time</th>
                <th className="pb-2 font-medium">Distance</th>
                <th className="pb-2 text-right font-medium">Level</th>
                <th className="pb-2 text-right font-medium">Volume</th>
                <th className="pb-2 font-medium">Status</th>
              </tr>
            </thead>
            <tbody className="tabular-nums">
              {rows.map((reading, index) => (
                <tr key={`${reading.measured_at}-${index}`} className="border-t border-depth-400/20">
                  <td className="py-2 text-slate-300">
                    {new Date(reading.measured_at).toLocaleTimeString('en-GB', {
                      hour: '2-digit',
                      minute: '2-digit',
                    })}
                  </td>
                  <td className="py-2 text-slate-400">
                    {reading.distance_cm != null ? `${reading.distance_cm} cm` : '--'}
                  </td>
                  <td className="py-2 text-right text-foam-100">
                    {formatPercent(reading.percent_full)}
                  </td>
                  <td className="py-2 text-right text-foam-100">
                    {reading.volume_litres != null ? `${formatLitres(reading.volume_litres)} L` : '--'}
                  </td>
                  <td className="py-2">
                    <span className={reading.valid ? 'text-tide-300' : 'text-rose-300'}>
                      {reading.valid ? 'accepted' : reading.sensor_status.replace(/_/g, ' ')}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          <p className="mt-3 text-[11px] text-slate-500">
            Capacity {formatLitres(capacity)} L. Distance is what the sensor measured; level and
            volume are derived on the server from the tank geometry.
          </p>
        </div>
      )}
    </div>
  )
}

