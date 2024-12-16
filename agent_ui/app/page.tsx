"use client"
import TerminalAnalysis from "@/components/terminal-analysis";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";

const queryClient = new QueryClient();

export default function Home() {
  return (
    <QueryClientProvider client={queryClient}>
      <TerminalAnalysis />
    </QueryClientProvider>
  );
}
