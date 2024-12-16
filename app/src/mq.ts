import { Queue, Worker } from "bullmq";
import { analysisPrompt, specPrompt } from "./prompt";
import { openai, pplxMakeshift } from "./models";
import { generateObject } from "ai";
import {
  isSpecAgentLocked,
  lockSpecAgent,
  prisma,
  redis,
  unlockSpecAgent,
} from "./prisma";
import { z } from "zod";

// Create queues
export const specQueue = new Queue("spec-agent", {
  connection: redis,
});
export const analysisQueue = new Queue("anal-agent", {
  connection: redis,
});

// Create workers
const speculationWorker = new Worker(
  "spec-agent",
  async (job) => {
    console.log("speculationWorker", job.data);
    try {
      const { subtaskId } = job.data;

      const subtask = await prisma.subtask.findUnique({
        where: { id: subtaskId },
        include: { prediction: true },
      });

      if (!subtask) {
        throw new Error("Subtask not found");
      }

      if (subtask.status == "COMPLETED") {
        return { success: true };
      }

      const isLocked = await isSpecAgentLocked(subtaskId);

      if (isLocked) {
        await specQueue.add(
          "spec-agent",
          { subtaskId },
          {
            delay: 60 * 1000,
          }
        );

        return { success: true };
      }

      await lockSpecAgent(subtaskId);

      const prompt = specPrompt(subtask.prediction, subtask);
      const { text, citations } = await pplxMakeshift(
        "llama-3.1-sonar-large-128k-online",
        prompt
      );

      // Mark task as complete
      await prisma.subtask.update({
        where: { id: subtaskId },
        data: {
          status: "COMPLETED",
          findings: text,
          sources: citations,
        },
      });

      await unlockSpecAgent(subtaskId);
      await analysisQueue.add("anal-agent", {
        subtaskId,
        parentId: subtask.prediction.id,
      });

      return { success: true };
    } catch (error) {
      console.error("Task processing failed:", error);
      throw error;
    }
  },
  {
    connection: redis,
    concurrency: 10,
  }
);

export const analysisWorker = new Worker(
  "anal-agent",
  async (job) => {
    console.log("analysisWorker", job.data);
    const { subtaskId, parentId } = job.data;

    const prediction = await prisma.prediction.findUnique({
      where: { id: parentId },
      include: { subtasks: true },
    });

    if (!prediction) {
      throw new Error("Prediction not found");
    }

    if (!prediction.subtasks.every((value) => value.status == "COMPLETED")) {
      console.log("Subtasks not completed");
      return { success: true };
    }

    const prompt = analysisPrompt(prediction, prediction.subtasks);
    const data = await generateObject({
      model: openai("gpt-4o", {
        structuredOutputs: true,
      }),
      schema: z.object({
        summary_overview: z.string(),
        overall_probability_score: z.number(),
        key_insights_and_implications: z.string(),
        thematic_breakdown: z.array(
          z.object({
            theme: z.string(),
            key_findings: z.string(),
            probability_score: z.number(),
            rationale: z.string(),
          })
        ),
      }),
      prompt,
    });

    await prisma.prediction.update({
      where: { id: parentId },
      data: {
        analysis: data.object,
      },
    });

    console.log("Analysis complete for subtask", subtaskId);

    return { success: true };
  },
  {
    connection: redis,
    concurrency: 10,
  }
);

// Error handling
speculationWorker.on("failed", (job, err) => {
  console.error(`Job ${job?.id} failed with error: ${err.message}`);
});

speculationWorker.on("completed", (job) => {
  console.log(`Job ${job.id} completed successfully`);
});

analysisWorker.on("failed", (job, err) => {
  console.error(`Job ${job?.id} failed with error: ${err.message}`);
});

analysisWorker.on("completed", (job) => {
  console.log(`Job ${job.id} completed successfully`);
});
