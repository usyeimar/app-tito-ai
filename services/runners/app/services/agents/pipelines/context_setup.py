import logging
from datetime import datetime
import jinja2
from pipecat.processors.aggregators.llm_context import LLMContext
from pipecat.processors.aggregators.llm_response_universal import (
    LLMContextAggregatorPair,
    LLMUserAggregatorParams,
    LLMAssistantAggregatorParams,
    LLMAutoContextSummarizationConfig,
)
from pipecat.turns.user_mute import (
    MuteUntilFirstBotCompleteUserMuteStrategy,
    FunctionCallUserMuteStrategy,
    FirstSpeechUserMuteStrategy,
    AlwaysUserMuteStrategy,
)
from pipecat.turns.user_start import VADUserTurnStartStrategy
from pipecat.turns.user_stop import (
    TurnAnalyzerUserTurnStopStrategy,
    SpeechTimeoutUserTurnStopStrategy,
)
from pipecat.turns.user_turn_strategies import UserTurnStrategies
from pipecat.audio.turn.smart_turn.local_smart_turn_v3 import LocalSmartTurnAnalyzerV3
from pipecat.audio.turn.smart_turn.base_smart_turn import SmartTurnParams
from app.schemas.agent import AgentConfig

logger = logging.getLogger(__name__)

def render_bot_prompt(session_id: str, config: AgentConfig) -> str:
    """Procesa el Prompt Template con Jinja2."""
    instructions_base = config.brain.llm.instructions
    if "# Capacidades" in instructions_base:
        instructions_base = instructions_base.replace(
            "# Capacidades",
            "# Capacidades\n0. FINALIZAR: Use la herramienta 'finalizar_llamada' de forma proactiva cuando el usuario se despide o la conversación haya terminado.",
        )

    template_vars = {
        "session_id": session_id,
        "agent_id": config.agent_id,
        "tenant_id": config.tenant_id,
        "now": datetime.now().isoformat(),
    }

    if config.orchestration and config.orchestration.session_context:
        template_vars.update(config.orchestration.session_context)

    try:
        template = jinja2.Template(instructions_base)
        return template.render(**template_vars)
    except Exception as e:
        logger.error(f"❌ Error rendering prompt: {e}")
        return instructions_base

def setup_context(session_id: str, config: AgentConfig, vad_analyzer) -> LLMContextAggregatorPair:
    """Configura el contexto LLM y las estrategias de turno."""
    bot_prompt = render_bot_prompt(session_id, config)
    context = LLMContext([{"role": "system", "content": bot_prompt}])

    # 1. Estrategias de Mute
    mute_strategies = []
    behavior = config.runtime_profiles.behavior
    if behavior and behavior.user_mute_strategies:
        for strategy_name in behavior.user_mute_strategies:
            if strategy_name == "first_speech":
                mute_strategies.append(FirstSpeechUserMuteStrategy())
            elif strategy_name == "function_call":
                mute_strategies.append(FunctionCallUserMuteStrategy())
            elif strategy_name == "always":
                mute_strategies.append(AlwaysUserMuteStrategy())
            elif strategy_name == "until_first_bot_complete":
                mute_strategies.append(MuteUntilFirstBotCompleteUserMuteStrategy())

    # 2. Estrategia de Inicio de Turno
    allow_interruptions = behavior.interruptibility if behavior else True
    turn_start_strategy = VADUserTurnStartStrategy(enable_interruptions=allow_interruptions)

    # 3. Estrategia de Fin de Turno
    if behavior and behavior.turn_detection_strategy == "timeout":
        timeout_secs = behavior.turn_detection_timeout_ms / 1000.0
        turn_stop_strategy = SpeechTimeoutUserTurnStopStrategy(user_speech_timeout=timeout_secs)
    else:
        stop_secs = behavior.smart_turn_stop_secs if behavior else 2.0
        turn_analyzer = LocalSmartTurnAnalyzerV3(params=SmartTurnParams(stop_secs=stop_secs))
        turn_stop_strategy = TurnAnalyzerUserTurnStopStrategy(turn_analyzer=turn_analyzer)

    # 4. Gestión de Contexto (Summarization)
    assistant_params = LLMAssistantAggregatorParams()
    context_config = config.brain.context
    if context_config.enabled and context_config.strategy != "none":
        summarization_prompt = "Resume la conversación de forma muy breve para conservar contexto."
        if context_config.strategy == "truncate":
            summarization_prompt = "Olvida los mensajes antiguos y mantén solo los puntos clave de forma ultra-resumida."

        assistant_params = LLMAssistantAggregatorParams(
            enable_auto_context_summarization=True,
            auto_context_summarization_config=LLMAutoContextSummarizationConfig(
                max_context_tokens=context_config.max_tokens,
                min_messages_after_summary=context_config.min_messages,
                summarization_system_prompt=summarization_prompt,
            ),
        )

    context_aggregator = LLMContextAggregatorPair(
        context,
        user_params=LLMUserAggregatorParams(
            user_mute_strategies=mute_strategies,
            vad_analyzer=vad_analyzer,
            user_turn_strategies=UserTurnStrategies(
                start=[turn_start_strategy], stop=[turn_stop_strategy]
            ),
        ),
        assistant_params=assistant_params,
    )
    
    return context_aggregator
