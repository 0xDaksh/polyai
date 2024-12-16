from celery import Celery
from contextlib import asynccontextmanager
from ai import query_gpt4, query_perplexity
from prompts import spec_agent_prompt, analysis_agent_prompt
from prisma import Prisma
import asyncio
import nest_asyncio
import json
import logging

# Initialize logging
logging.basicConfig(level=logging.DEBUG)

# Initialize Celery
celery_app = Celery("tasks", broker="redis://localhost:6379/0")

# Configure Celery
celery_app.conf.update(
    task_serializer="json",
    accept_content=["json"],
    result_serializer="json",
    timezone="UTC",
    enable_utc=True,
    worker_redirect_stdouts=False,
    # worker_log_format="[%(asctime)s: %(levelname)s/%(processName)s] %(message)s",
)


@asynccontextmanager
async def get_prisma():
    """Context manager for handling Prisma connections"""
    prisma = Prisma()
    try:
        await prisma.connect()
        yield prisma
    finally:
        await prisma.disconnect()


# Helper function to run async code in Celery
def run_async(coro):
    try:
        # Enable nested event loops
        nest_asyncio.apply()
        # Create new event loop
        loop = asyncio.new_event_loop()
        asyncio.set_event_loop(loop)
        logging.debug("Running async coroutine")
        return loop.run_until_complete(coro)
    except Exception as e:
        logging.error(f"Error running async coroutine: {e}")
    finally:
        loop.close()


@celery_app.task
def coordinate_subtasks(task_id: int):
    logging.debug(f"Coordinating subtasks for task_id: {task_id}")

    async def _process():
        async with get_prisma() as prisma:
            task = await prisma.task.find_unique(
                where={"id": task_id}, include={"subtasks": True}
            )
            logging.debug(f"Fetched task: {task}")

            for subtask in task.subtasks:
                if subtask.status == "PENDING":
                    logging.debug(f"Queuing subtask {subtask.id} for processing")
                    process_subtask.delay(subtask.id)

    return run_async(_process())


@celery_app.task
def process_subtask(subtask_id: int):
    logging.debug(f"Processing subtask {subtask_id}")

    async def _process():
        async with get_prisma() as prisma:
            subtask = await prisma.subtask.find_unique(
                where={"id": subtask_id}, include={"task": True}
            )
            logging.debug(f"Fetched subtask: {subtask}")

            if subtask.status == "PENDING":
                prompt = spec_agent_prompt(subtask.task, subtask.description)
                response = await query_perplexity(prompt)
                logging.debug(f"Received response: {response}")

                await prisma.subtask.update_many(
                    where={"id": subtask_id},
                    data={
                        "sources": json.dumps(response["citations"]),
                        "findings": response["content"],
                        "status": "COMPLETED",
                    },
                )

            pending_subtasks = await prisma.subtask.find_many(
                where={"task_id": subtask.task_id, "status": "PENDING"},
            )
            logging.debug(f"Pending subtasks: {pending_subtasks}")

            if len(pending_subtasks) == 0:
                logging.debug(
                    f"All subtasks completed for task {subtask.task_id}, analyzing task"
                )
                analyze_task.delay(subtask.task_id)

    return run_async(_process())


@celery_app.task
def analyze_task(task_id: int):
    logging.debug(f"Analyzing task {task_id}")

    async def _analyze():
        async with get_prisma() as prisma:
            task = await prisma.task.find_unique(
                where={"id": task_id}, include={"subtasks": True}
            )
            logging.debug(f"Fetched task for analysis: {task}")

            prompt = analysis_agent_prompt(task, task.subtasks)
            response = await query_gpt4(prompt)
            logging.debug(f"Analysis response: {response}")

            await prisma.task.update_many(
                where={"id": task_id},
                data={"analysis": response},
            )

    return run_async(_analyze())
