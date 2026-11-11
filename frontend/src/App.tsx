import { useState } from 'react'
import type { SyncStatus } from './api/types.ts'
import { ApiStatus } from './components/ApiStatus.tsx'
import { OrderTable } from './components/OrderTable.tsx'

/** Owns page state only; components fetch their own data (spec 2.1, §6.1). */
function App() {
  const [status, setStatus] = useState<SyncStatus | null>(null)
  const [page, setPage] = useState(1)

  function handleFilterChange(next: SyncStatus | null) {
    setStatus(next)
    setPage(1)
  }

  return (
    <>
      <header className="app-header">
        <h1>MerchantOps</h1>
        <ApiStatus />
      </header>
      <main>
        <OrderTable
          status={status}
          page={page}
          onFilterChange={handleFilterChange}
          onPageChange={setPage}
        />
      </main>
    </>
  )
}

export default App
