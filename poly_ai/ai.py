from openai import AsyncOpenAI, OpenAI
from typing import Dict, Any
from dotenv import load_dotenv
import os

load_dotenv()


# Initialize API clients
openai_client = AsyncOpenAI(
    api_key=os.getenv("OAI_API_KEY"),
)
openai_client_sync = OpenAI(
    api_key=os.getenv("OAI_API_KEY"),
)

pplx_client = AsyncOpenAI(
    api_key=os.getenv("PPLX_API_KEY"),
    base_url="https://api.perplexity.ai",
)
pplx_client_sync = OpenAI(
    api_key=os.getenv("PPLX_API_KEY"),
    base_url="https://api.perplexity.ai",
)

oai_model = "gpt-4o"
pplx_model = "llama-3.1-sonar-large-128k-online"


async def query_gpt4(prompt: str, force_json: bool = False) -> str:
    """
    Query GPT-4 through OpenAI API and return the response.

    Args:
        prompt (str): The input prompt to send to GPT-4

    Returns:
        Dict[str, Any]: The API response containing the model's output
    """
    try:
        response = await openai_client.chat.completions.create(
            model=oai_model,
            messages=[{"role": "user", "content": prompt}],
            response_format={"type": "json_object"} if force_json else None,
        )
        return response.choices[0].message.content
    except Exception as e:
        return {"error": str(e)}


async def query_perplexity(prompt: str) -> Dict[str, Any]:
    """
    Query Perplexity AI through their API and return the response.

    Args:
        prompt (str): The input prompt to send to Perplexity

    Returns:
        Dict[str, Any]: The API response containing the model's output
    """
    try:
        response = await pplx_client.chat.completions.create(
            model=pplx_model,
            messages=[{"role": "user", "content": prompt}],
        )
        return {
            "content": response.choices[0].message.content,
            "citations": response.citations,
        }
    except Exception as e:
        return {"error": str(e)}
