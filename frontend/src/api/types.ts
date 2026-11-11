// Response shapes of the backend API (spec 1.3, §6; README "Getting started").

export type Health = {
  status: 'ok' | 'error'
  database: 'ok' | 'unavailable'
  timestamp: string
}
