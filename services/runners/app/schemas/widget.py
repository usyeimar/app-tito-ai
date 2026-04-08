from typing import Optional, Literal, Dict, Any
from pydantic import BaseModel, Field

class WidgetAppearance(BaseModel):
    """Configuración visual del widget."""
    primary_color: str = Field("#FF5733", description="Color principal en HEX.")
    position: Literal["bottom-right", "bottom-left"] = Field("bottom-right")
    logo_url: Optional[str] = Field(None, description="URL del logo del agente.")
    font_family: str = Field("Inter, system-ui, sans-serif")
    welcome_message: str = Field("¡Hola! ¿En qué puedo ayudarte hoy?", description="Mensaje que aparece al abrir.")
    button_label: str = Field("Hablar con Luna", description="Texto del botón de acción.")

class WidgetConfig(BaseModel):
    """Configuración completa del despliegue tipo Widget."""
    agent_id: str
    workspace_slug: str
    appearance: WidgetAppearance = Field(default_factory=WidgetAppearance)
    allowed_domains: list[str] = Field(default_factory=list, description="Lista de dominios permitidos (CORS).")
    language: str = Field("es-MX", description="Idioma de la interfaz del widget.")
    enabled: bool = True

class WidgetResponse(BaseModel):
    """Respuesta con la configuración y el script de integración."""
    agent_id: str
    config: WidgetConfig
    embed_script: str = Field(..., description="Tag <script> para insertar en el sitio web.")
    preview_url: str = Field(..., description="URL para previsualizar el widget.")
    links: Dict[str, Any] = Field(default_factory=dict, alias="_links")

    model_config = {"populate_by_name": True}
