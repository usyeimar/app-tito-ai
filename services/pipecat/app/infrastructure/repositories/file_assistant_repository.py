import glob
import json
import os
from typing import List, Optional

from loguru import logger

from app.domains.assistant.models.assistant import Assistant
from app.domains.assistant.repositories.assistant_repository import AssistantRepository


class FileAssistantRepository(AssistantRepository):
    def __init__(self, data_dir: str):
        self.data_dir = data_dir
        os.makedirs(self.data_dir, exist_ok=True)

    # ------------------------------------------------------------------
    # Internal helpers
    # ------------------------------------------------------------------

    def _resolve_id(self, assistant_id: str) -> str:
        """Support slug/alias → UUID mapping via an optional migration file."""
        mapping_path = os.path.join(self.data_dir, "migration_mapping.json")
        if os.path.exists(mapping_path):
            try:
                with open(mapping_path) as f:
                    mapping = json.load(f)
                    return mapping.get(assistant_id, assistant_id)
            except Exception:
                pass
        return assistant_id

    def _get_file_path(self, assistant_id: str) -> str:
        actual_id = self._resolve_id(assistant_id)
        return os.path.join(self.data_dir, f"{actual_id}.json")

    @staticmethod
    def _migrate_v1_to_v3(data: dict) -> dict:
        """
        Best-effort migration of old (v1/v2) assistant JSON to the v3 schema.
        Returns the data unchanged if it already looks like v3.
        """
        # Already v3 if it has a 'metadata' key or version >= 3
        version = str(data.get("version", "1.0.0"))
        if data.get("metadata") or version.startswith("3"):
            return data

        migrated: dict = {
            "id": data.get("id", ""),
            "version": "3.0.0",
            "created_at": data.get("created_at"),
            "metadata": {
                "name": data.get("name", "Migrated Agent"),
                "description": data.get("description"),
                "tags": [],
                "language": "en",
            },
            "architecture": {
                "type": data.get("architecture_type", "simple"),
                "config": {},
            },
            "agent": data.get("agent", {}),
            "io_layer": data.get("io_layer", {}),
            "pipeline_settings": data.get("pipeline_settings", {}),
            "capabilities": {
                "tools": [],
                "mcp_servers": [],
                "guardrails": {},
                "handoff": {"enabled": False},
            },
            "observability": {
                "logging_level": "info",
                "webhooks": data.get("webhooks"),
            },
            "compliance": {},
            "flow": data.get("flow"),
        }

        # Lift tools from agent into capabilities
        agent_tools = migrated["agent"].get("tools", [])
        if agent_tools:
            migrated["capabilities"]["tools"] = [
                {"name": t.get("function", {}).get("name", t.get("name", "unknown")),
                 "type": "function_call",
                 "disabled": False}
                for t in agent_tools
            ]

        return migrated

    # ------------------------------------------------------------------
    # Repository interface
    # ------------------------------------------------------------------

    def save(self, assistant: Assistant) -> Assistant:
        file_path = self._get_file_path(assistant.id)
        with open(file_path, "w") as f:
            json.dump(assistant.model_dump(mode="json"), f, indent=4, default=str)
        return assistant

    def get(self, assistant_id: str) -> Optional[Assistant]:
        file_path = self._get_file_path(assistant_id)
        if not os.path.exists(file_path):
            return None
        try:
            with open(file_path) as f:
                data = json.load(f)
            data = self._migrate_v1_to_v3(data)
            return Assistant(**data)
        except Exception as e:
            logger.error(f"Error loading assistant {assistant_id}: {e}")
            return None

    def list_all(self) -> List[Assistant]:
        assistants = []
        for file_path in glob.glob(os.path.join(self.data_dir, "*.json")):
            if "migration_mapping" in file_path or "campaign" in file_path:
                continue
            try:
                with open(file_path) as f:
                    data = json.load(f)
                # Accept both old ("agent" key) and new ("metadata" key) formats
                if "agent" in data or "metadata" in data:
                    data = self._migrate_v1_to_v3(data)
                    assistants.append(Assistant(**data))
            except Exception as e:
                logger.warning(f"Skipping {file_path}: {e}")
        return assistants

    def delete(self, assistant_id: str) -> bool:
        file_path = self._get_file_path(assistant_id)
        if os.path.exists(file_path):
            os.remove(file_path)
            return True
        return False
