import React from "react"
import { Head } from '@inertiajs/react'
import { SidebarProvider, SidebarTrigger } from "@/components/ui/sidebar"
import { AppSidebar } from "@/components/app-sidebar"
import { Toaster } from "@/components/ui/sonner"
import Footer from '@/components/footer'
import UserMenu from '@/components/UserMenu'

export default function AuthenticatedLayout({ user, header, children }) {
  return (
    <SidebarProvider>
      <AppSidebar />
      <main className="min-h-screen flex flex-col w-full">
        <div className="flex items-center justify-between p-4 border-b">
          <div className="flex items-center gap-4">
            <SidebarTrigger />
            {header && <div className="font-semibold text-xl text-gray-800">{header}</div>}
          </div>
          <UserMenu user={user} />
        </div>
        <div className="flex-1 flex flex-col">
          {children}
        </div>
        <Toaster />
        <Footer />
      </main>
    </SidebarProvider>
  )
}