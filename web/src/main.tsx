import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import { App } from './app/App'
import { applyAppearance, loadAppearance } from '@/shared/lib/appearance'

// خطوط المستخدم المختارة قبل أوّل رسم — فلا وميضَ للخطّ الافتراضي ثمّ قفزة.
applyAppearance(loadAppearance())

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
