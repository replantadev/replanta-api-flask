# -*- coding: utf-8 -*-

from flask import Flask, request, jsonify
import openai
import os
import requests
import logging
import re
from openai import OpenAI
from flask import jsonify
from werkzeug.exceptions import HTTPException
import time

app = Flask(__name__)

# --- Función de subida a Imgur + validación ---
def subir_a_imgur(dalle_url: str, client_id: str, logger) -> str:
    try:
        img_bytes = requests.get(dalle_url).content
        if not img_bytes:
            raise Exception("No se pudo descargar la imagen DALL·E.")

        response = requests.post(
            "https://api.imgur.com/3/image",
            headers={"Authorization": f"Client-ID {client_id}"},
            files={"image": ("dalle.jpg", img_bytes, "image/jpeg")}
        )

        if response.status_code == 200:
            imgur_url = response.json()["data"]["link"]
            logger.debug(f"Imagen subida a Imgur: {imgur_url}")

            for attempt in range(3):
                check = requests.head(imgur_url)
                if check.status_code == 200:
                    # Forzar extensión si no tiene
                    if not re.search(r'\.(jpg|jpeg|png)$', imgur_url, re.IGNORECASE):
                        imgur_url += ".jpg"
                    return imgur_url
                logger.debug(f"Reintentando acceso a imagen Imgur (intento {attempt+1})")
                time.sleep(1.5)

            logger.warning("Imagen subida a Imgur no accesible. Usando fallback DALL-E.")
            return dalle_url
        else:
            logger.warning(f"Fallo subida Imgur. {response.status_code} - {response.text}")
            return dalle_url

    except Exception as e:
        logger.warning(f"Excepción al subir a Imgur: {e}")
        return dalle_url

# --- Limpieza HTML para Medium ---
def prepare_for_medium(content: str) -> str:
    replacements = {
        r'<strong>(.*?)</strong>': r'<b>\1</b>',
        r'<em>(.*?)</em>': r'<i>\1</i>',
        r'<h[1-6]>(.*?)</h[1-6]>': r'<h3>\1</h3>'
    }
    for pattern, repl in replacements.items():
        content = re.sub(pattern, repl, content)
    return content

# --- Error handler ---
@app.errorhandler(Exception)
def handle_exception(e):
    if isinstance(e, HTTPException):
        return jsonify({
            "error": e.name,
            "message": e.description,
            "code": e.code
        }), e.code
    app.logger.exception("Excepción no capturada")
    return jsonify({
        "error": "Internal Server Error",
        "message": str(e),
        "code": 500
    }), 500

# --- Logging setup ---
logging.basicConfig(
    level=logging.DEBUG,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[
        logging.StreamHandler()
    ],
    force=True
)

# Environment variables (required)
openai.api_key = os.environ.get("OPENAI_API_KEY")
MEDIUM_TOKEN = os.environ.get("MEDIUM_TOKEN")
IMGUR_CLIENT_ID = os.environ.get("IMGUR_CLIENT_ID")
DEVTO_TOKEN = os.environ.get("DEVTO_TOKEN")

# Validate required environment variables
if not all([openai.api_key, MEDIUM_TOKEN, IMGUR_CLIENT_ID, DEVTO_TOKEN]):
    raise ValueError("Missing required environment variables. Please set OPENAI_API_KEY, MEDIUM_TOKEN, IMGUR_CLIENT_ID, and DEVTO_TOKEN")

client = OpenAI(api_key=openai.api_key)

# --- Ping ---
@app.route('/ping', methods=['GET'])
def ping():
    app.logger.debug("Ping recibido")
    return jsonify({"pong": True})

