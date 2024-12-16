export type PredictionData = {
  id: number;
  centralQuestion: string;
  analysis: {
    summary_overview: string;
    thematic_breakdown: {
      theme: string;
      rationale: string;
      key_findings: string;
      probability_score: number;
    }[];
    overall_probability_score: number;
    key_insights_and_implications: string;
  };
  createdAt: string;
  updatedAt: string;
  subtasks: {
    id: string;
    predictionId: number;
    description: string;
    agentRole: string;
    priority: "High" | "Medium" | "Low";
    findings: string;
    sources: string[];
    status: "COMPLETED" | "PENDING" | "FAILED";
    createdAt: string;
    updatedAt: string;
  }[];
}[];
