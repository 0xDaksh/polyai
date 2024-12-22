"use client";

import React, { useState } from "react";
import ReactMarkdown from "react-markdown";
import { Card } from "@/components/ui/card";
import toast, { Toaster } from "react-hot-toast";
import {
  Accordion,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion";
import { AgentDetail } from "./AgentDetail";
import { ThemeDetail } from "./ThemeDetail";
import { useQuery } from "@tanstack/react-query";
import { PredictionData } from "./data";
import { Loader2 } from "lucide-react";

const API_URL = process.env.NEXT_PUBLIC_API_URL;

export default function TerminalAnalysis() {
  const [expandedAgent, setExpandedAgent] = useState<string | undefined>(
    undefined
  );
  const [expandedTheme, setExpandedTheme] = useState<string | undefined>(
    undefined
  );
  const [question, setQuestion] = useState("");

  const { data: predictions, refetch } = useQuery({
    queryKey: ["prediction"],
    refetchInterval: 2000,
    queryFn: () =>
      fetch(`${API_URL}/predictions`).then((res) =>
        res.json()
      ) as Promise<PredictionData>,
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!question.trim()) return;

    try {
      const toastId = toast.loading("Creating prediction...");
      await fetch(`${API_URL}/prediction/create`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ centralQuestion: question }),
      });
      setQuestion("");
      refetch();
      toast.success("Prediction created successfully!", {
        id: toastId,
      });
    } catch (error) {
      console.error("Failed to submit question:", error);
      toast.error("Failed to create prediction. Please try again.");
    }
  };

  return (
    <div className="bg-zinc-800 p-6 min-h-screen">
      <Toaster position="top-right" />
      <div className="space-y-6 mx-auto">
        <Card className="bg-zinc-900 font-mono p-6 shadow-lg w-full">
          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="flex flex-col space-y-2">
              <label htmlFor="question" className="text-white text-lg">
                Ask a Question:
              </label>
              <input
                id="question"
                type="text"
                value={question}
                onChange={(e) => setQuestion(e.target.value)}
                className="bg-zinc-800 text-white p-3 rounded-md border border-zinc-700 focus:outline-none focus:border-green-500"
                placeholder="Enter your question here..."
              />
            </div>
            <button
              type="submit"
              className="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition-colors"
            >
              Generate Prediction
            </button>
          </form>
        </Card>

        {predictions?.map((task, index) => (
          <Card
            key={index}
            className="bg-zinc-900 font-mono p-6 shadow-lg w-full"
          >
            <div className="space-y-4">
              <div className="space-y-2">
                <p className="text-white text-2xl font-bold">
                  Task ID: {task.id}
                </p>
                <p className="text-white">
                  Task:{" "}
                  <span className="text-green-500">{task.centralQuestion}</span>
                </p>
                <p className="text-white">
                  Total AI Agents:{" "}
                  <span className="text-green-500">{task.subtasks.length}</span>
                </p>
              </div>

              <div className="space-y-2">
                <p className="text-white">Factors Evaluated by AI Agents:</p>
                <Accordion
                  type="single"
                  collapsible
                  value={expandedAgent}
                  onValueChange={setExpandedAgent}
                >
                  {task.subtasks.map((agent, agentIndex) => (
                    <AccordionItem
                      key={agentIndex}
                      value={`task-${task.id}-agent-${agentIndex + 1}`}
                      className="border-b-0"
                    >
                      <AccordionTrigger className="py-2 hover:no-underline text-white">
                        <p className="text-left">
                          {`- Agent ${agentIndex + 1}: `}
                          <span className="text-green-500">
                            {agent.description}
                          </span>
                        </p>
                      </AccordionTrigger>
                      {agent.status === "COMPLETED" ? (
                        <AgentDetail agent={agent} />
                      ) : (
                        <div className="flex items-center justify-center p-4 text-green-500">
                          <Loader2 className="h-6 w-6 animate-spin mr-2" />
                          <span>Thinking...</span>
                        </div>
                      )}
                    </AccordionItem>
                  ))}
                </Accordion>
              </div>

              {task.analysis ? (
                <section>
                  <div className="space-y-2 mt-6">
                    <p className="text-white text-lg">AI Analysis Overview:</p>
                    <p className="pl-4 text-pretty text-green-500">
                      {task.analysis?.summary_overview}
                    </p>
                  </div>

                  <div className="space-y-2 mt-6">
                    <p className="text-white text-lg">
                      Key Insights and Implications:
                    </p>
                    <p className="pl-4 text-pretty text-green-500">
                      <ReactMarkdown>
                        {task.analysis.key_insights_and_implications}
                      </ReactMarkdown>
                    </p>
                  </div>

                  <div className="space-y-2 mt-6">
                    <p className="text-white text-lg">
                      Overall Probability Score:
                    </p>
                    <p
                      className={`pl-4 font-bold ${
                        task.analysis.overall_probability_score < 50
                          ? "text-red-500"
                          : task.analysis.overall_probability_score < 60
                          ? "text-yellow-500"
                          : "text-green-500"
                      }`}
                    >
                      {task.analysis.overall_probability_score}
                    </p>
                  </div>

                  <div className="space-y-2 mt-6">
                    <p className="text-white text-lg">Thematic Breakdown:</p>
                    <Accordion
                      type="single"
                      collapsible
                      value={expandedTheme}
                      onValueChange={setExpandedTheme}
                    >
                      {task.analysis.thematic_breakdown.map(
                        (theme, themeIndex) => (
                          <AccordionItem
                            key={themeIndex}
                            value={`task-${task.id}-theme-${themeIndex + 1}`}
                            className="border-b-0"
                          >
                            <AccordionTrigger className="py-2 hover:no-underline text-white">
                              <p className="text-left">{`- ${theme.theme}`}</p>
                            </AccordionTrigger>
                            <ThemeDetail theme={theme} />
                          </AccordionItem>
                        )
                      )}
                    </Accordion>
                  </div>
                </section>
              ) : null}
            </div>
          </Card>
        ))}
      </div>
    </div>
  );
}
