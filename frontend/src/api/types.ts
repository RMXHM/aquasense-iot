/**
 * Telemetry contract shared with the Laravel API.
 *
 * A device reports a DISTANCE. Percent and litres are always derived on the
 * server from tank geometry - the frontend never invents a volume.
 */

export type TankShape = 'cylinder' | 'rectangle'

export type Condition =
  | 'Normal'
  | 'Low Water'
  | 'Nearly Full'
  | 'Sensor Offline'
  | 'Sensor Error'
  | 'No Data'

export interface CurrentReading {
  percent_full: number | null
  volume_litres: number | null
  volume_cubic_meters: number | null
  water_height_cm: number | null
  distance_cm: number | null
  measured_at: string | null
  source: 'simulation' | 'hardware' | null
  sensor_status: string | null
}

export interface DeviceInfo {
  serial_number: string
  model: string
  status: string
  source: 'simulation' | 'hardware'
  firmware_version: string | null
  reporting_interval_minutes: number
  last_seen_at: string | null
  battery_percent: number | null
  wifi_rssi: number | null
}

export interface TankState {
  device_online: boolean
  readings_stale: boolean
  minutes_since_reading: number | null
  unacknowledged_alerts: number
}

export interface Tank {
  id: number
  name: string
  shape: TankShape
  height_cm: number
  sensor_height_cm: number
  capacity_litres: number
  low_water_threshold_percent: number
  high_water_threshold_percent: number
  monitoring_enabled: boolean
  demo: boolean
  current: CurrentReading | null
  device: DeviceInfo | null
  state: TankState
  condition: Condition
}

export interface HistoryPoint {
  measured_at: string
  percent_full: number | null
  volume_litres: number | null
  water_height_cm: number | null
  distance_cm: number | null
  sensor_status: string
  valid: boolean
}

export interface Alert {
  id: number
  type: string
  severity: 'info' | 'warning' | 'critical'
  title: string
  message: string
  acknowledged: boolean
  created_at: string
}

export interface TankDetail {
  data: Tank
  history: HistoryPoint[]
  alerts: Alert[]
}

export interface RawReading {
  id: number
  measured_at: string
  distance_cm: number
  water_height_cm: number | null
  percent_full: number | null
  volume_litres: number | null
  volume_cubic_meters: number | null
  source: string
  sensor_status: string
  valid: boolean
  battery_percent: number | null
  wifi_rssi: number | null
}

export type Scenarios = Record<string, string>
