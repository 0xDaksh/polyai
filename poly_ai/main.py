from typing import Dict, Any, List, Optional
from fastapi import FastAPI, HTTPException
from ai import query_gpt4, query_perplexity
import json
from models import CoordinatorResponse
from prisma import Prisma
from contextlib import asynccontextmanager
from prompts import coordinator_prompt, spec_agent_prompt, analysis_agent_prompt
from worker import coordinate_subtasks

prisma = Prisma()


@asynccontextmanager
async def lifespan(app: FastAPI):
    await prisma.connect()
    yield
    await prisma.disconnect()


app = FastAPI(lifespan=lifespan)


@app.post("/coordinate")
async def coordinate_task(central_question: str) -> Dict[str, Any]:
    """
    Coordinates task breakdown by calling GPT-4 with the coordinator prompt.
    """
    print(f"Processing task: {central_question}")

    # Create the main task in database
    parent_task = await prisma.task.create(data={"central_question": central_question})

    prompt = coordinator_prompt(parent_task, [], max_subtasks=10)
    response = await query_gpt4(prompt, force_json=True)

    parsed = CoordinatorResponse.model_validate(json.loads(response))
    print(f"Generated {len(parsed.subtasks)} subtasks")

    subtasks = [
        {
            "description": subtask.description,
            "agent_role": subtask.agent_role,
            "priority": subtask.priority,
            "task_id": parent_task.id,
        }
        for subtask in parsed.subtasks
    ]

    await prisma.subtask.create_many(
        data=subtasks,
    )

    coordinate_subtasks.delay(parent_task.id)

    return {
        "id": parent_task.id,
        "central_question": central_question,
        "subtasks": subtasks,
    }


@app.get("/task/{task_id}")
async def get_task(task_id: int) -> Dict[str, Any]:
    """
    Retrieves a task and its subtasks by task ID.
    """
    task = await prisma.task.find_unique(
        where={"id": task_id}, include={"subtasks": True}
    )

    if not task:
        raise HTTPException(status_code=404, detail="Task not found")

    return {
        "id": task.id,
        "central_question": task.central_question,
        "analysis": task.analysis,
        "subtasks": task.subtasks,
    }


@app.post("/task/{task_id}/coordinate")
async def trigger_coordinate(task_id: int) -> Dict[str, Any]:
    """
    Triggers coordination of subtasks for a given task ID.
    """
    task = await prisma.task.find_unique(where={"id": task_id})

    if not task:
        raise HTTPException(status_code=404, detail="Task not found")

    coordinate_subtasks.delay(task_id)

    return {"message": f"Coordination triggered for task {task_id}"}
