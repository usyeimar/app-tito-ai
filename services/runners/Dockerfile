FROM python:3.11-slim

ENV PYTHONUNBUFFERED=1 \
    PYTHONDONTWRITEBYTECODE=1

WORKDIR /app

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    build-essential \
    && rm -rf /var/lib/apt/lists/*

# Copiar configuración de dependencias
COPY pyproject.toml README.md ./

# Instalar dependencias de Python
RUN pip install --no-cache-dir pip setuptools uv && \
    uv pip install --system -e .

# Crear usuario sin privilegios
RUN useradd -m -u 1000 runneruser && \
    chown -R runneruser:runneruser /app

# Copiar el código fuente
COPY --chown=runneruser:runneruser ./app /app/app

USER runneruser

EXPOSE 8000

CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]