# --- Endpoint principal ---
@app.route('/replanta-medium', methods=['POST'])
def replanta_medium():
    try:
        app.logger.debug("Entrando al endpoint /replanta-medium")
        app.logger.debug(f"Content-Type: {request.content_type}")
        app.logger.debug(f"Request data length: {len(request.data)}")
        
        # Intentar parsear JSON con manejo de errores mejorado
        try:
            data = request.get_json(force=True)
            app.logger.debug(f"JSON parseado exitosamente: {list(data.keys()) if data else 'None'}")
        except Exception as json_error:
            app.logger.error(f"Error parseando JSON: {json_error}")
            app.logger.error(f"Raw data: {request.data[:500]}")  # Primeros 500 chars
            return jsonify({
                "error": "JSON inválido",
                "message": str(json_error),
                "received_data": request.data.decode('utf-8')[:200] if request.data else 'empty'
            }), 400

        title = data.get('title')
        url = data.get('url')
        content = data.get('content')
        excerpt = data.get('excerpt', '')
        tags = data.get('tags', [])
        image = data.get('image', '')
        publish = data.get('publish', False)

        if not all([title, url, content]):
            app.logger.error(f"Faltan campos: title={bool(title)}, url={bool(url)}, content={bool(content)}")
            return jsonify({"error": "Faltan campos requeridos: title, url, content"}), 400

        if image.endswith(".webp"):
            image = re.sub(r'(-\d+x\d+)?\.webp$', '-1024x1024.jpg', image)

        if not image:
            dalle_prompt = f"cinematic concept art representing: {title}, technology, internet, digital world"
            try:
                app.logger.debug("Generando imagen con DALL-E")
                image_response = client.images.generate(
                    model="dall-e-3",
                    prompt=dalle_prompt,
                    n=1,
                    size="1024x1024"
                )
                dalle_url = image_response.data[0].url
                app.logger.debug(f"Imagen DALL-E generada: {dalle_url}")
                dalle_image_bytes = requests.get(dalle_url).content
                if not dalle_image_bytes:
                    raise Exception("La imagen DALL·E no pudo descargarse.")
                image = subir_a_imgur(dalle_url, IMGUR_CLIENT_ID, app.logger)
            except Exception as e:
                app.logger.warning(f"Error generando o subiendo imagen IA: {e}")
                image = ""

        prompt = f'''
Eres un redactor experto en tecnología, con experiencia en SEO editorial y escritura para plataformas como Medium. 
Tu tarea es reescribir el artículo siguiente con un enfoque original, profesional y humano.

Debes cumplir los siguientes puntos:

1. Genera un <strong>nuevo título atractivo</strong> y diferente al del blog original.
2. Reescribe el contenido en un estilo fluido y natural, de desarrollador web, sin sonar automatizado.
3. Introduce hasta <strong>dos fuentes externas útiles</strong> (Wired, TechCrunch, HackerNews, MIT Review, etc. o las que consideres de alto PA), con 
   <strong>enlaces reales en formato HTML</strong> y usando <em>anchor texts naturales y variados</em> (no repitas "artículo" o "aquí").
4. Menciona el artículo fuente como una referencia adicional, usando <a href="{url}">este enlace</a> con un anchor text 
   descriptivo y largo si es posible.
5. Evita comenzar el artículo con la fuente original. Úsala en el cuerpo o al final.
6. Termina con una reflexión genuina, genial, o recomendación técnica.

IMPORTANTE:
- No incluyas más de 2 fuentes externas.
- Solo usa formato HTML para los enlaces.
- No uses frases como "según Replanta".

Formato esperado:

Título: [Título generado]
[Artículo reescrito]
'''

        response = client.chat.completions.create(
            model="gpt-4",
            messages=[{"role": "user", "content": prompt}],
            temperature=0.7
        )
        output_text = response.choices[0].message.content

        lines = output_text.splitlines()
        if lines and lines[0].lower().startswith("título:"):
            generated_title = re.sub(r'<[^>]+>', '', lines[0].split(":", 1)[1]).strip()
            ai_output = "\n".join(lines[1:]).strip()
        else:
            generated_title = title
            ai_output = output_text.strip()

        generated_title = re.sub(r'^\*+|\*+$', '', generated_title).strip()

        # Añadir imagen
        if image and not re.search(r'\.(jpg|jpeg|png)$', image, re.IGNORECASE):
            image += ".jpg"

        # ✅ Versión compatible con Medium:
        image_html = f'''
        <figure>
            <img src="{image}" alt="{generated_title}">
            <figcaption>{generated_title}</figcaption>
        </figure>
        '''

        # Reemplazar saltos de línea solo donde sea texto (no rodeando tags HTML)
        formatted_output = "\n".join([
            line if line.startswith("<") else f"<p>{line.strip()}</p>"
            for line in ai_output.splitlines()
        ])

        ai_output = image_html + formatted_output


        # Limpieza final - reemplazar saltos de línea por <br>
        ai_output = prepare_for_medium(ai_output)
        ai_output = ai_output.replace('\n', '<br>')

       

        if generated_title.lower().strip() == title.lower().strip():
            return jsonify({"error": "El título generado es igual al original. Reintenta."}), 422

        if not re.search(r'<a\s+href="https?://[^"]+">', ai_output):
            return jsonify({"error": "El contenido generado no contiene enlaces válidos. Reintenta."}), 422

        headers = {"Authorization": f"Bearer {MEDIUM_TOKEN}"}
        user_info = requests.get("https://api.medium.com/v1/me", headers=headers)
        if user_info.status_code != 200:
            return jsonify({"error": "Error autenticando con Medium", "details": user_info.text}), 403

        user_id = user_info.json()["data"]["id"]

        medium_payload = {
            "title": generated_title,
            "contentFormat": "html",
            "content": ai_output,
            "tags": tags[:5],
            "publishStatus": "public" if publish else "draft"
        }

        medium_response = requests.post(
            f"https://api.medium.com/v1/users/{user_id}/posts",
            headers={**headers, "Content-Type": "application/json"},
            json=medium_payload
        )

        if medium_response.status_code != 201:
            try:
                medium_error = medium_response.json()
            except Exception:
                medium_error = {"raw_response": medium_response.text}
            app.logger.error(f"Medium error: {medium_error}")
            return jsonify({"error": "Error al publicar en Medium", "details": medium_error}), 500

        medium_data = medium_response.json()['data']

        return jsonify({
            "titulo": generated_title,
            "contenido": ai_output,
            "medium_url": medium_data['url'],
            "resumen": excerpt,
            "tags": tags,
            "categoria": data.get('categories', []),
            "ai_image": image
        })

    except Exception as e:
        app.logger.exception("Excepción en el proceso de publicación")
        return jsonify({
            "error": "Excepción en el servidor",
            "message": str(e)
        }), 500

