# Plan de Desarrollo: Tito - Orquestación de Agentes y Sesiones de Voz

Este roadmap detalla la evolución de **Tito** como una plataforma SaaS de IA Conversacional. Se divide en componentes de **Core/Control (Laravel)** y **Ejecución en Tiempo Real (Pipecat/Python)**.

---

## 🛠 Estándares de Calidad (Definition of Done)
Para considerar una Historia de Usuario (HU) como **Finalizada**, debe cumplir:
1. **Código:** Siguiendo PSR-12 (PHP) y PEP 8 (Python). Formateado con Pint/Ruff.
2. **Pruebas:** Cobertura de tests unitarios/feature (Pest para Laravel, Pytest para Pipecat).
3. **Documentación:** Contratos de API actualizados y comentarios en métodos complejos.
4. **Seguridad:** Validación de Multi-tenancy y saneamiento de entradas.

---

## Fase 1: Agents API & Core (Laravel)
*Responsable de la persistencia, configuración, facturación y panel de control.*

### Épica 1: Arquitectura de Agentes y Multi-tenancy
*   **[ ] HU-1.1: Aprovisionamiento de Identidad del Agente**
    *   **Como** Administrador del Tenant.
    *   **Quiero** registrar agentes con UUIDs únicos y metadatos regionales (idioma, zona horaria).
    *   **Criterios de Aceptación (CA):**
        *   **CA1:** Validación de `locale` contra ISO 639-1.
        *   **CA2:** Aislamiento estricto de datos (Scope Global de Tenant).
*   **[ ] HU-1.2: Configuración del Cerebro (Brain) y Voz (Voice)**
    *   **Como** Ingeniero de Prompts.
    *   **Quiero** definir proveedores de LLM (OpenAI, Anthropic) y TTS (Cartesia, ElevenLabs) por agente.
    *   **CA:** Validación de esquemas JSON para `brain_config` y soporte de `templating` en el System Prompt.
*   **[ ] HU-1.3: Control de Ciclo de Vida (Runtime Config)**
    *   **Como** Gerente de Operaciones.
    *   **Quiero** establecer límites de `max_duration` y `idle_timeout`.
    *   **CA:** El sistema debe forzar el cierre de sesiones que excedan el límite para evitar fugas de costes.

### Épica 2: Integraciones y Webhooks (Tool Calling)
*   **[ ] HU-2.1: Registro de Herramientas Dinámicas**
    *   **Como** Desarrollador.
    *   **Quiero** registrar esquemas de herramientas (JSON Schema) que el LLM pueda invocar.
    *   **CA:** Validación de firma de Webhooks y persistencia segura de Headers de autenticación.

### Épica 3: SaaS, Seguridad y Playground
*   **[ ] HU-3.1: Gestión de API Keys del Tenant**
    *   **Como** Cliente Pro.
    *   **Quiero** generar llaves de API con scopes limitados para integrar Tito en mis sistemas.
    *   **CA:** Implementación de rotación de llaves y middleware de validación.
*   **[ ] HU-3.2: Simulador WebRTC (Playground)**
    *   **Como** Diseñador de Agentes.
    *   **Quiero** probar al agente en una interfaz web antes de pasarlo a producción.
    *   **CA:** Generación de tokens efímeros para la conexión WebRTC con Pipecat.

---

## Fase 2: Pipecat Engine (Runners & Workers)
*Responsable del procesamiento de audio, latencia y flujos de IA.*

### Épica 4: Pipeline de Audio en Tiempo Real
*   **[ ] HU-4.1: Orquestación de Pipeline (STT -> LLM -> TTS)**
    *   **Como** Motor de Voz.
    *   **Quiero** inicializar una pipeline de Pipecat validando el JWT enviado por Laravel.
    *   **CA:** Soporte para cambio dinámico de modelos sin reiniciar el socket.

