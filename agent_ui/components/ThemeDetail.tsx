import { AccordionContent } from "@/components/ui/accordion"
import { PredictionData } from "./data"

type ThemeData = PredictionData[0]['analysis']['thematic_breakdown'][0];


export function ThemeDetail({ theme }: { theme: ThemeData }) {
  return (
    <AccordionContent className="text-red-500 space-y-2 pl-4">
      <p><strong>Theme:</strong> {theme.theme}</p>
      <p><strong>Key Findings:</strong> {theme.key_findings}</p>
      <p><strong>Probability Score:</strong> <span className="text-red-500">{theme.probability_score}</span></p>
      <p><strong>Rationale:</strong> {theme.rationale}</p>
    </AccordionContent>
  )
}

