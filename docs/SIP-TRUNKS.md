# Documentación de SIP Trunks (Runners)

El módulo de **SIP Trunks** dentro de `services/runners` proporciona la capacidad de conectar agentes de Inteligencia Artificial de voz a través de telefonía SIP utilizando diferentes modos de conectividad con PBX externas o proveedores telefónicos.

## Modos de Conexión Soportados

El modelo de "Customer-Owned Trunks" (Trunks propiedad del cliente) permite a los clientes integrar sus propias líneas telefónicas mediante 3 modalidades diferentes:

### 1. Modo Inbound (Entrante)
**Uso:** El cliente configura un Trunk en su propia PBX (ej. Asterisk, FreePBX, 3CX) apuntando al servidor de Tito AI.
- **Funcionamiento:** 
  - El sistema de Tito AI expone credenciales SIP (`username` y `password`).
  - La PBX del cliente se conecta a Tito AI mediante SIP INVITE.
  - El cliente configura qué extensión mapea a qué agente de IA (ej. marcar la extensión 100 redirige al agente de ventas, extensión 200 al de soporte).
- **Ventaja principal:** Permite consolidar múltiples agentes en un solo Trunk. No se necesita configurar credenciales complejas por cada agente.

### 2. Modo Register (Registro Remoto)
**Uso:** El Asterisk interno de Tito AI se registra *hacia* la PBX del cliente, actuando como si fuera un dispositivo físico o softphone.
- **Funcionamiento:** 
  - Tito AI se registra en la IP pública o dominio de la PBX del cliente usando unas credenciales SIP.
  - Cuando alguien llama a esa extensión en la PBX del cliente, la PBX enruta la llamada a Tito AI.
- **Ventaja principal:** Útil en entornos con NAT o firewalls restrictivos donde la PBX del cliente no puede hacer peticiones salientes (Inbound mode) libremente, o cuando la PBX requiere un registro SIP explícito.

### 3. Modo Outbound (Saliente)
**Uso:** Permite a un Agente de IA iniciar llamadas telefónicas directamente hacia la red telefónica pública (PSTN).
- **Funcionamiento:** 
  - Se configura un Trunk con los datos de un carrier SIP (como Twilio, VoIP.ms, etc.).
  - El usuario hace una petición a la API (`POST /api/v1/trunks/{trunk_id}/calls`) y el Agente de IA origina la llamada.
- **Características:**
  - Límite configurable de llamadas simultáneas (`max_concurrent_calls`).
  - Control sobre el `caller_id` mostrado al usuario destino.

## Arquitectura de Componentes

La implementación del soporte de Trunks se ubica en el servicio en Python (`services/runners/`) y consta de:

- **Data Models:** `app/schemas/trunks.py` define los esquemas Pydantic (`CreateTrunkRequest`, `TrunkOutboundConfig`, `TrunkRouteConfig`, etc.).
- **Servicios:** `app/services/trunk_service.py` maneja la persistencia de datos y el estado usando Redis (como almacenar el mapa de extensiones a agentes y contar las llamadas activas).
- **Endpoints API:** `app/api/v1/trunks.py` expone el CRUD completo (crear trunks, añadir rutas, originar llamadas).
- **Controlador SIP / AMI:** La conexión y ruteo de red en vivo se gestiona integrando el sistema de telefonía Asterisk (a través del `AMIController` en `app/services/sip/`).

## Flujo de Orquestación

Cuando una llamada SIP entra o sale del sistema, la resolución ocurre de esta manera:
1. `call_handler.py` procesa la sesión de la llamada entrante.
2. Llama a `TrunkService.resolve_inbound_call()` o `resolve_register_call()` para determinar a qué Trunk y a qué Agente de IA corresponde la llamada basándose en la extensión marcada o el dominio SIP.
3. Se actualizan los contadores en Redis (`increment_active_calls`) para evitar superar el máximo permitido.
4. El motor de IA interactúa en la sesión de audio usando sockets o WebRTC hasta que la llamada termina.

---
*Para ver en detalle los payloads de la API y ejemplos de JSON, consulte el archivo de diseño original en `services/runners/PLAN.md`.*
