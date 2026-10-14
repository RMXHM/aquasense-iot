import type { Condition } from '../api/types'

export function formatLitres(value: number | null | undefined): string {
  if (value === null || value === undefined) return '--'
  return new Intl.NumberFormat('en-GB', { maximumFractionDigits: 0 }).format(value)
}

export function formatPercent(value: number | null | undefined): string {
  if (value === null || value === undefined) return '--'
  return `${Math.round(value)}%`
}

export function formatCubicMeters(value: number | null | undefined): string {
  if (value === null || value === undefined) return '--'
  return `${value.toFixed(2)} m³`
}

/** "3 min ago", "just now" - relative time for last-measurement labels. */
export function formatRelative(iso: string | null | undefined): string {
  if (!iso) return 'never'
  const then = new Date(iso).getTime()
  if (Number.isNaN(then)) return 'unknown'

  const seconds = Math.max(0, Math.round((Date.now() - then) / 1000))
  if (seconds < 45) return 'just now'

  const minutes = Math.round(seconds / 60)
  if (minutes < 60) return `${minutes} min ago`

  const hours = Math.round(minutes / 60)
  if (hours < 24) return `${hours} h ago`

  const days = Math.round(hours / 24)
  return `${days} d ago`
}

export function formatClock(iso: string): string {
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return '--'
  return date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
}

export function formatDate(iso: string): string {
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return '--'
  return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })
}

export interface ConditionStyle {
  label: Condition
  text: string
  dot: string
  badge: string
}

/**
 * Condition styling. Sensor Offline and Sensor Error are visually distinct from
 * a genuine low-water state so a stale value is never mistaken for a live one.
 */
export function conditionStyle(condition: Condition): ConditionStyle {
  switch (condition) {
    case 'Normal':
      return {
        label: condition,
        text: 'text-tide-300',
        dot: 'bg-tide-300',
        badge: 'bg-tide-300/12 text-tide-300 border-tide-300/35',
      }
    case 'Low Water':
      return {
        label: condition,
        text: 'text-amber-300',
        dot: 'bg-amber-300',
        badge: 'bg-amber-300/12 text-amber-200 border-amber-300/35',
      }
    case 'Nearly Full':
      return {
        label: condition,
        text: 'text-aqua-300',
        dot: 'bg-aqua-300',
        badge: 'bg-aqua-300/12 text-aqua-200 border-aqua-300/35',
      }
    case 'Sensor Offline':
    case 'Sensor Error':
      return {
        label: condition,
        text: 'text-rose-300',
        dot: 'bg-rose-400',
        badge: 'bg-rose-400/12 text-rose-200 border-rose-400/40',
      }
    default:
      return {
        label: condition,
        text: 'text-slate-300',
        dot: 'bg-slate-400',
        badge: 'bg-slate-400/12 text-slate-200 border-slate-400/35',
      }
  }
}

/** Wi-Fi signal strength band from RSSI in dBm. */
export function rssiLabel(rssi: number | null | undefined): string {
  if (rssi === null || rssi === undefined) return 'unknown'
  if (rssi >= -60) return 'excellent'
  if (rssi >= -70) return 'good'
  if (rssi >= -80) return 'weak'
  return 'very weak'
}

export function batteryStyle(percent: number | null | undefined): string {
  if (percent === null || percent === undefined) return 'text-slate-400'
  if (percent <= 20) return 'text-rose-300'
  if (percent <= 40) return 'text-amber-300'
  return 'text-tide-300'
}
