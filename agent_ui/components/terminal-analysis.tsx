'use client'

import { useState } from 'react'
import ReactMarkdown from "react-markdown";
import { Card } from "@/components/ui/card"
import {
  Accordion,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion"
import { AgentDetail } from "./AgentDetail"
import { ThemeDetail } from "./ThemeDetail"
import { useQuery } from '@tanstack/react-query'
import { PredictionData } from './data'

export default function TerminalAnalysis() {
  const [expandedAgent, setExpandedAgent] = useState<string | null>(null)
  const [expandedTheme, setExpandedTheme] = useState<string | null>(null)

  const { data: predictions } = useQuery({
    queryKey: ['prediction'],
    queryFn: () => fetch('http://localhost:3000/predictions').then(res => res.json()) as Promise<PredictionData>
  });



  return (
    <div className="bg-zinc-800 p-6 min-h-screen">
      <div className="space-y-6 mx-auto">
        {predictions?.map((task, index) => (
          <Card key={index} className="bg-zinc-900 font-mono p-6 shadow-lg w-full">
            <div className="space-y-4">
              <div className="space-y-2">
                <p className="text-white text-2xl font-bold">Task ID: {task.id}</p>
                <p className="text-white">Task: <span className="text-green-500">{task.centralQuestion}</span></p>
                <p className="text-white">Total AI Agents: <span className="text-green-500">{task.subtasks.length}</span></p>
              </div>

              <div className="space-y-2">
                <p className="text-white">Factors Evaluated by AI Agents:</p>
                <Accordion type="single" collapsible value={expandedAgent} onValueChange={setExpandedAgent}>
                  {task.subtasks.map((agent, agentIndex) => (
                    <AccordionItem key={agentIndex} value={`task-${task.id}-agent-${agentIndex + 1}`} className="border-b-0">
                      <AccordionTrigger className="py-2 hover:no-underline text-white">
                        <p className="text-left">{`- Agent ${agentIndex + 1}: `}<span className="text-green-500">{agent.description}</span></p>
                      </AccordionTrigger>
                      <AgentDetail agent={agent} />
                    </AccordionItem>
                  ))}
                </Accordion>
              </div>

              <div className="space-y-2 mt-6">
                <p className="text-white text-lg">AI Analysis Overview:</p>
                <p className="pl-4 text-pretty text-green-500">
                  {task.analysis?.summary_overview}
                </p>
              </div>

              <div className="space-y-2 mt-6">
                <p className="text-white text-lg">Key Insights and Implications:</p>
                <p className="pl-4 text-pretty text-green-500">
                  <ReactMarkdown >{task.analysis.key_insights_and_implications}</ReactMarkdown>
                </p>
              </div>

              <div className="space-y-2 mt-6">
                <p className="text-white text-lg">Overall Probability Score:</p>
                <p className={`pl-4 font-bold ${task.analysis.overall_probability_score < 50 ? 'text-red-500' :
                  task.analysis.overall_probability_score < 60 ? 'text-yellow-500' :
                    'text-green-500'
                  }`}>{task.analysis.overall_probability_score}</p>
              </div>

              <div className="space-y-2 mt-6">
                <p className="text-white text-lg">Thematic Breakdown:</p>
                <Accordion type="single" collapsible value={expandedTheme} onValueChange={setExpandedTheme}>
                  {task.analysis.thematic_breakdown.map((theme, themeIndex) => (
                    <AccordionItem key={themeIndex} value={`task-${task.id}-theme-${themeIndex + 1}`} className="border-b-0">
                      <AccordionTrigger className="py-2 hover:no-underline text-white">
                        <p className="text-left">{`- ${theme.theme}`}</p>
                      </AccordionTrigger>
                      <ThemeDetail theme={theme} />
                    </AccordionItem>
                  ))}
                </Accordion>
              </div>
            </div>
          </Card>
        ))}
      </div>
    </div>
  )
}
