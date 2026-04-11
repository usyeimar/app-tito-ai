import logging
import uuid
import json
from fastapi import APIRouter, HTTPException, status, Path, Request
from fastapi.responses import Response, HTMLResponse
from app.schemas.deployments import (
    SIPProvisionRequest,
    SIPProvisionResponse,
    SIPRotateKeyResponse,
    DeploymentLink,
)
from app.schemas.widget import WidgetConfig, WidgetResponse
from app.schemas.sessions import ActionResponse
from app.services.deployment_service import deployment_service

logger = logging.getLogger(__name__)

router = APIRouter()


@router.get(
    "/widget/preview-frame",
    response_class=HTMLResponse,
    summary="Frame de Previsualización del Widget",
)
async def get_widget_preview_frame(
    agent_id: str, request: Request, workspace_slug: str = None
):
    """
    Sirve el HTML interactivo que vive dentro del iframe del widget.
    """
    # Intentar recuperar configuración del widget
    # Si no se pasa workspace_slug, buscamos en Redis por patrón (escalable para preview)
    config_data = None
    if workspace_slug:
        config_data = await deployment_service.get_widget_config(
            workspace_slug, agent_id
        )
    else:
        # Búsqueda fallback por agent_id
        keys = await deployment_service._redis.keys(f"deployment:widget:*:{agent_id}")
        if keys:
            raw_data = await deployment_service._redis.get(keys[0])
            config_data = json.loads(raw_data) if raw_data else None

    # Valores por defecto si no hay config
    appearance = config_data.get("appearance", {}) if config_data else {}
    primary_color = appearance.get("primary_color", "#FF5733")
    welcome_msg = appearance.get("welcome_message", "¡Hola! Soy tu asistente IA.")
    button_text = appearance.get("button_label", "Comenzar Llamada")

    base_url = str(request.base_url).rstrip("/")

    html_content = f"""
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tito AI Widget Preview (RTVI)</title>
    <style>
        body {{
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0; padding: 0;
            display: flex; flex-direction: column;
            height: 100vh; background: #fff;
            border-radius: 12px; overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }}
        .header {{
            background: {primary_color};
            color: white; padding: 20px;
            text-align: center; font-weight: bold;
        }}
        .content {{
            flex: 1; padding: 20px;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            text-align: center; gap: 20px;
        }}
        .status {{ font-size: 14px; color: #666; }}
        .btn-talk {{
            background: {primary_color};
            color: white; border: none;
            padding: 15px 30px; border-radius: 30px;
            font-size: 16px; font-weight: bold;
            cursor: pointer; transition: transform 0.2s;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }}
        .btn-talk:active {{ transform: scale(0.95); }}
        .btn-talk:disabled {{ background: #ccc; cursor: not-allowed; }}
        .pulse {{
            animation: pulse-animation 2s infinite;
        }}
        @keyframes pulse-animation {{
            0% {{ box-shadow: 0 0 0 0px {primary_color}44; }}
            100% {{ box-shadow: 0 0 0 20px {primary_color}00; }}
        }}
    </style>
</head>
<body>
    <div class="header">Tito AI Assistant</div>
    <div class="content">
        <p id="welcome-text">{welcome_msg}</p>
        <p id="status" class="status">Listo para conectar</p>
        <button id="talk-btn" class="btn-talk">{button_text}</button>
    </div>

    <script type="module">
        import {{ PipecatClient, RTVIEvent }} from "https://esm.sh/@pipecat-ai/client-js";
        import {{ DailyTransport }} from "https://esm.sh/@pipecat-ai/daily-transport";

        const talkBtn = document.getElementById('talk-btn');
        const statusText = document.getElementById('status');
        
        // Datos de la sesión para el Runner (Basados en el agente MVP Luna)
        const sessionData = {{
            version: "1.0.0",
            agent_id: "{agent_id}",
            tenant_id: "{workspace_slug or "preview"}",
            metadata: {{ 
                name: "Luna - Travel Guru", 
                slug: "luna-travel-guru", 
                description: "Asistente de viajes premium", 
                language: "es-CO" 
            }},
            brain: {{ 
                llm: {{ 
                    provider: "openai", 
                    model: "gpt-4o", 
                    instructions: `# Identidad
- Eres Luna, una experta conserje de viajes de alto nivel. Tono elegante y acogedor.
- CRÍTICO: Nunca digas 'soy una IA', actúa como una asistente personal humana.`
                }} 
            }},
            runtime_profiles: {{
                stt: {{ provider: "deepgram", model: "nova-2" }},
                tts: {{ 
                    provider: "cartesia", 
                    voice_id: "79a125e8-cd45-4c13-8a67-188112f4dd22" 
                }}
            }}
        }};

        // 1. Configurar el Cliente de Pipecat (RTVI)
        const rtviClient = new PipecatClient({{
            transport: new DailyTransport(),
            params: {{
                baseUrl: "{base_url}/api/v1",
                requestData: sessionData
            }},
            enableMic: true,
            enableCam: false
        }});

        // --- Eventos de RTVI ---
        rtviClient.on(RTVIEvent.TransportStateChanged, (state) => {{
            console.log("🔄 State:", state);
            if (state === "connecting") statusText.innerText = "Conectando...";
            if (state === "connected") statusText.innerText = "● Conectado";
            if (state === "disconnected") {{
                statusText.innerText = "Desconectado";
                talkBtn.innerText = "{button_text}";
                talkBtn.classList.remove('pulse');
                talkBtn.disabled = false;
                
                // Restaurar handler inicial
                talkBtn.onclick = startCall;
            }}
        }});

        rtviClient.on(RTVIEvent.BotReady, () => {{
            console.log("🤖 Bot is ready!");
            statusText.innerText = "● Agente listo para hablar";
            talkBtn.innerText = "Terminar";
            talkBtn.classList.add('pulse');
            talkBtn.disabled = false;
            
            // Cambiar comportamiento a desconectar
            talkBtn.onclick = () => rtviClient.disconnect();
        }});

        rtviClient.on(RTVIEvent.Error, (error) => {{
            console.error("❌ RTVI Error:", error);
            statusText.innerText = "Error de conexión";
            talkBtn.disabled = false;
        }});

        async function startCall() {{
            try {{
                talkBtn.disabled = true;
                statusText.innerText = "Iniciando sesión...";
                
                // 1. Crear sesión manualmente para tener control total
                const response = await fetch("{base_url}/api/v1/sessions", {{
                    method: 'POST',
                    headers: {{ 'Content-Type': 'application/json' }},
                    body: JSON.stringify(sessionData)
                }});
                
                const data = await response.json();
                if (!response.ok) throw new Error(data.detail || "Error al crear sesión");

                console.log("✅ Sesión creada, conectando transporte...", data.url);

                // 2. Conectar el cliente de Pipecat usando los datos recibidos
                await rtviClient.connect({{
                    url: data.url,
                    token: data.access_token
                }}); 

            }} catch (err) {{
                console.error("❌ Error fatal:", err);
                statusText.innerText = "Error: " + err.message;
                talkBtn.disabled = false;
            }}
        }}

        talkBtn.onclick = startCall;
    </script>
</body>
</html>
    """
    return HTMLResponse(content=html_content)


