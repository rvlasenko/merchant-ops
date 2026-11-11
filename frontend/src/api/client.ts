import type { Health } from './types.ts'

// The only place that calls fetch (spec 2.1, §6.1). Paths are relative, so
// requests go through the Vite proxy (D4).

export class ApiError extends Error {}

async function get<T>(path: string, signal?: AbortSignal): Promise<T> {
  let response: Response
  try {
    response = await fetch(path, {
      method: 'GET',
      headers: { Accept: 'application/json' },
      signal,
    })
  } catch (error) {
    // An aborted request is not an error to show (§5.6).
    if (signal?.aborted) throw error
    throw new ApiError('Network error')
  }

  if (!response.ok) {
    throw new ApiError(`Request failed (${response.status})`)
  }

  return (await response.json()) as T
}

export function getHealth(signal?: AbortSignal): Promise<Health> {
  return get<Health>('/api/health', signal)
}