### Épica 5: Naturalidad y Gestión de Interrupciones (Barge-In)
*   **[ ] HU-5.1: Detección de Actividad de Voz (VAD) Inteligente**
    *   **Como** Usuario.
    *   **Quiero** que el bot deje de hablar inmediatamente si yo digo algo importante.
    *   **CA (BDD):** 
        *   **Dado** que el bot reproduce audio, **Cuando** detecto voz continua > 400ms, **Entonces** detengo el TTS en < 100ms y trunco el contexto del LLM.
*   **[ ] HU-5.2: Gestión de Silencios y Abandono (Idle Logic)**
    *   **Como** Usuario / Dueño del Tenant.
    *   **Quiero** que el bot detecte periodos de silencio prolongado para intentar retomar la charla o colgar si no hay respuesta.
    *   **CA (Flujo de Silencio):** 
        *   **Fase 1 (Re-engagement):** Al alcanzar el `user_idle_timeout` (ej. 15s), el bot debe inyectar un mensaje de voz proactivo (ej. "¿Sigues ahí?").
        *   **Fase 2 (Cierre Forzoso):** Si tras el mensaje de la Fase 1 pasan 10s adicionales sin audio del usuario, el bot debe ejecutar un "Cierre Clean" avisando al usuario ("Parece que tenemos problemas de conexión, hasta luego") y notificando a Laravel para liberar el worker.
        *   **CA3:** Registro del motivo de fin de llamada como `TIMEOUT_SILENCE` en el dashboard.

### Épica 6: Post-procesamiento Asíncrono (ARQ Workers)
*   **[ ] HU-6.1: Resumen de Sesión y Extracción de Variables**
    *   **Como** Sistema.
    *   **Quiero** que al finalizar la llamada, un worker offline genere un resumen y extraiga datos (ej. email, nombre).
    *   **CA:** Envío de webhook de retorno a Laravel con el JSON estructurado de la sesión.

---

## Fase 3: Operaciones SaaS Pro y Calidad de Servicio
*Características premium y herramientas de observabilidad.*

### Épica 7: Telefonía Avanzada y Cumplimiento
*   **[ ] HU-7.1: Transferencia SIP (SIP REFER)**
    *   **Como** Cliente B2B.
    *   **Quiero** que el bot transfiera la llamada a un humano si detecta frustración.
    *   **CA:** Comando de transferencia exitoso hacia el PBX externo y cierre de sesión en Pipecat.
*   **[ ] HU-7.2: Grabación de Audio Dual (S3)**
    *   **Como** Supervisor.
    *   **Quiero** descargar el audio de la llamada con tracks separados (Bot/Humano).
    *   **CA:** Almacenamiento cifrado en S3 y enlace temporal en el dashboard.

### Épica 8: Calidad de Servicio (QoS) y Métricas para el Cliente
*Foco: Transparencia y confianza en la IA.*
*   **[ ] HU-8.1: Dashboard de Fluidez y Latencia (Métricas Cliente)**
    *   **Como** Dueño del Tenant.
    *   **Quiero** ver KPIs de "Tiempo de Respuesta" y "Tasa de Interrupción".
    *   **CA:** Gráficas de latencia media por agente y reporte de cumplimiento de SLA de voz (< 1s).
*   **[ ] HU-8.2: Visualizador Waterfall de Latencia (Modo Debug)**
    *   **Como** Desarrollador / Admin.
    *   **Quiero** ver el desglose en ms de cada fase: *STT -> LLM (TTFT) -> TTS*.
    *   **CA:** Gráfica de telemetría detallada por cada turno de palabra en el historial de sesión.
*   **[ ] HU-8.3: Monitor de Eficiencia Coste-Calidad**
    *   **Como** Admin Financiero.
    *   **Quiero** ver el coste estimado de la sesión basado en tokens y minutos de audio.
    *   **CA:** Cálculo preciso basado en precios de proveedores configurados.

---

## Fase 4: Operaciones Enterprise, Guardrails y Omnicanalidad
*Características para despliegues corporativos, banca y alta disponibilidad.*

