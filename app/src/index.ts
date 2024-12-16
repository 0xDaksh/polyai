import { Elysia, t } from "elysia";
import { swagger } from "@elysiajs/swagger";
import { openai } from "./models";
import { generateObject } from "ai";
import { coordinatorPrompt } from "./prompt";
import { z } from "zod";
import { prisma } from "./prisma";
import { specQueue } from "./mq";
import { cors } from "@elysiajs/cors";

const app = new Elysia().use(swagger()).use(cors());

app.get("/", () => "Hello Elysia");

app.post(
  "/prediction/create",
  async ({ body }) => {
    const res = await generateObject({
      model: openai("gpt-4o", {
        structuredOutputs: true,
      }),
      output: "array",
      prompt: coordinatorPrompt(body, []),
      schema: z.object({
        subtask_id: z.string(),
        description: z.string(),
        agent_role: z.string(),
        priority: z.string(),
      }),
    });

    const prediction = await prisma.prediction.create({
      data: {
        centralQuestion: body.centralQuestion,
        subtasks: {
          create: res.object.map((subtask) => ({
            description: subtask.description,
            agentRole: subtask.agent_role,
            priority: subtask.priority,
            status: "PENDING",
          })),
        },
      },
      include: {
        subtasks: true,
      },
    });

    specQueue.addBulk(
      prediction.subtasks.map((value) => ({
        name: "spec-agent",
        data: { subtaskId: value.id },
      }))
    );

    return prediction;
  },
  {
    body: t.Object({
      centralQuestion: t.String(),
    }),
  }
);

app.get("/prediction/:id", async ({ params }) => {
  const prediction = await prisma.prediction.findUnique({
    where: { id: parseInt(params.id) },
    include: { subtasks: true },
  });
  return prediction;
});

app.get("/predictions", async () => {
  const predictions = await prisma.prediction.findMany({
    include: { subtasks: true },
  });

  return predictions;
});

app.listen(3000);
console.log(
  `🦊 Elysia is running at ${app.server?.hostname}:${app.server?.port}`
);
