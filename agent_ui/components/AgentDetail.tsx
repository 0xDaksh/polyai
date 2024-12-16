import { AccordionContent } from "@/components/ui/accordion"
import { PredictionData } from "./data"
import ReactMarkdown from "react-markdown";

type AgentDetailProps = PredictionData[0]['subtasks'][0]

export function AgentDetail({ agent }: { agent: AgentDetailProps }) {

  return (
    <AccordionContent className="text-red-500 space-y-2 pl-4">
      <p><strong>Task:</strong> {agent.description}</p>
      <p><strong>Role:</strong> {agent.agentRole}</p>
      <p><strong>Priority:</strong> {agent.priority}</p>
      <p><strong>Key Findings:</strong> </p>
      <ReactMarkdown

      >{agent.findings}</ReactMarkdown>
      <ul>
        <strong>Sources:</strong>
        {agent.sources.length > 0 && agent.sources.map((source, index) => (
          <li key={index}>
            <a href={source} target="_blank" rel="noopener noreferrer">
              {source}
            </a>
          </li>
        ))}
      </ul>
    </AccordionContent>
  )
}

