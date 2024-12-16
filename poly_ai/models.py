from typing import Dict, Any, List, Optional
from pydantic import BaseModel
from datetime import datetime


class SubtaskBase(BaseModel):
    subtask_id: str
    description: str
    agent_role: str
    priority: str
    findings: Optional[str] = None
    sources: Optional[List[str]] = []


class CoordinatorResponse(BaseModel):
    subtasks: List[SubtaskBase]
