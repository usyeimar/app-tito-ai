import os
from typing import Dict, List, Optional

from app.domains.assistant.models.assistant import Assistant
from app.domains.assistant.repositories.assistant_repository import AssistantRepository


class AssistantService:
    def __init__(self, repository: AssistantRepository):
        self.repository = repository

    def create_assistant(self, assistant: Assistant) -> Assistant:
        return self.repository.save(assistant)

    def get_assistant(self, assistant_id: str) -> Optional[Assistant]:
        return self.repository.get(assistant_id)

    def list_assistants(self) -> List[Assistant]:
        return self.repository.list_all()

    def update_assistant(self, assistant_id: str, updates: Dict) -> Optional[Assistant]:
        assistant = self.repository.get(assistant_id)
        if not assistant:
            return None

        updated_data = assistant.model_dump()
        updated_data.update(updates)
        new_assistant = Assistant(**updated_data)
        new_assistant.id = assistant_id
        return self.repository.save(new_assistant)

    def delete_assistant(self, assistant_id: str) -> bool:
        return self.repository.delete(assistant_id)

    async def chat_with_assistant(self, assistant_id: str, message: str) -> str:
        """Text-only chat with an assistant's LLM (for testing/preview)."""
        assistant = self.repository.get(assistant_id)
        if not assistant:
            raise ValueError("Assistant not found")

        provider = assistant.agent.provider
        model = assistant.agent.model
        temperature = assistant.agent.temperature
        system_prompt = assistant.agent.system_prompt

        if provider == "openai":
            from openai import AsyncOpenAI

            client = AsyncOpenAI(api_key=os.getenv("OPENAI_API_KEY"))
            response = await client.chat.completions.create(
                model=model or "gpt-4o",
                messages=[
                    {"role": "system", "content": system_prompt},
                    {"role": "user", "content": message},
                ],
                temperature=temperature,
            )
            return response.choices[0].message.content

        elif provider == "anthropic":
            import anthropic

            client = anthropic.AsyncAnthropic(api_key=os.getenv("ANTHROPIC_API_KEY"))
            response = await client.messages.create(
                model=model or "claude-3-5-sonnet-20241022",
                max_tokens=assistant.agent.max_tokens,
                system=system_prompt,
                messages=[{"role": "user", "content": message}],
            )
            return response.content[0].text

        elif provider == "google":
            import google.generativeai as genai

            genai.configure(api_key=os.getenv("GOOGLE_API_KEY"))
            gmodel = genai.GenerativeModel(
                model_name=model or "gemini-2.5-flash",
                system_instruction=system_prompt,
            )
            response = await gmodel.generate_content_async(message)
            return response.text

        elif provider == "groq":
            from groq import AsyncGroq

            client = AsyncGroq(api_key=os.getenv("GROQ_API_KEY"))
            response = await client.chat.completions.create(
                model=model or "llama-3.3-70b-versatile",
                messages=[
                    {"role": "system", "content": system_prompt},
                    {"role": "user", "content": message},
                ],
                temperature=temperature,
            )
            return response.choices[0].message.content

        return f"Provider '{provider}' not supported for text-only chat."