### Épica 9: Base de Conocimiento (RAG)
*   **[ ] HU-9.1: Ingesta y Consulta de Base de Conocimiento (RAG)**
    *   **Como** Ingeniero de Prompts.
    *   **Quiero** asociar un ID de Knowledge Base a un agente para que pueda buscar respuestas en documentos internos (FAQs, T&C del banco).
    *   **CA:** Implementación de un vector store y un retriever en Pipecat previo al LLM.

### Épica 10: Resiliencia y Alta Disponibilidad
*   **[ ] HU-10.1: Mecanismo de Fallback de Modelos LLM**
    *   **Como** Motor de Pipecat.
    *   **Quiero** detectar caídas o timeouts en el proveedor principal y cambiar automáticamente al fallback (ej. Anthropic a OpenAI).
    *   **CA:** Tolerancia a fallos sin desconectar la llamada del cliente, con logueo de "Fallback Triggereado".

### Épica 11: Seguridad y Cumplimiento Financiero (Guardrails)
*   **[ ] HU-11.1: Implementación de Guardrails Financieros y PII**
    *   **Como** Oficial de Cumplimiento.
    *   **Quiero** que el sistema censure en tiempo real datos sensibles (PII) antes de enviarlos al LLM y bloquee tópicos prohibidos (política, estafas).
    *   **CA:** Uso de un filtro en tiempo real que procese el texto de la llamada de manera confiable.
*   **[ ] HU-11.2: Trabajos de Retención y Borrado de Datos (GDPR)**
    *   **Como** Administrador del Sistema.
    *   **Quiero** un cron job (Celery/Horizon) que elimine audios de S3 y transcripts pasada la ventana de retención (ej. 30 días).
    *   **CA:** Eliminación física confirmada mediante logs de auditoría sin intervención humana.

### Épica 12: Omnicanalidad y Experiencia Auditiva (Audio UX)
*   **[ ] HU-12.1: Soporte para Orquestación Omnicanal (WhatsApp/SIP Trunking)**
    *   **Como** Gestor de Canales.
    *   **Quiero** que el mismo agente cognitivo reciba mensajes de texto/voz por WhatsApp y llamadas entrantes SIP (PSTN).
    *   **CA:** Webhooks bidireccionales integrados en Laravel mapeando el contexto a la misma Worker Pipeline.
*   **[ ] HU-12.2: Efectos de Sonido de Procesamiento (Thinking Audio)**
    *   **Como** Interfaz de Voz.
    *   **Quiero** reproducir un archivo de audio ambiental mientras el LLM experimenta latencia al consumir herramientas de negocio pesadas.
    *   **CA:** Transición fluida del sonido de espera al TTS, silenciando el ambiente métrico automáticamente al empezar a responder.

---

## Fase 5: Estandarización de Plataforma y Arquitectura Core
*Unificación de los diferentes tipos de agentes (MVP, Pipeline, Flow) bajo un mismo estándar arquitectónico.*

### *Nota:* El esquema base unificado ya fue consolidado en `docs/agent_unified_manifest.json`.

### Épica 13: Gestión de Entornos y Despliegues
*   **[ ] HU-13.1: Ciclo de Vida y Estados (Environments)**
    *   **Como** Diseñador de Agentes.
    *   **Quiero** etiquetar manifiestos con su estado (`draft`, `staging`, `production`) y no permitir tráfico real a versiones no publicadas.
    *   **CA:** El orquestador telefónico/web solo iniciará sesiones a agentes con estatus de producción.

