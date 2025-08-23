import React from "react"
import { Head } from '@inertiajs/react'
import { Toaster } from "@/components/ui/sonner"

export default function AuthenticatedLayout({ user, header, children }) {
  return (
    <div className="min-h-screen bg-gray-50/50">
      <header className="bg-white border-b border-gray-200 px-4 py-3">
        <div className="flex items-center justify-between max-w-7xl mx-auto">
          <div className="flex items-center gap-4">
            {header && <div className="font-semibold text-xl text-gray-800">{header}</div>}
          </div>
          <div className="flex items-center space-x-4">
            {user && (
              <div className="text-sm text-gray-600">
                Connecté en tant que {user.firstname} {user.name}
              </div>
            )}
          </div>
        </div>
      </header>
      
      <main className="flex-1">
        {children}
      </main>
      
      <Toaster />
    </div>
  )
}