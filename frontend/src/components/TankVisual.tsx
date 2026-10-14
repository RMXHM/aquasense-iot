import { useEffect, useState } from 'react'
import { formatLitres, formatPercent } from '../lib/format'
import type { Condition } from '../api/types'

interface TankVisualProps {
  /** Current fill percentage, 0-100. Server-computed; never fabricated here. */
  percent: number | null
  capacityLitres: number
  volumeLitres: number | null
  size?: 'sm' | 'md' | 'lg'
  shape?: 'cylinder' | 'rectangle'
  condition?: Condition
  /** When true the value is known to be old rather than live. */
  stale?: boolean
}

/**
 * Animated vertical tank visualisation.
 *
 * The animation only interpolates the height between real server values - it
 * never generates intermediate readings, so the picture cannot disagree with
 * the numbers.
 */
export function TankVisual({
  percent,
  capacityLitres,
  volumeLitres,
  size = 'md',
  shape = 'cylinder',
  condition,
  stale = false,
}: TankVisualProps) {
  const target = Math.max(0, Math.min(100, percent ?? 0))
  const [displayed, setDisplayed] = useState(target)

  // Ease the fill height toward the newest real measurement.
  useEffect(() => {
    setDisplayed((current) => current)
    const id = window.setTimeout(() => setDisplayed(target), 60)
    return () => window.clearTimeout(id)
  }, [target])

  const heightClass = { sm: 'h-28 w-16', md: 'h-44 w-24', lg: 'h-72 w-40' }[size]
  const radiusClass = shape === 'cylinder' ? 'rounded-[40%]' : 'rounded-xl'
  const labelSize = { sm: 'text-[10px]', md: 'text-xs', lg: 'text-sm' }[size]

  const offline = condition === 'Sensor Offline' || condition === 'Sensor Error'
  const noData = percent === null

  const fillColor = offline
    ? 'from-slate-600/70 to-slate-700/70'
    : stale
      ? 'from-depth-500/70 to-aqua-400/70'
      : target <= 20
        ? 'from-amber-500/80 to-amber-300/80'
        : target >= 95
          ? 'from-aqua-400 to-tide-300'
          : 'from-depth-500 to-aqua-400'

  return (
    <div className="flex flex-col items-center gap-3">
      <div
        className={`relative ${heightClass} ${radiusClass} overflow-hidden border border-depth-400/40 bg-abyss-900/80 shadow-inner`}
        role="img"
        aria-label={
          noData
            ? 'Tank level unknown'
            : `Tank ${Math.round(target)} percent full, about ${formatLitres(volumeLitres)} litres`
        }
      >
        {/* Water body */}
        <div
          className={`tank-fill absolute inset-x-0 bottom-0 bg-gradient-to-b ${fillColor}`}
          style={{ height: `${noData ? 0 : displayed}%` }}
        >
          {/* Shimmering surface line */}
          {!noData && displayed > 2 && (
            <div className="absolute inset-x-0 top-0 h-[3px] bg-foam-100/70" />
          )}
          {!noData && displayed > 8 && (
            <div className="water-shimmer absolute inset-0 opacity-40" />
          )}
        </div>

        {/* Measurement ticks every 25% */}
        {[25, 50, 75].map((mark) => (
          <div
            key={mark}
            className="absolute inset-x-0 border-t border-dashed border-foam-100/10"
            style={{ bottom: `${mark}%` }}
          />
        ))}

        {noData && (
          <div className="absolute inset-0 flex items-center justify-center px-2 text-center">
            <span className={`${labelSize} text-slate-400`}>No data</span>
          </div>
        )}
      </div>

      <div className="text-center">
        <p className={`font-semibold tabular-nums ${size === 'lg' ? 'text-4xl' : 'text-2xl'}`}>
          {noData ? '--' : formatPercent(percent)}
        </p>
        <p className={`${labelSize} text-slate-400 tabular-nums`}>
          {noData ? 'awaiting first reading' : `≈ ${formatLitres(volumeLitres)} L of ${formatLitres(capacityLitres)} L`}
        </p>
      </div>
    </div>
  )
}
