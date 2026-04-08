import logging
import asyncio
from typing import Dict, Any, List, Optional, Callable
import aiohttp
from pipecat.services.llm_service import FunctionCallParams
from pipecat.frames.frames import TextFrame

logger = logging.getLogger(__name__)


class AgentTools:
    """
    Procesador genérico de herramientas para agentes con soporte para:
    - Mensajes de "acción en curso" dinámicos.
    - Timeouts de ejecución.
    - Notificación de errores al usuario.
    """

    def __init__(self, agent_id: str, metadata: Optional[Dict[str, Any]] = None):
        self.agent_id = agent_id
        self.metadata = metadata or {}
        self.webhook_url = None
        self.queue_frame_handler: Optional[Callable] = None
        self.rtvi_handler: Optional[Any] = None
        
        # Mapa de nombres técnicos a mensajes de procesamiento dinámicos
        self.processing_messages = {}

    def set_queue_handler(self, handler: Callable):
        """Asigna el manejador para enviar frames al pipeline (TTS)."""
        self.queue_frame_handler = handler

    def set_rtvi_handler(self, rtvi_processor: Any):
        """Asigna el procesador RTVI para enviar mensajes de estado visual."""
        self.rtvi_handler = rtvi_processor

    def register_on_llm(self, llm_service, tools: List[Any]):
        """Registra dinámicamente todas las herramientas habilitadas en el LLM."""
        for tool in tools:
            if getattr(tool, "disabled", False):
                continue

            func_name = getattr(tool, "name", None)
            if not func_name:
                continue

            # Guardar el mensaje de procesamiento (processing_message) si existe
            if hasattr(tool, "processing_message") and tool.processing_message:
                self.processing_messages[func_name] = tool.processing_message

            logger.info(f"🛠️ Registrando acción genérica: {func_name} (ID Agente: {self.agent_id})")
            llm_service.register_function(func_name, self.handle_action)

    async def handle_action(self, params: FunctionCallParams):
        """
        Manejador central para todas las llamadas a funciones.
        """
        func_name = params.function_name
        args = params.arguments
        
        # 🔊 RTVI: Notificar que se está ejecutando una herramienta
        if self.rtvi_handler:
            try:
                await self.rtvi_handler.send_server_message({
                    "event": "tool-executing", 
                    "tool": func_name
                })
            except Exception as e:
                logger.debug(f"ℹ️ Error opcional al enviar RTVI tool-executing: {e}")

        # Caso especial: Finalizar llamada
        if func_name == "finalizar_llamada":
            logger.info(f"🔌 El agente ha solicitado finalizar la llamada.")
            if self.queue_frame_handler:
                from pipecat.frames.frames import EndFrame
                # Damos un pequeño margen para que termine de decir la despedida si la hay
                await asyncio.sleep(1.5)
                await self.queue_frame_handler(EndFrame())
            if params.result_callback:
                await params.result_callback({"status": "success", "message": "Llamada finalizada."})
            return

        # 1. Notificar al usuario que estamos trabajando usando el processing_message dinámico
        msg = self.processing_messages.get(func_name)
        if msg and self.queue_frame_handler:
            await self.queue_frame_handler(TextFrame(f"Un momento por favor, estoy {msg}..."))

        logger.info(f"⚡ Ejecutando acción: {func_name} | Args: {args}")

        # 2. Ejecutar con Timeout (ej. 12 segundos)
        result = {"status": "error", "message": "Unknown error"}
        try:
            result = await asyncio.wait_for(
                self._call_external_api(func_name, args), 
                timeout=12.0
            )
        except asyncio.TimeoutError:
            logger.error(f"⏱️ Timeout ejecutando {func_name}")
            result = {
                "status": "error",
                "message": "La operación tardó demasiado tiempo en responder."
            }
            if self.queue_frame_handler:
                await self.queue_frame_handler(TextFrame("Lo siento, la operación está tardando más de lo esperado. Intentemos algo más."))
        except Exception as e:
            logger.error(f"❌ Error ejecutando {func_name}: {e}")
            result = {"status": "error", "message": str(e)}

        # 🔊 RTVI: Notificar que la herramienta terminó
        if self.rtvi_handler:
            try:
                await self.rtvi_handler.send_server_message({
                    "event": "tool-completed", 
                    "tool": func_name,
                    "status": result.get("status", "success")
                })
            except Exception as e:
                logger.debug(f"ℹ️ Error opcional al enviar RTVI tool-completed: {e}")

        if params.result_callback:
            await params.result_callback(result)

    async def _call_external_api(self, function_name: str, arguments: Dict[str, Any]) -> Dict[str, Any]:
        """
        Envía la acción a un servicio externo (ej. Laravel).
        """
        # Simulamos una pequeña espera para que se note la interrupción y el mute
        await asyncio.sleep(1.5)

        if not self.webhook_url:
            return {
                "status": "success",
                "data": arguments,
                "message": f"La acción '{function_name}' fue completada (simulación)."
            }

        payload = {
            "agent_id": self.agent_id,
            "function": function_name,
            "arguments": arguments,
            "metadata": self.metadata
        }

        try:
            async with aiohttp.ClientSession() as session:
                async with session.post(self.webhook_url, json=payload) as response:
                    return await response.json()
        except Exception as e:
            logger.error(f"❌ Error de red al contactar al backend: {e}")
            raise e