### Épica 14: Manejo de Estado e Inyección de Contexto (Memory Strategies)
*   **[ ] HU-14.1: Implementación Lógica de Estrategias de Memoria**
    *   **Como** Arquitecto de IA.
    *   **Quiero** implementar el módulo estandarizado de memoria y estado para preservar la continuidad.
    *   **Implementación Técnica (El 'Cómo'):**
        1. **Contexto de Sesión (Short-Term):** En cada llamada activa, Pipecat utilizará Redis (`hash: session:{uuid}:history`) como buffer rápido. Se aplicará truncamiento FIFO (ventana deslizante) si la charla excede el límite del token del LLM.
        2. **Memoria Persistente (Long-Term):** Si la configuración estipula una estrategia permanente, al detonar el webhook de `on_call_ended`, Laravel encolará un Job de Horizon. Dicho Job utilizará un modelo ligero para extraer "Datos Clave y Acuerdos", almacenando el resumen en PostgreSQL vinculado al `phone_number` o `user_id`.
        3. **Inyección de Variables (Zero-shot Hydration):** Justo antes de empujar el prompt hacia Pipecat, el orquestador Laravel inspeccionará la lista profunda de `"injected_variables"`. Realizará fetch dinámico a recursos bancarios y ejecutará un render de Blade/String (`str_replace`) sustituyendo tags como `{{account_balance}}` por sus valores en tiempo real, garantizando así un prompt 100% pre-fabricado y estático para Python.
    *   **CA:** Retomar el estado de contexto de una llamada pasada dentro de la ventana del TTL configurada.

### Épica 16: Seguridad y Eventos (Events & Secrets)
*   **[ ] HU-16.1: Protocolo Unificado de Webhooks (Lifecycle Hooks)**
    *   **Como** Sistema Externo Integrado.
    *   **Quiero** suscribirme a un bus de eventos estandarizado (`on_session_start`, `on_call_ended`) para todos los canales (Web, SIP, WhatsApp).
    *   **CA:** Despacho de eventos centralizado en Laravel hacia APIs de clientes.
*   **[ ] HU-16.2: Referenciación Segura de Secretos (Vault Key Management)**
    *   **Como** Responsable de Seguridad.
    *   **Quiero** reemplazar los tokens planos en los JSON por referencias seguras (ej. `vault:tenant_123_twilio`) que Laravel resuelva en memoria.
    *   **CA:** Mecanismo seguro de hidratación de credenciales antes de inyectar configuración a Pipecat.

---

## Fase 6: Experiencia B2B, Monetización y Personalización
*Capa comercial, integraciones de cara al cliente y herramientas orientadas a potenciar la adopción SaaS.*

### Épica 17: Gestión y Clonación de Voces (Custom Voices)
*   **[ ] HU-17.1: Motor de Clonación Multi-Proveedor (Voice Cloning Sync)**
    *   **Como** Diseñador de Agentes.
    *   **Quiero** subir un clip de audio (ej. el saludo de mi mejor vendedor) para clonar su voz y usarla como TTS en mis bots.
    *   **Implementación Lógica Multi-Proveedor (El 'Cómo'):**
        1. **Módulo de Estrategia (Strategy Pattern):** Se implementará un `VoiceCloningManager` en Laravel que consuma una interfaz base `VoiceProviderContract`. Esto permitirá aislar la lógica nativa para conectarse a ElevenLabs, Cartesia, PlayHT, etc.
        2. **Sync Asíncrono Concurrent:** Cuando un usuario de un Tenant sube su MP3 (`POST /api/voices/clone`), Laravel disparará un Job que realiza el API Request *en paralelo* a todos los proveedores configurados/habilitados para entrenar la voz en cada uno de ellos simultáneamente.
        3. **Vendor Mapping (Tabla `tenant_custom_voices`):** Se creará un único registro lógico de voz en la BD para el cliente (ej. `"name": "Voz de Ventas Juan"`). Este registro contendrá una columna JSON `vendor_ids` que guarda el identificador resultante devuelto por cada proveedor:
           `{"elevenlabs": "ID_Xi8J...", "cartesia": "ID_cart_99...", "playht": "ID_play_01..."}`.
        4. **Resolución en Runtime:** Al momento de compilar el manifiesto final para Pipecat, si el agente tiene seteado `"tts_provider": "cartesia"` y usar la voz personalizada "Voz de Ventas Juan", el Core de Laravel buscará en la base de datos el hash map y le inyectará a la pipeline en Python exclusivamente el ID `ID_cart_99...`. Pipecat no sabrá nada del mapeo, solo ejecutará.
    *   **CA:** Un usuario debe poder subir un solo archivo de audio y posteriormente intercambiar el motor de TTS de su agente (de ElevenLabs a Cartesia) sin perder la identidad de la voz o tener que volver a subir el archivo.
