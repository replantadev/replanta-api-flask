# Configuración de Gunicorn para Render.com
# Aumenta timeout para llamadas largas a OpenAI

bind = "0.0.0.0:10000"
workers = 1
worker_class = "sync"
worker_connections = 1000

# Timeout aumentado para OpenAI (GPT-4 puede tardar hasta 2 minutos)
timeout = 120
keepalive = 10

# Logging
loglevel = "debug"
accesslog = "-"
errorlog = "-"

# Reiniciar workers que consuman mucha memoria
max_requests = 100
max_requests_jitter = 10