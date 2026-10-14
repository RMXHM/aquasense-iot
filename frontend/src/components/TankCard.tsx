import { TankVisual } from './TankVisual'
import { ConditionBadge, SourceBadge } from './Ui'
import { batteryStyle, formatLitres, formatPercent, formatRelative, rssiLabel } from '../lib/format'
import type { Tank } from '../api/types'

/**
 * One tank on the customer overview.
 *
 * The three numbers a customer actually needs - percentage, litres and
 * condition - are given equal visual weight, with technical detail below.
 */
export function TankCard({ tank, onOpen }: { tank: Tank; onOpen: (id: number) => void }) {
  const { state, device, current } = tank
  const offline = state.readings_stale || !state.device_online

  return (
    <button
      type="button"
      onClick={() => onOpen(tank.id)}
      className="panel group w-full p-5 text-left transition duration-300 hover:-translate-y-0.5 hover:border-aqua-400/60 hover:shadow-lg hover:shadow-aqua-400/10"
    >
      <div className="flex items-start justify-between gap-3">
        <div>
          <h3 className="text-base font-semibold text-foam-100">{tank.name}</h3>
          <p className="mt-0.5 text-xs text-slate-400">
            {tank.shape === 'cylinder' ? 'Vertical cylinder' : 'Rectangular tank'} ·{' '}
            {formatLitres(tank.capacity_litres)} L capacity
          </p>
        </div>
        <ConditionBadge condition={tank.condition} />
      </div>

      <div className="mt-5 flex items-center gap-5">
        <TankVisual
          percent={current?.percent_full ?? null}
          capacityLitres={tank.capacity_litres}
          volumeLitres={current?.volume_litres ?? null}
          size="sm"
          shape={tank.shape}
          condition={tank.condition}
          stale={state.readings_stale}
        />

        <dl className="flex-1 space-y-2.5 text-xs">
          <div className="flex items-center justify-between gap-2">
            <dt className="text-slate-400">Water remaining</dt>
            <dd className="font-semibold tabular-nums text-foam-100">
              {current?.volume_litres != null ? `${formatLitres(current.volume_litres)} L` : '--'}
            </dd>
          </div>
          <div className="flex items-center justify-between gap-2">
            <dt className="text-slate-400">Filled</dt>
            <dd className="font-semibold tabular-nums text-foam-100">
              {formatPercent(current?.percent_full ?? null)}
            </dd>
          </div>
          <div className="flex items-center justify-between gap-2">
            <dt className="text-slate-400">Device</dt>
            <dd className={offline ? 'text-rose-300' : 'text-tide-300'}>
              {offline ? 'Offline' : 'Online'}
            </dd>
          </div>
          <div className="flex items-center justify-between gap-2">
            <dt className="text-slate-400">Battery</dt>
            <dd className={`tabular-nums ${batteryStyle(device?.battery_percent)}`}>
              {device?.battery_percent != null ? `${device.battery_percent}%` : '--'}
            </dd>
          </div>
          <div className="flex items-center justify-between gap-2">
            <dt className="text-slate-400">Last reading</dt>
            <dd className="text-slate-300">{formatRelative(current?.measured_at)}</dd>
          </div>
        </dl>
      </div>

      <div className="mt-4 flex flex-wrap items-center gap-2 border-t border-depth-400/25 pt-3">
        {device && <SourceBadge source={device.source} />}
        {state.unacknowledged_alerts > 0 && (
          <span className="rounded-full border border-amber-300/40 bg-amber-300/12 px-2.5 py-1 text-xs font-medium text-amber-200">
            {state.unacknowledged_alerts} new alert{state.unacknowledged_alerts === 1 ? '' : 's'}
          </span>
        )}
        {device?.wifi_rssi != null && (
          <span className="text-xs text-slate-500">Wi-Fi {rssiLabel(device.wifi_rssi)}</span>
        )}
        <span className="ml-auto text-xs text-aqua-300 transition group-hover:translate-x-0.5">
          View details →
        </span>
      </div>

      {offline && current?.measured_at && (
        <p className="mt-3 rounded-lg border border-amber-300/30 bg-amber-300/10 px-3 py-2 text-xs text-amber-100">
          Showing the last measurement from {formatRelative(current.measured_at)}. This value is not
          live — the device has not reported recently.
        </p>
      )}
    </button>
  )
}
