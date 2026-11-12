import { useState } from 'react'
import type { SyncStatus } from './api/types.ts'
import { ApiStatus } from './components/ApiStatus.tsx'
import { OrderDetail } from './components/OrderDetail.tsx'
import { OrderTable } from './components/OrderTable.tsx'

/** Owns page state only; components fetch their own data (spec 2.1, §6.1). */
function App() {
  const [status, setStatus] = useState<SyncStatus | null>(null)
  const [page, setPage] = useState(1)
  // Kept when the filter or page changes (§5.3).
  const [selectedId, setSelectedId] = useState<number | null>(null)

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
      <main className="layout">
        <OrderTable
          status={status}
          page={page}
          onFilterChange={handleFilterChange}
          onPageChange={setPage}
          selectedId={selectedId}
          onSelect={setSelectedId}
        />
        {selectedId !== null && (
          <OrderDetail orderId={selectedId} onClose={() => setSelectedId(null)} />
        )}
      </main>
    </>
  )
}

export default App
