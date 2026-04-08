"""
Mapa de seudónimos de idiomas para simplificar la configuración de agentes.

En vez de recordar códigos ISO como "es-CO" o "pt-BR", el usuario puede usar
nombres comunes como "español", "spanish", "portugues-brasil", etc.

Uso:
    from app.services.agents.factory.languages import resolve_language
    lang = resolve_language("español")        # -> Language.ES
    lang = resolve_language("spanish-mexico")  # -> Language.ES_MX
    lang = resolve_language("es-CO")           # -> Language.ES_CO  (códigos ISO siguen funcionando)
"""
from pipecat.transcriptions.language import Language

# Mapeo de seudónimos -> Language enum
# Incluye: nombre en español, nombre en inglés, nombre nativo, variantes comunes
LANGUAGE_ALIASES: dict[str, Language] = {
    # ── Español ──────────────────────────────────────────────────────────
    "español": Language.ES,
    "espanol": Language.ES,
    "spanish": Language.ES,
    "es": Language.ES,
    "español-españa": Language.ES_ES,
    "espanol-espana": Language.ES_ES,
    "spanish-spain": Language.ES_ES,
    "español-mexico": Language.ES_MX,
    "espanol-mexico": Language.ES_MX,
    "spanish-mexico": Language.ES_MX,
    "español-colombia": Language.ES_CO,
    "espanol-colombia": Language.ES_CO,
    "spanish-colombia": Language.ES_CO,
    "español-argentina": Language.ES_AR,
    "espanol-argentina": Language.ES_AR,
    "spanish-argentina": Language.ES_AR,
    "español-chile": Language.ES_CL,
    "espanol-chile": Language.ES_CL,
    "spanish-chile": Language.ES_CL,
    "español-peru": Language.ES_PE,
    "espanol-peru": Language.ES_PE,
    "spanish-peru": Language.ES_PE,
    "español-venezuela": Language.ES_VE,
    "espanol-venezuela": Language.ES_VE,
    "spanish-venezuela": Language.ES_VE,
    "español-ecuador": Language.ES_EC,
    "espanol-ecuador": Language.ES_EC,
    "spanish-ecuador": Language.ES_EC,
    "español-usa": Language.ES_US,
    "espanol-usa": Language.ES_US,
    "spanish-us": Language.ES_US,
    "español-latam": Language.ES_419,
    "espanol-latam": Language.ES_419,
    "spanish-latam": Language.ES_419,
    "latinoamerica": Language.ES_419,

    # ── Inglés ───────────────────────────────────────────────────────────
    "ingles": Language.EN,
    "inglés": Language.EN,
    "english": Language.EN,
    "en": Language.EN,
    "ingles-usa": Language.EN_US,
    "english-us": Language.EN_US,
    "english-usa": Language.EN_US,
    "american": Language.EN_US,
    "ingles-uk": Language.EN_GB,
    "english-uk": Language.EN_GB,
    "british": Language.EN_GB,
    "english-australia": Language.EN_AU,
    "english-canada": Language.EN_CA,
    "english-india": Language.EN_IN,

    # ── Portugués ────────────────────────────────────────────────────────
    "portugues": Language.PT,
    "portugués": Language.PT,
    "portuguese": Language.PT,
    "pt": Language.PT,
    "portugues-brasil": Language.PT_BR,
    "portugués-brasil": Language.PT_BR,
    "portuguese-brazil": Language.PT_BR,
    "brasileiro": Language.PT_BR,
    "portugues-portugal": Language.PT_PT,
    "portuguese-portugal": Language.PT_PT,

    # ── Francés ──────────────────────────────────────────────────────────
    "frances": Language.FR,
    "francés": Language.FR,
    "french": Language.FR,
    "français": Language.FR,
    "fr": Language.FR,
    "frances-francia": Language.FR_FR,
    "french-france": Language.FR_FR,
    "frances-canada": Language.FR_CA,
    "french-canada": Language.FR_CA,
    "quebecois": Language.FR_CA,

    # ── Alemán ───────────────────────────────────────────────────────────
    "aleman": Language.DE,
    "alemán": Language.DE,
    "german": Language.DE,
    "deutsch": Language.DE,
    "de": Language.DE,
    "aleman-alemania": Language.DE_DE,
    "german-germany": Language.DE_DE,
    "aleman-austria": Language.DE_AT,
    "german-austria": Language.DE_AT,
    "aleman-suiza": Language.DE_CH,
    "german-switzerland": Language.DE_CH,

    # ── Italiano ─────────────────────────────────────────────────────────
    "italiano": Language.IT,
    "italian": Language.IT,
    "it": Language.IT,

    # ── Chino ────────────────────────────────────────────────────────────
    "chino": Language.ZH,
    "chinese": Language.ZH,
    "mandarin": Language.ZH_CN,
    "zh": Language.ZH,
    "chino-simplificado": Language.ZH_CN,
    "chinese-simplified": Language.ZH_CN,
    "chino-tradicional": Language.ZH_TW,
    "chinese-traditional": Language.ZH_TW,
    "cantones": Language.YUE,
    "cantonese": Language.YUE,

    # ── Japonés ──────────────────────────────────────────────────────────
    "japones": Language.JA,
    "japonés": Language.JA,
    "japanese": Language.JA,
    "ja": Language.JA,

    # ── Coreano ──────────────────────────────────────────────────────────
    "coreano": Language.KO,
    "korean": Language.KO,
    "ko": Language.KO,

    # ── Ruso ─────────────────────────────────────────────────────────────
    "ruso": Language.RU,
    "russian": Language.RU,
    "ru": Language.RU,

    # ── Árabe ────────────────────────────────────────────────────────────
    "arabe": Language.AR,
    "árabe": Language.AR,
    "arabic": Language.AR,
    "ar": Language.AR,

    # ── Hindi ────────────────────────────────────────────────────────────
    "hindi": Language.HI,
    "hi": Language.HI,

    # ── Turco ────────────────────────────────────────────────────────────
    "turco": Language.TR,
    "turkish": Language.TR,
    "tr": Language.TR,

    # ── Polaco ───────────────────────────────────────────────────────────
    "polaco": Language.PL,
    "polish": Language.PL,
    "pl": Language.PL,

    # ── Holandés ─────────────────────────────────────────────────────────
    "holandes": Language.NL,
    "holandés": Language.NL,
    "dutch": Language.NL,
    "nl": Language.NL,

    # ── Sueco ────────────────────────────────────────────────────────────
    "sueco": Language.SV,
    "swedish": Language.SV,
    "sv": Language.SV,

    # ── Danés ────────────────────────────────────────────────────────────
    "danes": Language.DA,
    "danés": Language.DA,
    "danish": Language.DA,
    "da": Language.DA,

    # ── Noruego ──────────────────────────────────────────────────────────
    "noruego": Language.NB,
    "norwegian": Language.NB,
    "nb": Language.NB,

    # ── Finlandés ────────────────────────────────────────────────────────
    "finlandes": Language.FI,
    "finlandés": Language.FI,
    "finnish": Language.FI,
    "fi": Language.FI,

    # ── Griego ───────────────────────────────────────────────────────────
    "griego": Language.EL,
    "greek": Language.EL,
    "el": Language.EL,

    # ── Checo ────────────────────────────────────────────────────────────
    "checo": Language.CS,
    "czech": Language.CS,
    "cs": Language.CS,

    # ── Rumano ───────────────────────────────────────────────────────────
    "rumano": Language.RO,
    "romanian": Language.RO,
    "ro": Language.RO,

    # ── Húngaro ──────────────────────────────────────────────────────────
    "hungaro": Language.HU,
    "húngaro": Language.HU,
    "hungarian": Language.HU,
    "hu": Language.HU,

    # ── Ucraniano ────────────────────────────────────────────────────────
    "ucraniano": Language.UK,
    "ukrainian": Language.UK,
    "uk": Language.UK,

    # ── Indonesio ────────────────────────────────────────────────────────
    "indonesio": Language.ID,
    "indonesian": Language.ID,
    "bahasa": Language.ID,
    "id": Language.ID,

    # ── Malayo ───────────────────────────────────────────────────────────
    "malayo": Language.MS,
    "malay": Language.MS,
    "ms": Language.MS,

    # ── Vietnamita ───────────────────────────────────────────────────────
    "vietnamita": Language.VI,
    "vietnamese": Language.VI,
    "vi": Language.VI,

    # ── Tailandés ────────────────────────────────────────────────────────
    "tailandes": Language.TH,
    "tailandés": Language.TH,
    "thai": Language.TH,
    "th": Language.TH,

    # ── Filipino ─────────────────────────────────────────────────────────
    "filipino": Language.FIL,
    "tagalog": Language.TL,
    "fil": Language.FIL,

    # ── Hebreo ───────────────────────────────────────────────────────────
    "hebreo": Language.HE,
    "hebrew": Language.HE,
    "he": Language.HE,

    # ── Catalán ──────────────────────────────────────────────────────────
    "catalan": Language.CA,
    "catalán": Language.CA,
    "ca": Language.CA,

    # ── Especiales ───────────────────────────────────────────────────────
    "auto": None,
    "multi": None,
    "detect": None,
}