def get_sip_links(request: Request, workspace_slug: str, agent_id: str):
    """Genera enlaces HATEOAS para un despliegue SIP."""
    base_url = str(request.base_url).rstrip("/")
    api_path = f"{base_url}/api/v1/deployments/sip/{workspace_slug}/{agent_id}"

    return {
        "self": DeploymentLink(href=api_path, method="GET"),
        "rotate_key": DeploymentLink(href=f"{api_path}/rotate-key", method="POST"),
        "delete": DeploymentLink(href=api_path, method="DELETE"),
        "update": DeploymentLink(
            href=f"{base_url}/api/v1/deployments/sip", method="POST"
        ),
    }


def get_widget_links(request: Request, workspace_slug: str, agent_id: str):
    """Genera enlaces HATEOAS para un despliegue de Widget."""
    base_url = str(request.base_url).rstrip("/")
    api_path = f"{base_url}/api/v1/deployments/widget/{workspace_slug}/{agent_id}"

    return {
        "self": DeploymentLink(href=api_path, method="GET"),
        "embed_js": DeploymentLink(
            href=f"{base_url}/api/v1/deployments/widget/{agent_id}/embed.js",
            method="GET",
        ),
        "delete": DeploymentLink(href=api_path, method="DELETE"),
    }


# --- SIP Endpoints ---


@router.post(
    "/sip",
    response_model=SIPProvisionResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Provisionar Canal SIP",
)
async def provision_sip_channel(request_body: SIPProvisionRequest, request: Request):
    """
    Crea o actualiza el despliegue de un agente en telefonía SIP.
    """
    try:
        deployment = await deployment_service.provision_sip(request_body)
        deployment["workspace_subdomain"] = f"{request_body.workspace_slug}.sip.tito.ai"
        deployment["_links"] = get_sip_links(
            request, request_body.workspace_slug, request_body.agent_id
        )
        return deployment
    except Exception as e:
        logger.error(f"❌ Failed to provision SIP: {e}")
        raise HTTPException(
            status_code=500, detail="Error interno al procesar el despliegue SIP."
        )


