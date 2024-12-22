import "dotenv/config";
import { PrismaClient } from "@prisma/client";
import { Redis } from "ioredis";

export const prisma = new PrismaClient();
export const redis = new Redis(
  process.env.REDIS_URL! || "redis://localhost:6379",
  {
    maxRetriesPerRequest: null,
  }
);

export const specAgentLockKey = (subtaskId: string) =>
  `spec-agent-lock:${subtaskId}`;

export const isSpecAgentLocked = async (subtaskId: string) => {
  const value = await redis.get(specAgentLockKey(subtaskId));
  return value === "1";
};

export const lockSpecAgent = async (subtaskId: string) => {
  await redis.set(specAgentLockKey(subtaskId), "1", "EX", 60);
};

export const unlockSpecAgent = async (subtaskId: string) => {
  await redis.del(specAgentLockKey(subtaskId));
};