def resolve_language(value: str) -> Language | None:
    """
    Resuelve un string de idioma a un Language enum de Pipecat.

    Acepta:
    - Seudónimos: "español", "spanish", "portugues-brasil", etc.
    - Códigos ISO: "es", "es-CO", "en-US", "pt-BR", etc.
    - Especiales: "auto", "multi", "detect" -> None (detección automática)

    Returns:
        Language enum o None si es detección automática.

    Raises:
        ValueError si el idioma no se reconoce.
    """
    if not value:
        return Language.EN

    normalized = value.strip().lower()

    # 1. Buscar en seudónimos
    if normalized in LANGUAGE_ALIASES:
        return LANGUAGE_ALIASES[normalized]

    # 2. Intentar como código ISO directo contra el enum
    try:
        return Language(normalized)
    except ValueError:
        pass

    # 3. Intentar por nombre del enum (ej: "ES_CO", "EN_US")
    upper = normalized.upper().replace("-", "_")
    try:
        return Language[upper]
    except KeyError:
        pass

    raise ValueError(
        f"Idioma no reconocido: '{value}'. "
        f"Usa un seudónimo (ej: 'español', 'english', 'portugues-brasil') "
        f"o un código ISO (ej: 'es', 'en-US', 'pt-BR')."
    )


def list_supported_languages() -> dict[str, list[str]]:
    """
    Devuelve un diccionario de Language -> lista de seudónimos.
    Útil para documentación y endpoints de ayuda.
    """
    result: dict[str, list[str]] = {}
    for alias, lang in LANGUAGE_ALIASES.items():
        key = lang.value if lang else "auto"
        if key not in result:
            result[key] = []
        result[key].append(alias)
    return result
