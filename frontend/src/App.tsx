import { useState } from 'react'
import { Overview } from './pages/Overview'
import { TankDetailPage } from './pages/TankDetail'
import { Activate } from './pages/Activate'
import { api } from './api/client'

type Route = { view: 'overview' } | { view: 'tank'; id: number } | { view: 'activate' }

export default function App() {
  const [route, setRoute] = useState<Route>({ view: 'overview' })

  return (
    <div className="min-h-full">
      <Header route={route} onNavigate={setRoute} />

      <main className="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6">
        {route.view === 'overview' && (
          <Overview onOpenTank={(id) => setRoute({ view: 'tank', id })} onActivate={() => setRoute({ view: 'activate' })} />
        )}
        {route.view === 'tank' && (
          <TankDetailPage tankId={route.id} onBack={() => setRoute({ view: 'overview' })} />
        )}
        {route.view === 'activate' && <Activate onDone={() => setRoute({ view: 'overview' })} />}
      </main>

      <footer className="mx-auto w-full max-w-6xl px-4 pb-10 text-xs text-slate-500 sm:px-6">
        AquaSense prototype · readings are produced by a software simulator and are labelled as
        simulated. Not a substitute for inspecting a real tank.
      </footer>
    </div>
  )
}

function Header({
  route,
  onNavigate,
}: {
  route: Route
  onNavigate: (route: Route) => void
}) {
  const [seeding, setSeeding] = useState(false)
  const [note, setNote] = useState<string | null>(null)

  /** One-click demo setup: gives the primary tank a chart worth of history. */
  const seedDemo = async () => {
    setSeeding(true)
    setNote(null)
    try {
      const overview = await api.overview()
      const first = overview.data[0]
      if (!first) {
        setNote('No tank available to seed yet.')
        return
      }
      await api.simulateBackfill(first.id, 'consumption', 24, 30)
      setNote('Demo history generated. Refreshing…')
      onNavigate({ view: 'overview' })
      window.setTimeout(() => window.location.reload(), 600)
    } catch (err) {
      setNote(err instanceof Error ? err.message : 'Could not seed demo data.')
    } finally {
      setSeeding(false)
    }
  }

  const isActive = (view: Route['view']) => route.view === view

  return (
    <header className="sticky top-0 z-20 border-b border-depth-400/25 bg-abyss-950/85 backdrop-blur">
      <div className="mx-auto flex w-full max-w-6xl flex-wrap items-center gap-4 px-4 py-3 sm:px-6">
        <button
          type="button"
          onClick={() => onNavigate({ view: 'overview' })}
          className="flex items-center gap-2.5"
        >
          <span
            aria-hidden
            className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-depth-400 to-tide-300 text-abyss-950"
          >
            <WaveIcon />
          </span>
          <span className="text-left">
            <span className="block text-base font-semibold leading-tight tracking-tight">AquaSense</span>
            <span className="block text-[11px] text-slate-400">Tank water monitoring</span>
          </span>
        </button>

        <nav className="ml-auto flex items-center gap-2">
          <NavButton active={isActive('overview')} onClick={() => onNavigate({ view: 'overview' })}>
            Overview
          </NavButton>
          <NavButton active={isActive('activate')} onClick={() => onNavigate({ view: 'activate' })}>
            Register device
          </NavButton>
          <button
            type="button"
            onClick={() => void seedDemo()}
            disabled={seeding}
            className="rounded-lg border border-violet-400/40 bg-violet-500/10 px-3 py-1.5 text-xs font-semibold text-violet-200 transition hover:bg-violet-500/20 disabled:opacity-50"
          >
            {seeding ? 'Seeding…' : 'Seed demo data'}
          </button>
        </nav>
      </div>

      {note && (
        <p className="mx-auto w-full max-w-6xl px-4 pb-2 text-xs text-violet-200 sm:px-6">{note}</p>
      )}
    </header>
  )
}

function NavButton({
  active,
  onClick,
  children,
}: {
  active: boolean
  onClick: () => void
  children: React.ReactNode
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`rounded-lg px-3 py-1.5 text-xs font-medium transition ${
        active
          ? 'bg-aqua-400/15 text-aqua-200'
          : 'text-slate-300 hover:bg-white/5 hover:text-foam-100'
      }`}
    >
      {children}
    </button>
  )
}

function WaveIcon() {
  return (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden>
      <path
        d="M2 15c2.5 0 2.5-2 5-2s2.5 2 5 2 2.5-2 5-2 2.5 2 5 2"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
      />
      <path
        d="M2 9c2.5 0 2.5-2 5-2s2.5 2 5 2 2.5-2 5-2 2.5 2 5 2"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        opacity="0.6"
      />
    </svg>
  )
}
