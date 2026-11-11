import { ApiStatus } from './components/ApiStatus.tsx'

function App() {
  return (
    <>
      <header className="app-header">
        <h1>MerchantOps</h1>
        <ApiStatus />
      </header>
      <main />
    </>
  )
}

export default App
