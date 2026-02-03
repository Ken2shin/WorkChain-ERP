"use client"

import React from 'react'

export default function WorkChainLoginPage() {
  return (
    <main className="min-h-screen bg-gray-50 flex items-center justify-center">
      <div className="text-center p-10 bg-white shadow-xl rounded-2xl">
        <h1 className="text-2xl font-bold text-blue-600">WorkChain ERP</h1>
        <p className="text-gray-500 mt-2">Sistema detectado: Next.js App</p>
        <div className="mt-6 p-4 bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg text-sm">
          <strong>Aviso de Arquitectura:</strong><br />
          Estás intentando cargar un componente de la carpeta <code>/frontend</code> (Astro) 
          dentro de <code>/app</code> (Next.js). 
          <br /><br />
          Para ver tu Login de Astro, debes navegar a: <br />
          <code className="font-bold">http://localhost:4321/login</code>
        </div>
      </div>
    </main>
  )
}