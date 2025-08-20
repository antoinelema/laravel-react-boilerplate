import React from "react"
import { SidebarProvider, SidebarTrigger } from "@/components/ui/sidebar"
import { AppSidebar } from "@/components/app-sidebar"
import { Toaster } from "@/components/ui/sonner"
import Footer from '@/components/footer';
import UserMenu from '../components/UserMenu';

export default function Layout({ children }: { children: React.ReactNode }) {
  return (
    <SidebarProvider>
      <AppSidebar />
      <main className="min-h-screen flex flex-col">
        <SidebarTrigger />
        <UserMenu />
        <div className="mb-10 mt-2 flex-1 flex flex-col">
          {children}
        </div>
        <Toaster />
        <Footer />
      </main>
    </SidebarProvider>
  )
}
