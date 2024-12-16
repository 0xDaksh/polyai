import { createOpenAI } from "@ai-sdk/openai";

const openai = createOpenAI({
  apiKey: process.env.OPENAI_API_KEY,
});

const pplx = createOpenAI({
  name: "pplx",
  apiKey: process.env.PPLX_API_KEY ?? "",
  baseURL: "https://api.perplexity.ai/",
  compatibility: "compatible",
});

export const pplxMakeshift = async (model: string, prompt: string) => {
  const options = {
    method: "POST",
    headers: {
      Authorization: `Bearer ${process.env.PPLX_API_KEY}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      model,
      messages: [{ role: "user", content: prompt }],
    }),
  };

  const res = await fetch(
    "https://api.perplexity.ai/chat/completions",
    options
  );
  const body = await res.json();
  return { text: body.choices[0].message.content, citations: body.citations };
};

export { openai, pplx };
