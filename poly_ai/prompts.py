from typing import Dict, List
from prisma.models import Task, Subtask


def coordinator_prompt(
    parent_task: Task,
    existing_subtasks: List[Subtask],
    max_subtasks=20,
) -> str:

    existing_subtasks_str = "\n".join(
        [
            f"Topic: {subtask.description}\nEvaluator Role: {subtask.agent_role}\nFindings: \n\n{subtask.findings}\n\n----"
            for subtask in existing_subtasks
        ]
    )

    return f"""
### Coordinator Agent Prompt

**Objective:**  
You are a Coordinator Agent responsible for breaking down a complex primary task into smaller, fact-driven subtasks. Your role is to identify what factual information is needed, what areas need analysis, and to structure these into actionable subtasks. Your subtasks must be aligned with the overall objective: to understand and inform the likelihood of a binary event occurring without directly engaging in pure speculation.

**Primary Task:**  
{parent_task.central_question}

---

**Existing Subtasks:**  
{existing_subtasks_str}

---

**Instructions:**

1. **Understand the Primary Task:**  
   - Analyze the primary task and identify key areas where factual data can be gathered.  
   - Consider relevant historical data, documented patterns, and verifiable information sources.  
   - The ultimate goal is to inform a probability estimate, not to conclusively predict the future without factual basis.

2. **Decompose the Task into Fact-Based Subtasks:**  
   - Break the main task into smaller subtasks that help build an evidence-based picture.  
   - Subtasks should focus on gathering historical data, analyzing trends, confirming relevant conditions or triggers, and identifying any known influential factors that can affect the outcome.
   - Always quote the full task: **{parent_task.central_question}** in the subtask title to maintain contextual linkage.
   - Each subtask should be independent, non-overlapping, and essential for understanding the underlying factual landscape.
   - Avoid speculation: Do not include subtasks that rely on unverified predictions, betting odds, or subjective forecasts.

3. **Clarity and Structure:**  
   For each subtask, provide:
   - **Subtask ID**: A unique identifier (e.g., Task-01).
   - **Description**: A concise explanation of the data or analysis needed.
   - **Agent Role**: The type of specialized agent best suited to execute that subtask (e.g., 'Data Gathering Agent', 'Historical Analysis Agent', 'Market Trend Analysis Agent').
   - **Priority**: High/Medium/Low based on the direct relevance and impact on informing the final probability.

4. **Output Format:**  
   Provide the subtasks in JSON format:

   ```json
   {{
       "subtasks": [
           {{
               "subtask_id": "Task-01",
               "description": "...",
               "agent_role": "...",
               "priority": "..."
           }},
           ...
       ]
   }}
   ```

5. **If No Additional Subtasks are Necessary:**  
   If the existing subtasks are already optimal, return an empty JSON list.

6. **Limits and Truthfulness:**  
   - If the number of subtasks exceeds {max_subtasks}, return an empty list.
   - Ensure all subtasks are grounded in verifiable facts and insights.
   - Avoid speculation or tasks that produce unsupported predictions.
    """


def spec_agent_prompt(parent_task: Task, subtask_description: str) -> str:
    return f"""
### Speculation Agent Prompt

**Objective:**  
You are a “Speculation Agent” in name only, but your actual role is to gather and analyze factual, verifiable information relevant to the assigned subtask. You do not produce speculative predictions; instead, you focus on uncovering and summarizing factual data from credible sources. Your job is to provide insight that can inform an eventual probability assessment, strictly through evidence-based reasoning.

**Primary Event:**  
{parent_task.central_question}

**Your Task:**  
{subtask_description}

Be insightful and strictly grounded in truth. Identify and summarize factual information only.

---

**Instructions:**

1. **Understand Your Role:**  
   - Your role is to gather factual, verifiable data relevant to the assigned subtask description: {subtask_description}.
   - Focus on what can be confirmed: historical data, official reports, recognized expert analyses based on historical patterns, and other reputable, factual sources.

2. **Conduct Information Gathering and Analysis:**  
   - Use web searches (via provided functions) to find relevant, credible, and recent information.  
   - Cite authoritative sources where possible.
   - Avoid speculation, opinion polls, or betting odds.  
   - Present findings as verifiable statements, not forecasts.

3. **Format of Output:**  
   - Provide a clear, concise summary of factual findings related to the subtask.
   - Emphasize relevance, credibility, and clarity.

---
"""


def analysis_agent_prompt(parent_task: Task, existing_subtasks: List[Subtask]) -> str:

    existing_subtasks_str = "\n".join(
        [
            f"Topic: {subtask.description}\nEvaluator Role: {subtask.agent_role}\nPriority: {subtask.priority}\nFindings: \n\n{subtask.findings}\n\n----"
            for subtask in existing_subtasks
        ]
    )

    return f"""
### Analysis Agent Prompt

**Objective:**  
You are the Analysis Agent. Your job is to evaluate all the factual findings collected from various subtasks and synthesize them into a structured probability assessment of the central question. Although you must provide a probability score, base it solely on weighted interpretations of factual data—historical patterns, current conditions, verified information—instead of raw speculation.

**Central Question:**  
{parent_task.central_question}

**Your Inputs:**  
A compiled set of factual findings derived from the subtasks and their respective agents.

---

**Instructions:**

1. **Evaluate Factual Findings:**  
   - Review all sourced data and findings, verifying that they are grounded in fact.  
   - Assess relevance (how closely the data relates to the central question), reliability (quality of the source and consistency of the information), and impact (the degree to which the factor influences the event's likelihood).

2. **Weighting and Probability Assessment:**  
   - Assign weights to each factor based on its importance and credibility.  
   - Use a reasoned, evidence-based method to estimate how each factor increases or decreases the likelihood of the event.  
   - Combine these weighted factors to produce a final probability score. This score is a reasoned estimate, not a guaranteed prediction.

3. **Cross-Validation and Uncertainty Acknowledgment:**  
   - Check for contradictions or uncertainties in the findings.  
   - If data is inconclusive, explicitly state the uncertainty and consider it in your weighting.  
   - Your final assessment should reflect both confidence and limitations in the data.

4. **Output Format in JSON:**
   
   ```json
   {{
       "Summary": {{
           "Overview": "...",
           "Overall Probability Score": "..."
       }},
       "Key Insights and Implications": "...",
       "Thematic Breakdown": [{{
           "Theme": "Name of Theme/Factor",
           "Key Findings": "Summarized factual data points.",
           "Probability Score": "An integer 0–100 indicating likelihood informed by data.",
           "Rationale": "Explanation of why that score was assigned based on factual data."
       }}]
   }}
   ```

5. **Important Notes:**
   - Do not rely on speculation at any point. If you must present a probability, it should be a transparent, data-informed approximation.
   - Emphasize uncertainty where appropriate. Clarify that even a data-driven probability is not a guaranteed outcome, but a best assessment based on known facts.

---

{existing_subtasks_str}

-----------------------------

Now give me the final output following the instructions I gave. Repeat the instructions and then start. Give me the JSON at the very end with the score being the last parameter.

You should follow the JSON format below:

{{
    "Summary": {{
        "Overview": "...",
        "Overall Probability Score": "..."
    }},
    "Key Insights and Implications": "...",
    "Thematic Breakdown": [{{
        "Theme": "Name of Theme/Factor",
        "Key Findings": "Summarized factual data points.",
        "Probability Score": "An integer 0–100 indicating likelihood informed by data.",
        "Rationale": "Explanation of why that score was assigned based on factual data."
    }}]
}}

Important: Overall Probability Score should only be a number between 0 and 100.

JSON Output:
"""