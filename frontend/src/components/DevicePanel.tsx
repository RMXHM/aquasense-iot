import { useState } from 'react'
import { api } from '../api/client'
import { SourceBadge } from '../components/Ui'
import { batteryStyle, formatRelative, rssiLabel } from '../lib/format'
import type { Tank } from '../api/types'

/**
 * Device identity, tank geometry, thresholds and reporting cadence.
 *
 * Every field is saved through PATCH /tanks/{id}/settings. Capacity is always
 * recomputed by the server from geometry, so the UI can never disagree with
 * the volume maths.
 */
export function DevicePanel({ tank, onChanged }: { tank: Tank; onChanged: () => void }) {
  const [form, setForm] = useState({
    name: tank.name,
    shape: tank.shape,
    radius_cm: '',
    length_cm: '',
    width_cm: '',
    height_cm: String(tank.height_cm),
    sensor_height_cm: String(tank.sensor_height_cm),
    low_water_threshold_percent: String(tank.low_water_threshold_percent),
    high_water_threshold_percent: String(tank.high_water_threshold_percent),
    reporting_interval_minutes: String(tank.device?.reporting_interval_minutes ?? 5),
  })
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const set = (key: keyof typeof form, value: string) =>
    setForm((previous) => ({ ...previous, [key]: value }))

  const save = async (event: React.FormEvent) => {
    event.preventDefault()
    setSaving(true)
    setError(null)
    setMessage(null)

    const payload: Record<string, string | number | boolean> = {
      name: form.name,
      shape: form.shape,
      height_cm: Number(form.height_cm),
      sensor_height_cm: Number(form.sensor_height_cm),
      low_water_threshold_percent: Number(form.low_water_threshold_percent),
      high_water_threshold_percent: Number(form.high_water_threshold_percent),
      reporting_interval_minutes: Number(form.reporting_interval_minutes),
    }

    if (form.shape === 'cylinder') {
      payload.radius_cm = Number(form.radius_cm)
    } else {
      payload.length_cm = Number(form.length_cm)
      payload.width_cm = Number(form.width_cm)
    }

    try {
      const response = await api.updateSettings(tank.id, payload)
      onChanged()
      setMessage(
        `Saved. Capacity is now ${Math.round(response.data.capacity_litres).toLocaleString('en-GB')} L.`,
      )
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not save settings.')
    } finally {
      setSaving(false)
    }
  }

  const device = tank.device

  return (
    <div className="space-y-4">
      <div className="panel p-5">
        <h3 className="text-sm font-semibold text-foam-100">Device</h3>
        {device ? (
          <dl className="mt-3 space-y-2 text-xs">
            <Row label="Model" value={device.model} />
            <Row label="Serial" value={device.serial_number} mono />
            <Row label="Status" value={device.status} />
            <Row label="Firmware" value={device.firmware_version ?? 'unknown'} mono />
            <Row
              label="Last check-in"
              value={formatRelative(device.last_seen_at)}
              tone={tank.state.device_online ? 'text-tide-300' : 'text-rose-300'}
            />
            <Row
              label="Battery"
              value={device.battery_percent != null ? `${device.battery_percent}%` : 'not reported'}
              tone={batteryStyle(device.battery_percent)}
            />
            <Row
              label="Wi-Fi signal"
              value={
                device.wifi_rssi != null
                  ? `${device.wifi_rssi} dBm (${rssiLabel(device.wifi_rssi)})`
                  : 'not reported'
              }
            />
            <Row label="Reports every" value={`${device.reporting_interval_minutes} minutes`} />
            <div className="flex items-center justify-between gap-2 pt-1">
              <dt className="text-slate-400">Data source</dt>
              <dd>
                <SourceBadge source={device.source} />
              </dd>
            </div>
          </dl>
        ) : (
          <p className="mt-3 text-sm text-slate-400">No device is associated with this tank yet.</p>
        )}
        <p className="mt-3 rounded-lg bg-abyss-900/70 px-3 py-2 text-[11px] text-slate-400">
          This device sleeps between measurements to save battery. A configuration change below is
          applied at its next check-in, not immediately.
        </p>
      </div>

      <form onSubmit={save} className="panel space-y-4 p-5">
        <h3 className="text-sm font-semibold text-foam-100">Tank & monitoring settings</h3>

        <label className="block">
          <span className="text-xs text-slate-400">Tank name</span>
          <input
            type="text"
            value={form.name}
            onChange={(event) => set('name', event.target.value)}
            className="mt-1 w-full rounded-lg border border-depth-400/50 bg-abyss-900 px-3 py-2 text-sm text-foam-100"
          />
        </label>

        <div className="grid gap-3 sm:grid-cols-2">
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
            <label className="block">
              <span className="text-xs text-slate-400">Radius (cm)</span>
              <input
                type="number"
                step="0.1"
                min="1"
                required
                value={form.radius_cm}
                onChange={(event) => set('radius_cm', event.target.value)}
                className="mt-1 w-full rounded-lg border border-depth-400/50 bg-abyss-900 px-3 py-2 text-sm text-foam-100"
              />
            </label>
          ) : (
            <>
              <label className="block">
                <span className="text-xs text-slate-400">Length (cm)</span>
                <input
                  type="number"
                  step="0.1"
                  min="1"
                  required
                  value={form.length_cm}
                  onChange={(event) => set('length_cm', event.target.value)}
                  className="mt-1 w-full rounded-lg border border-depth-400/50 bg-abyss-900 px-3 py-2 text-sm text-foam-100"
                />
              </label>
              <label className="block">
                <span className="text-xs text-slate-400">Width (cm)</span>
                <input
                  type="number"
                  step="0.1"
                  min="1"
                  required
                  value={form.width_cm}
                  onChange={(event) => set('width_cm', event.target.value)}
                  className="mt-1 w-full rounded-lg border border-depth-400/50 bg-abyss-900 px-3 py-2 text-sm text-foam-100"
                />
              </label>
            </>
          )}

          <NumberField
            label="Tank height (cm)"
            value={form.height_cm}
            onChange={(value) => set('height_cm', value)}
          />
          <NumberField
            label="Sensor height above bottom (cm)"
            value={form.sensor_height_cm}
            onChange={(value) => set('sensor_height_cm', value)}
          />
          <NumberField
            label="Low-water warning (%)"
            value={form.low_water_threshold_percent}
            min={1}
            max={99}
            onChange={(value) => set('low_water_threshold_percent', value)}
          />
          <NumberField
            label="Overflow risk (%)"
            value={form.high_water_threshold_percent}
            min={2}
            max={100}
            onChange={(value) => set('high_water_threshold_percent', value)}
          />
          <NumberField
            label="Reporting interval (minutes)"
            value={form.reporting_interval_minutes}
            onChange={(value) => set('reporting_interval_minutes', value)}
          />
        </div>

        <p className="text-[11px] text-slate-500">
          Volume is calculated from these dimensions. AquaSense does not assume volume changes
          linearly with height, and it will not report a litre value for a tank shape it cannot model.
        </p>

        <div className="flex items-center gap-3">
          <button
            type="submit"
            disabled={saving}
            className="rounded-lg bg-depth-500 px-4 py-2 text-sm font-semibold text-foam-100 transition hover:bg-depth-400 disabled:opacity-50"
          >
            {saving ? 'Saving…' : 'Save settings'}
          </button>
          {message && <p className="text-xs text-tide-300">{message}</p>}
          {error && <p className="text-xs text-rose-300">{error}</p>}
        </div>
      </form>
    </div>
  )
}

function NumberField({
  label,
  value,
  onChange,
  min = 1,
  max = 1440,
}: {
  label: string
  value: string
  onChange: (value: string) => void
  min?: number
  max?: number
}) {
  return (
    <label className="block">
      <span className="text-xs text-slate-400">{label}</span>
      <input
        type="number"
        min={min}
        max={max}
        required
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className="mt-1 w-full rounded-lg border border-depth-400/50 bg-abyss-900 px-3 py-2 text-sm text-foam-100"
      />
    </label>
  )
}

function Row({
  label,
  value,
  mono = false,
  tone,
}: {
  label: string
  value: string
  mono?: boolean
  tone?: string
}) {
  return (
    <div className="flex items-center justify-between gap-3">
      <dt className="text-slate-400">{label}</dt>
      <dd className={`${mono ? 'font-mono text-[11px]' : ''} ${tone ?? 'text-foam-100'}`}>{value}</dd>
    </div>
  )
}