@router.get(
    "/sip/{workspace_slug}/{agent_id}",
    response_model=SIPProvisionResponse,
    summary="Consultar Despliegue SIP",
)
async def get_sip_channel(
    request: Request,
    workspace_slug: str = Path(..., examples=["alloy-finance"]),
    agent_id: str = Path(..., examples=["agent-001"]),
):
    deployment = await deployment_service.get_deployment(workspace_slug, agent_id)
    if not deployment:
        raise HTTPException(status_code=404, detail="Despliegue SIP no encontrado.")

    deployment["workspace_subdomain"] = f"{workspace_slug}.sip.tito.ai"
    deployment["_links"] = get_sip_links(request, workspace_slug, agent_id)
    return deployment


@router.post(
    "/sip/{workspace_slug}/{agent_id}/rotate-key",
    response_model=SIPRotateKeyResponse,
    summary="Rotar API Key SIP",
)
async def rotate_sip_key(request: Request, workspace_slug: str, agent_id: str):
    try:
        new_key = await deployment_service.rotate_sip_key(workspace_slug, agent_id)
        return SIPRotateKeyResponse(
            agent_id=agent_id,
            new_api_key=new_key,
            _links=get_sip_links(request, workspace_slug, agent_id),
        )
    except ValueError as e:
        raise HTTPException(status_code=404, detail=str(e))


# --- Widget Endpoints ---


@router.post(
    "/widget",
    response_model=WidgetResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Configurar Web Widget",
)
async def configure_widget(config: WidgetConfig, request: Request):
    """
    Configura la apariencia y dominios permitidos para el Web Widget de un agente.
    """
    try:
        data = await deployment_service.save_widget_config(config)
        base_url = str(request.base_url).rstrip("/")

        embed_script = f'<script src="{base_url}/api/v1/deployments/widget/{config.agent_id}/embed.js" async></script>'
        preview_url = f"{base_url}/api/v1/deployments/widget/{config.workspace_slug}/{config.agent_id}/preview"

        return WidgetResponse(
            agent_id=config.agent_id,
            config=config,
            embed_script=embed_script,
            preview_url=preview_url,
            _links=get_widget_links(request, config.workspace_slug, config.agent_id),
        )
    except Exception as e:
        logger.error(f"❌ Failed to configure widget: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@router.get(
    "/widget/{agent_id}/embed.js",
    summary="Servir Script del Widget",
)
async def get_widget_script(agent_id: str, request: Request):
    """
    Devuelve el código JavaScript para inyectar el widget en cualquier sitio web.
    """
    base_url = str(request.base_url).rstrip("/")
    js_content = f"""
(function() {{
    console.log("🚀 Tito AI Widget Loading for agent: {agent_id}");
    const baseUrl = "{base_url}";
    const containerId = 'tito-ai-widget-' + Math.random().toString(36).substr(2, 9);
    
    document.write('<div id="' + containerId + '"></div>');
    
    const iframe = document.createElement('iframe');
    iframe.src = baseUrl + "/api/v1/deployments/widget/preview-frame?agent_id={agent_id}";
    iframe.style.position = 'fixed';
    iframe.style.bottom = '20px';
    iframe.style.right = '20px';
    iframe.style.width = '400px';
    iframe.style.height = '600px';
    iframe.style.border = 'none';
    iframe.style.zIndex = '9999';
    iframe.allow = "microphone";
    document.getElementById(containerId).appendChild(iframe);
}})();
    """
    return Response(content=js_content, media_type="application/javascript")


@router.delete(
    "/{type}/{workspace_slug}/{agent_id}",
    response_model=ActionResponse,
    summary="Eliminar Despliegue",
)
async def delete_deployment(
    workspace_slug: str,
    agent_id: str,
    type: str = Path(..., description="Tipo de despliegue: sip o widget"),
):
    """
    Elimina un despliegue (SIP o Widget) del sistema.
    """
    success = await deployment_service.delete_deployment(
        workspace_slug, agent_id, type=type
    )
    if not success:
        raise HTTPException(
            status_code=404, detail=f"Despliegue tipo {type} no encontrado."
        )

    return ActionResponse(
        success=True,
        message=f"Despliegue {type} para {agent_id} eliminado exitosamente.",
    )
