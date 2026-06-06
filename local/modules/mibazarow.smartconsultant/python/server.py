#!/usr/bin/env python3
"""
HTTP-сервис для поисковых запросов (модель всегда в памяти).

Модель: intfloat/multilingual-e5-large (1024-dim, semantic retrieval)

Эндпоинты:
    POST /embed  {"text": "..."}  → {"embedding": [...], "time_ms": 15.0}
    GET  /health                   → {"status": "ok", "model": "multilingual-e5-large"}

Запуск:
    venv/bin/python server.py      # слушает 127.0.0.1:9876

Systemd-сервис:
    systemctl enable --now mib-smartconsultant
"""

import time
import sys
import os
import hashlib
from sentence_transformers import SentenceTransformer
from flask import Flask, request, jsonify

app = Flask(__name__)

MODEL_NAME = 'intfloat/multilingual-e5-large'
MODEL_PATH = os.path.join(os.path.dirname(__file__), 'model')

# Модель загружается ОДИН раз при старте и висит в памяти
# Сначала пробуем локальную копию (включена в дистрибутив модуля),
# если нет — качаем из HuggingFace.
print(f'Загрузка модели {MODEL_NAME}...', file=sys.stderr)
if os.path.isdir(MODEL_PATH) and os.path.isfile(os.path.join(MODEL_PATH, 'model.safetensors')):
    model = SentenceTransformer(MODEL_PATH)
    print(f'Модель загружена из {MODEL_PATH}', file=sys.stderr)
else:
    model = SentenceTransformer(MODEL_NAME)
    print(f'Модель загружена из HuggingFace', file=sys.stderr)


@app.route('/embed', methods=['POST'])
def embed():
    t0 = time.time()
    data = request.get_json(force=True)

    if not data or 'text' not in data:
        return jsonify({'error': 'missing "text" field'}), 400

    text = data['text']

    # Префикс "query: " обязателен для multilingual-e5 при поиске
    text = 'query: ' + text[:1024]

    # Векторизуем
    embedding = model.encode(
        text,
        normalize_embeddings=True,
        show_progress_bar=False
    )

    elapsed_ms = round((time.time() - t0) * 1000, 1)

    return jsonify({
        'embedding': embedding.tolist(),
        'time_ms': elapsed_ms,
    })


@app.route('/embed-batch', methods=['POST'])
def embed_batch():
    """
    Пакетная векторизация для индексации.
    Принимает: {"items": [{"id": 1, "text": "..."}, ...]}
    Возвращает: {"results": [{"id": 1, "hash": "...", "embedding": [...]}, ...]}
    Модель уже в памяти — не перезагружается.
    """
    t0 = time.time()
    data = request.get_json(force=True)

    if not data or 'items' not in data:
        return jsonify({'error': 'missing "items" field'}), 400

    items = data['items']
    if not items:
        return jsonify({'results': []})

    # Извлекаем тексты с префиксом "passage: " (требование e5 для индексации)
    texts = []
    for item in items:
        text = item.get('text', '')
        texts.append('passage: ' + text[:1024])

    # Векторизуем все тексты разом
    embeddings = model.encode(
        texts,
        normalize_embeddings=True,
        show_progress_bar=False
    )

    # Формируем результат
    results = []
    for item, embedding in zip(items, embeddings):
        text = item.get('text', '')
        results.append({
            'id': item['id'],
            'hash': hashlib.md5(text.encode('utf-8')).hexdigest(),
            'embedding': embedding.tolist(),
        })

    elapsed_ms = round((time.time() - t0) * 1000, 1)

    return jsonify({
        'results': results,
        'time_ms': elapsed_ms,
        'count': len(results),
    })


@app.route('/health', methods=['GET'])
def health():
    return jsonify({
        'status': 'ok',
        'model': MODEL_NAME,
    })


if __name__ == '__main__':
    import os
    port = int(os.environ.get('PORT', 9876))
    host = os.environ.get('HOST', '127.0.0.1')
    app.run(host=host, port=port)
