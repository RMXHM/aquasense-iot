import type {
  Alert,
  HistoryPoint,
  RawReading,
  Scenarios,
  Tank,
  TankDetail,
} from './types'

/**
 * Thin typed wrapper around the AquaSense API.
 *
 * Requests go to the same origin (see the Vite dev proxy), so no credentials
 * or API host live in the browser bundle.
 */

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(`/api/v1${path}`, {
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    ...init,
  })

  if (!response.ok) {
    let message = `Request failed (HTTP ${response.status})`
    try {
      const body = (await response.json()) as { message?: string; errors?: Record<string, string[]> }
      if (body.message) message = body.message
      const firstError = body.errors && Object.values(body.errors)[0]?.[0]
      if (firstError) message = firstError
    } catch {
      // keep the generic message
    }
    throw new Error(message)
  }

  return (await response.json()) as T
}

export interface OverviewResponse {
  data: Tank[]
  meta: { generated_at: string }
}

export const api = {
  overview: () => request<OverviewResponse>('/overview'),

  tank: (id: number) => request<TankDetail>(`/tanks/${id}`),

  readings: (id: number, limit = 100) =>
    request<{ data: RawReading[] }>(`/tanks/${id}/readings?limit=${limit}`),

  alerts: (id: number) => request<{ data: Alert[] }>(`/tanks/${id}/alerts`),

  scenarios: () => request<{ data: Scenarios }>('/scenarios'),

  acknowledgeAlert: (alertId: number) =>
    request<{ acknowledged: boolean }>(`/alerts/${alertId}/acknowledge`, { method: 'POST' }),

  updateSettings: (id: number, patch: Partial<Tank> & { reporting_interval_minutes?: number }) =>
    request<{ data: Tank }>(`/tanks/${id}/settings`, {
      method: 'PATCH',
      body: JSON.stringify(patch),
    }),

  simulateStep: (id: number, scenario: string) =>
    request<{
      step: number
      accepted: boolean
      reason: string | null
      reading: { percent_full: number | null; volume_litres: number | null } | null
      alerts: Alert[]
    }>(`/tanks/${id}/simulate/step`, {
      method: 'POST',
      body: JSON.stringify({ scenario }),
    }),

  simulateBackfill: (id: number, scenario: string, steps = 24, intervalMinutes = 30) =>
    request<{ accepted: number }>(`/tanks/${id}/simulate/backfill`, {
      method: 'POST',
      body: JSON.stringify({ scenario, steps, interval_minutes: intervalMinutes }),
    }),

  simulateReset: (id: number) =>
    request<{ reset: boolean }>(`/tanks/${id}/simulate/reset`, { method: 'POST' }),

  claimDevice: (payload: {
    serial_number: string
    activation_code: string
    tank_name: string
    shape: Tank['shape']
    radius_cm?: number
    length_cm?: number
    width_cm?: number
    height_cm: number
    sensor_height_cm: number
  }) =>
    request<{ data: Tank; notice: string }>('/devices/claim', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
}

export type { Alert, HistoryPoint, RawReading, Tank, TankDetail }
