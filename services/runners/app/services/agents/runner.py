from app.services.agents.pipelines.agent_pipeline_engine import AgentPipelineEngine
from app.schemas.agent import AgentConfig


async def spawn_bot(room_url: str, bot_token: str, config: AgentConfig, room_name: str):
    """
    Punto de entrada para levantar un bot basado en la configuración del agente.
    """
    orchestrator = AgentPipelineEngine(room_url, bot_token, config, room_name)
    await orchestrator.run()
