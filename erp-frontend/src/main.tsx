import React from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import App from './App'
import './styles/index.css'
import { AuthProvider } from './app/auth/useAuth'
import { ToastProvider } from './app/toast/ToastProvider'

createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <ToastProvider>
      <AuthProvider>
        <BrowserRouter>
          <App />
        </BrowserRouter>
      </AuthProvider>
    </ToastProvider>
  </React.StrictMode>
)
