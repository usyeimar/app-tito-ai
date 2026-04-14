# Propuesta: Refactor de URLs con prefijos de canal

## Contexto

El agent config actual (`tito-agent-mvp.json`) es **channel-agnostic** (como Vapi).
El mismo agente funciona en SIP, WebRTC, Widget y WhatsApp.
El canal se determina por **cómo llega la conexión**, no por el config.

Sin embargo, los endpoints actuales no indican claramente qué canal atienden:

```
POST /api/v1/sessions/              ← ¿WebRTC? ¿Widget?
WS   /api/v1/sessions/{id}/audio    ← Widget (implícito)
WS   /api/v1/sip/ari/audio          ← SIP ARI
TCP  :9092                          ← SIP AudioSocket
```

## Solución: Prefijos de canal en URLs

Reorganizar los routers bajo `/api/v1/channels/{canal}/...`.

### URLs finales

```
POST /api/v1/channels/webrtc/sessions/       ← Crear sesión WebRTC (LiveKit/Daily)
DELETE /api/v1/channels/webrtc/sessions/{id}  ← Terminar sesión WebRTC

POST /api/v1/channels/widget/sessions/       ← Crear sesión Widget
WS   /api/v1/channels/widget/{id}/audio      ← Audio Widget (48kHz PCM)
DELETE /api/v1/channels/widget/sessions/{id}  ← Terminar sesión Widget

WS   /api/v1/channels/sip/ari/audio          ← SIP ARI ExternalMedia (8kHz slin)
POST /api/v1/channels/sip/calls/{id}/hangup  ← Colgar llamada SIP
GET  /api/v1/channels/sip/health             ← Health check SIP bridge

POST /api/v1/channels/whatsapp/webhook       ← Recibe mensajes WhatsApp Cloud API
GET  /api/v1/channels/whatsapp/verify        ← Verificación de webhook

GET  /api/v1/metrics                         ← Métricas (sin cambios)
GET  /api/v1/deployments                     ← Deployments (sin cambios)
GET  /api/v1/trunks                          ← SIP Trunks (sin cambios)
```

### Estructura de archivos

```
app/api/v1/
├── __init__.py                  ← registra channels/, metrics, deployments, trunks
├── channels/
│   ├── __init__.py              ← registra webrtc, widget, sip, whatsapp
│   ├── webrtc.py                ← sesiones WebRTC
│   ├── widget.py                ← sesiones Widget + WS audio
│   ├── sip.py                   ← SIP ARI + AudioSocket + health
│   └── whatsapp.py              ← WhatsApp webhook + verify
├── metrics.py                   ← sin cambios
├── deployments.py               ← sin cambios
└── trunks.py                    ← sin cambios
```

### Qué se mueve dónde

| Archivo actual              | Archivo nuevo          | Cambio                                                      |
| --------------------------- | ---------------------- | ----------------------------------------------------------- |
| `sessions.py` (WebRTC part) | `channels/webrtc.py`   | Extraer handler `create_session` con provider livekit/daily |
| `sessions.py` (Widget part) | `channels/widget.py`   | Extraer handler websocket + audio WS                        |
| `sip.py`                    | `channels/sip.py`      | Mover con prefijo /sip                                      |
| (no existe)                 | `channels/whatsapp.py` | Nuevo: webhook + verify                                     |

### Session metadata en Redis

Se agrega campo `channel` explícito:

```json
{
    "session_id": "sess_abc123",
    "provider": "livekit",
    "channel": "webrtc",
    "agent_id": "tito-mvp-001",
    "tenant_id": "tito-ai",
    "status": "running",
    "created_at": 1713062400.0
}
```

### Handoff multi-canal

El tool `transferir_a_humano` usa `internal:handoff` que detecta el canal
por el `provider` guardado en la sesión y ejecuta el mecanismo correcto:

| provider                | handoff mechanism         |
| ----------------------- | ------------------------- |
| `"livekit"` / `"daily"` | LiveKit room transfer     |
| `"websocket"`           | Redirect a URL de soporte |
| `"ari"` / `"sip"`       | SIP REFER a cola          |
| `"whatsapp"`            | Webhook al CRM            |

### Adicionar un canal nuevo (ej: Telegram)

1. Crear `app/api/v1/channels/telegram.py`
2. Registrar en `channels/__init__.py`
3. Crear transport handler en `app/services/agents/pipelines/`
4. Agregar al `AgentConfig` (no se necesita, es channel-agnostic)
5. Listo

## Comparación con competencia

|                        | Vapi                          | Tito (actual)            | Tito (propuesta)         |
| ---------------------- | ----------------------------- | ------------------------ | ------------------------ |
| **Agent config**       | Channel-agnostic              | Channel-agnostic         | Channel-agnostic         |
| **Canal se determina** | En el endpoint o phone number | Implícito por endpoint   | Explícito en URL prefix  |
| **URLs**               | `/call/web`, `/call`          | `/sessions/`, `/sip/...` | `/channels/{canal}/...`  |
| **Swagger**            | Separado por tipo             | Mezclado                 | Separado por canal       |
| **Nuevo canal**        | Nuevo endpoint                | Copiar/pegar código      | Nuevo router + transport |

## Archivos a modificar

- `docs/resources/tito-agent-mvp.json` (creado)
- `app/api/v1/__init__.py` (cambiar imports)
- `app/api/v1/sessions.py` (dividir en webrtc.py + widget.py)
- `app/api/v1/sip.py` (mover a channels/)
- `app/api/v1/channels/__init__.py` (nuevo)
- `app/api/v1/channels/webrtc.py` (nuevo, desde sessions.py)
- `app/api/v1/channels/widget.py` (nuevo, desde sessions.py)
- `app/api/v1/channels/sip.py` (nuevo, desde sip.py)
- `app/api/v1/channels/whatsapp.py` (nuevo)
- `app/services/session_manager.py` (agregar campo `channel`)
