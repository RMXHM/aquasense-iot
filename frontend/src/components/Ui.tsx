import type { ReactNode } from 'react'
import { conditionStyle } from '../lib/format'
import type { Condition } from '../api/types'

export function Badge({ children, className = '' }: { children: ReactNode; className?: string }) {
  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium ${className}`}
    >
      {children}
    </span>
  )
}

export function ConditionBadge({ condition }: { condition: Condition }) {
  const style = conditionStyle(condition)

  return (
    <Badge className={style.badge}>
      <span className={`h-1.5 w-1.5 rounded-full ${style.dot}`} />
      {style.label}
    </Badge>
  )
}

export function SourceBadge({ source }: { source: 'simulation' | 'hardware' }) {
  return source === 'simulation' ? (
    <Badge className="border-violet-400/40 bg-violet-400/12 text-violet-200">Simulated data</Badge>
  ) : (
    <Badge className="border-tide-300/40 bg-tide-300/12 text-tide-300">Live hardware</Badge>
  )
}

export function DemoBanner() {
  return (
    <div className="flex items-start gap-3 rounded-xl border border-violet-400/35 bg-violet-500/10 px-4 py-3">
      <span aria-hidden className="mt-0.5 text-violet-200">
        ◆
      </span>
      <p className="text-sm text-violet-100">
        <strong className="font-semibold">Demo mode.</strong> Every tank shown here is simulated
        in software. These readings do not come from a physical tank and are not suitable as
        evidence of real water use.
      </p>
    </div>
  )
}

export function Spinner({ label = 'Loading' }: { label?: string }) {
  return (
    <div className="flex items-center justify-center gap-3 py-16 text-slate-400" role="status">
      <span className="h-4 w-4 animate-spin rounded-full border-2 border-depth-400 border-t-transparent" />
      <span className="text-sm">{label}</span>
    </div>
  )
}

export function EmptyState({
  title,
  description,
  action,
}: {
  title: string
  description: string
  action?: ReactNode
}) {
  return (
    <div className="panel flex flex-col items-center gap-3 px-6 py-14 text-center">
      <span aria-hidden className="text-3xl text-depth-400">
        ◌
      </span>
      <h3 className="text-lg font-semibold">{title}</h3>
      <p className="max-w-md text-sm text-slate-400">{description}</p>
      {action}
    </div>
  )
}

export function ErrorState({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <div className="panel border-rose-400/40 bg-rose-500/10 px-6 py-10 text-center">
      <h3 className="text-lg font-semibold text-rose-200">Could not load AquaSense data</h3>
      <p className="mx-auto mt-2 max-w-md text-sm text-rose-100/90">{message}</p>
      {onRetry && (
        <button
          type="button"
          onClick={onRetry}
          className="mt-5 rounded-lg border border-rose-300/40 px-4 py-2 text-sm font-medium text-rose-100 transition hover:bg-rose-400/15"
        >
          Try again
        </button>
      )}
    </div>
  )
}

export function ConnectionNotice({ lastUpdated }: { lastUpdated: Date | null }) {
  return (
    <p className="text-xs text-slate-500">
      {lastUpdated
        ? `Synced ${lastUpdated.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`
        : 'Connecting…'}
    </p>
  )
}
