import { useState } from 'react'
import { api } from '../api/client'
import { DemoBanner } from '../components/Ui'

interface FormState {
  serial_number: string
  activation_code: string
  tank_name: string
  shape: 'cylinder' | 'rectangle'
  radius_cm: string
  length_cm: string
  width_cm: string
  height_cm: string
  sensor_height_cm: string
}

const INITIAL: FormState = {
  serial_number: 'AQS-S3-0002',
  activation_code: 'AQUA-2026-DEMO2',
  tank_name: 'Garden Tank',
  shape: 'rectangle',
  radius_cm: '',
  length_cm: '120',
  width_cm: '80',
  height_cm: '100',
  sensor_height_cm: '110',
}

/**
 * Device activation and registration.
 *
 * PROTOTYPE ONLY. The demo serial and activation code are pre-filled on purpose
 * so the flow can be demonstrated, and the API checks them by simple equality.
 * A production system must issue single-use, expiring codes over a verified
 * channel and must never accept a code typed into a public form.
 */
export function Activate({ onDone }: { onDone: () => void }) {
  const [step, setStep] = useState(1)
  const [form, setForm] = useState<FormState>(INITIAL)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)

  const set = (key: keyof FormState, value: string) =>
    setForm((previous) => ({ ...previous, [key]: value }))

  const claim = async () => {
    setBusy(true)
    setError(null)
    try {
      const response = await api.claimDevice({
        serial_number: form.serial_number,
        activation_code: form.activation_code,
        tank_name: form.tank_name,
        shape: form.shape,
        height_cm: Number(form.height_cm),
        sensor_height_cm: Number(form.sensor_height_cm),
        ...(form.shape === 'cylinder'
          ? { radius_cm: Number(form.radius_cm) }
          : { length_cm: Number(form.length_cm), width_cm: Number(form.width_cm) }),
      })

      setNotice(response.notice)
      setStep(4)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not activate the device.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">Register a device</h1>
        <p className="mt-1 text-sm text-slate-400">
          Claim an AquaSense device and describe the tank it monitors.
        </p>
      </header>

      <DemoBanner />

      <div className="panel p-5">
        <ol className="mb-5 flex flex-wrap gap-2 text-xs">
          {['Credentials', 'Tank geometry', 'Calibration', 'Done'].map((label, index) => {
            const number = index + 1
            return (
              <li
                key={label}
                className={`rounded-full border px-3 py-1 ${
                  step === number
                    ? 'border-aqua-400 bg-aqua-400/15 text-aqua-200'
                    : 'border-depth-400/40 text-slate-400'
                }`}
              >
                {number}. {label}
              </li>
            )
          })}
        </ol>

        {step === 1 && (
          <div className="space-y-4">
            <p className="text-sm text-slate-300">
              Enter the serial number printed on the device and the activation code supplied with it.
            </p>
            <Field
              label="Device serial number"
              value={form.serial_number}
              onChange={(value) => set('serial_number', value)}
            />
            <Field
              label="Activation code"
              value={form.activation_code}
              onChange={(value) => set('activation_code', value)}
            />
            <p className="rounded-lg border border-violet-400/35 bg-violet-500/10 px-3 py-2 text-xs text-violet-100">
              Demo credentials are pre-filled so this flow can be tried without hardware. They are
              intentionally public and carry no security value.
            </p>
            <button
              type="button"
              onClick={() => setStep(2)}
              className="rounded-lg bg-depth-500 px-4 py-2 text-sm font-semibold text-foam-100 transition hover:bg-depth-400"
            >
              Continue
            </button>
          </div>
        )}

        {step === 2 && (
          <div className="space-y-4">
            <p className="text-sm text-slate-300">Name the tank and describe its shape.</p>
            <Field
              label="Tank name"
              value={form.tank_name}
              onChange={(value) => set('tank_name', value)}
            />
            <label className="block">
              <span className="text-xs text-slate-400">Tank shape</span>
              <select
                value={form.shape}
                onChange={(event) => set('shape', event.target.value)}
                className="mt-1 w-full rounded-lg border border-depth-400/50 bg-abyss-900 px-3 py-2 text-sm text-foam-100"
              >
                <option value="cylinder">Vertical cylinder</option>
                <option value="rectangle">Rectangle (cuboid)</option>
              </select>
            </label>

            {form.shape === 'cylinder' ? (
              <Field
                label="Radius (cm)"
                value={form.radius_cm}
                onChange={(value) => set('radius_cm', value)}
                type="number"
              />
            ) : (
              <div className="grid gap-3 sm:grid-cols-2">
                <Field
                  label="Length (cm)"
                  value={form.length_cm}
                  onChange={(value) => set('length_cm', value)}
                  type="number"
                />
                <Field
                  label="Width (cm)"
                  value={form.width_cm}
                  onChange={(value) => set('width_cm', value)}
                  type="number"
                />
              </div>
            )}

            <div className="grid gap-3 sm:grid-cols-2">
              <Field
                label="Tank height (cm)"
                value={form.height_cm}
                onChange={(value) => set('height_cm', value)}
                type="number"
              />
              <Field
                label="Sensor height above bottom (cm)"
                value={form.sensor_height_cm}
                onChange={(value) => set('sensor_height_cm', value)}
                type="number"
              />
            </div>

            <div className="flex gap-2">
              <button
                type="button"
                onClick={() => setStep(1)}
                className="rounded-lg border border-depth-400/50 px-4 py-2 text-sm font-medium text-slate-200"
              >
                Back
              </button>
              <button
                type="button"
                onClick={() => setStep(3)}
                className="rounded-lg bg-depth-500 px-4 py-2 text-sm font-semibold text-foam-100 transition hover:bg-depth-400"
              >
                Continue
              </button>
            </div>
          </div>
        )}

        {step === 3 && (
          <div className="space-y-4">
            <p className="text-sm text-slate-300">Confirm the calibration values.</p>
            <div className="rounded-lg bg-abyss-900/70 p-4 text-xs text-slate-300">
              <p>
                AquaSense measures the distance from the sensor to the water surface, then subtracts
                it from the sensor height to obtain the water column.
              </p>
              <pre className="mt-3 overflow-x-auto rounded bg-abyss-950/70 p-3 text-[11px] text-aqua-300">
{`water_height = sensor_height - measured_distance
sensor_height = ${form.sensor_height_cm || '--'} cm
tank_height   = ${form.height_cm || '--'} cm`}
              </pre>
              <p className="mt-3">
                Measure sensor height from the tank bottom to the face of the sensor, not to the top
                of the tank. An incorrect value shifts every reading.
              </p>
            </div>

            {error && (
              <p className="rounded-lg border border-rose-400/40 bg-rose-400/10 px-3 py-2 text-xs text-rose-200">
                {error}
              </p>
            )}

            <div className="flex gap-2">
              <button
                type="button"
                onClick={() => setStep(2)}
                className="rounded-lg border border-depth-400/50 px-4 py-2 text-sm font-medium text-slate-200"
              >
                Back
              </button>
              <button
                type="button"
                onClick={() => void claim()}
                disabled={busy}
                className="rounded-lg bg-tide-300 px-4 py-2 text-sm font-semibold text-abyss-950 transition hover:bg-tide-300/90 disabled:opacity-50"
              >
                {busy ? 'Activating…' : 'Activate and enable monitoring'}
              </button>
            </div>
          </div>
        )}

        {step === 4 && (
          <div className="space-y-4">
            <p className="text-lg font-semibold text-tide-300">Device activated</p>
            <p className="text-sm text-slate-300">
              <strong>{form.tank_name}</strong> is now being monitored. Readings will appear as the
              device reports.
            </p>
            {notice && (
              <p className="rounded-lg border border-amber-300/35 bg-amber-300/10 px-3 py-2 text-xs text-amber-100">
                {notice}
              </p>
            )}
            <button
              type="button"
              onClick={onDone}
              className="rounded-lg bg-depth-500 px-4 py-2 text-sm font-semibold text-foam-100 transition hover:bg-depth-400"
            >
              Go to my tanks
            </button>
          </div>
        )}
      </div>

      <button
        type="button"
        onClick={onDone}
        className="text-sm text-aqua-300 transition hover:text-aqua-400"
      >
        ← Back to dashboard
      </button>
    </div>
  )
}

function Field({
  label,
  value,
  onChange,
  type = 'text',
}: {
  label: string
  value: string
  onChange: (value: string) => void
  type?: string
}) {
  return (
    <label className="block">
      <span className="text-xs text-slate-400">{label}</span>
      <input
        type={type}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className="mt-1 w-full rounded-lg border border-depth-400/50 bg-abyss-900 px-3 py-2 text-sm text-foam-100"
      />
    </label>
  )
}
