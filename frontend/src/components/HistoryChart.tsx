import { formatClock, formatLitres, formatPercent } from '../lib/format'
import type { HistoryPoint } from '../api/types'

interface HistoryChartProps {
  history: HistoryPoint[]
  lowWaterThreshold?: number
}

/**
 * Water-level history chart.
 *
 * Rendered as inline SVG so the dashboard carries no chart dependency, and so
 * the plotted points are exactly the stored measurements - no smoothing that
 * could imply readings the device never sent.
 */
export function HistoryChart({ history, lowWaterThreshold = 20 }: HistoryChartProps) {
  const valid = history.filter((point) => point.valid && point.percent_full !== null)

  if (valid.length < 2) {
    return (
      <div className="panel flex h-56 items-center justify-center px-6 text-center">
        <p className="text-sm text-slate-400">
          Not enough measurements yet. Run a simulator scenario or seed demo history to build a chart.
        </p>
      </div>
    )
  }

  const width = 720
  const height = 220
  const padding = { top: 18, right: 16, bottom: 30, left: 44 }
  const plotWidth = width - padding.left - padding.right
  const plotHeight = height - padding.top - padding.bottom

  const x = (index: number) => padding.left + (index / (valid.length - 1)) * plotWidth
  const y = (percent: number) => padding.top + plotHeight - (percent / 100) * plotHeight

  const linePath = valid
    .map((point, index) => `${index === 0 ? 'M' : 'L'} ${x(index).toFixed(1)} ${y(point.percent_full as number).toFixed(1)}`)
    .join(' ')

  const areaPath = `${linePath} L ${x(valid.length - 1).toFixed(1)} ${padding.top + plotHeight} L ${padding.left} ${padding.top + plotHeight} Z`

  const first = valid[0]
  const last = valid[valid.length - 1]

  return (
    <div className="panel overflow-hidden p-4">
      <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
        <h3 className="text-sm font-semibold text-foam-100">Water level history</h3>
        <p className="text-xs text-slate-400">
          {formatClock(first.measured_at)} → {formatClock(last.measured_at)} ·{' '}
          {formatPercent(first.percent_full)} → {formatPercent(last.percent_full)} ·{' '}
          {formatLitres(last.volume_litres)} L now
        </p>
      </div>

      <svg
        viewBox={`0 0 ${width} ${height}`}
        className="h-56 w-full"
        role="img"
        aria-label={`Water level over the last ${valid.length} measurements, from ${Math.round(first.percent_full as number)} to ${Math.round(last.percent_full as number)} percent`}
      >
        <defs>
          <linearGradient id="aquaFill" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="#35c3e8" stopOpacity="0.45" />
            <stop offset="100%" stopColor="#1b6fa8" stopOpacity="0.04" />
          </linearGradient>
        </defs>

        {[0, 25, 50, 75, 100].map((mark) => (
          <g key={mark}>
            <line
              x1={padding.left}
              x2={width - padding.right}
              y1={y(mark)}
              y2={y(mark)}
              stroke="#ffffff"
              strokeOpacity="0.08"
              strokeDasharray={mark === 0 ? undefined : '3 4'}
            />
            <text
              x={padding.left - 8}
              y={y(mark) + 4}
              textAnchor="end"
              className="fill-slate-500 text-[10px]"
            >
              {mark}%
            </text>
          </g>
        ))}

        {/* Low-water threshold reference line */}
        <line
          x1={padding.left}
          x2={width - padding.right}
          y1={y(lowWaterThreshold)}
          y2={y(lowWaterThreshold)}
          stroke="#fbbf24"
          strokeOpacity="0.55"
          strokeDasharray="5 5"
        />
        <text
          x={width - padding.right}
          y={y(lowWaterThreshold) - 6}
          textAnchor="end"
          className="fill-amber-300/80 text-[10px]"
        >
          low-water threshold {lowWaterThreshold}%
        </text>

        <path d={areaPath} fill="url(#aquaFill)" />
        <path
          d={linePath}
          fill="none"
          stroke="#58d4f0"
          strokeWidth="2.5"
          strokeLinecap="round"
          strokeLinejoin="round"
        />

        {valid.map((point, index) => (
          <circle
            key={`${point.measured_at}-${index}`}
            cx={x(index)}
            cy={y(point.percent_full as number)}
            r={index === valid.length - 1 ? 4 : 2.2}
            fill={index === valid.length - 1 ? '#6ee7d7' : '#35c3e8'}
          />
        ))}
      </svg>
    </div>
  )
}