@app.route('/replanta-devto', methods=['POST'])
def replanta_devto():
    try:
        app.logger.debug("Generando y publicando en Dev.to")
        app.logger.debug(f"Content-Type: {request.content_type}")
        
        try:
            data = request.get_json(force=True)
            app.logger.debug(f"JSON parseado exitosamente: {list(data.keys()) if data else 'None'}")
        except Exception as json_error:
            app.logger.error(f"Error parseando JSON: {json_error}")
            app.logger.error(f"Raw data: {request.data[:500]}")
            return jsonify({
                "error": "JSON inválido",
                "message": str(json_error),
                "received_data": request.data.decode('utf-8')[:200] if request.data else 'empty'
            }), 400

        title = data.get('title')
        url = data.get('url')
        content = data.get('content')
        tags = data.get('tags', [])
        canonical_url = data.get('canonical_url', '')
        publish = data.get('publish', False)

        if not all([title, url, content]):
            return jsonify({"error": "Faltan campos requeridos"}), 400

        prompt = f"""
Eres un redactor experto en tecnología para publicaciones profesionales como Dev.to y Github.

Tu tarea es reflexionar sobre el tópico principal del artículo a continuación con las siguientes pautas:

1. Genera un título completamente nuevo, creativo y más atractivo, **distinto del original**.
2. Usa un estilo editorial, técnico y humano, orientado a lectores avanzados o profesionales del sector.
3. Agrega hasta **dos enlaces externos útiles** en formato HTML (Wired, TechCrunch, MIT Tech Review, etc.), usando **anchor text largo y natural**.
4. Introduce un enlace de referencia al artículo fuente usando <a href="{url}">este enlace</a> con un anchor natural (no pongas "aquí").
5. No uses frases como "según Replanta". La idea es que consideres a Replanta (la funete del artículo) como un **referente** más.
6. Finaliza con una conclusión/reflexión convincente.

Formato esperado:

Título: [Título generado]
[Artículo reescrito con HTML]
"""


        response = client.chat.completions.create(
            model="gpt-4",
            messages=[{"role": "user", "content": prompt}],
            temperature=0.7
        )

        ai_output = response.choices[0].message.content

        # Extraer título y markdown
        lines = ai_output.splitlines()
        if lines and lines[0].lower().startswith("título:"):
            generated_title = lines[0].split(":", 1)[1].strip()
            markdown_output = "\n".join(lines[1:]).strip()
        else:
            generated_title = title
            markdown_output = ai_output.strip()

        # Publicar como draft
        headers = {
            "Content-Type": "application/json",
            "api-key": DEVTO_TOKEN
        }

        payload = {
            "article": {
                "title": generated_title,
                "published": publish,
                "body_markdown": markdown_output,
                "tags": tags[:4],
                "canonical_url": canonical_url
            }
        }

        response = requests.post("https://dev.to/api/articles", headers=headers, json=payload)

        if response.status_code not in [200, 201]:
            return jsonify({"error": "Error al publicar en Dev.to", "details": response.text}), 500

        devto_url = response.json()["url"]

        return jsonify({
            "devto_url": devto_url,
            "titulo": generated_title,
            "resumen": data.get("excerpt", ""),
            "tags": tags,
            "modo": "draft" if not publish else "publicado"
        })

    except Exception as e:
        app.logger.exception("Error al publicar en Dev.to")
        return jsonify({
            "error": "Excepción en el servidor",
            "message": str(e)
        }), 500


# Configuración para cPanel/Passenger WSGI
if __name__ == '__main__':
    # Para desarrollo local
    app.run(debug=True, host='0.0.0.0', port=5000)

# Para Passenger WSGI, la aplicación se expone directamente
# No se necesita configuración adicional
