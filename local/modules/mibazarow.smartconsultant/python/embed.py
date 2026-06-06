#!/usr/bin/env python3
"""
Пакетная индексация товаров (CLI, раз в сутки).

Читает JSON из stdin: [{"id": 123, "text": "Газонокосилка Bosch ARM 34"}, ...]
Пишет JSON в stdout: [{"id": 123, "hash": "abc...", "embedding": [0.023, -0.451, ...]}, ...]

-Модель: intfloat/multilingual-e5-large (1024-dim, semantic retrieval)

Использование:
    echo '[{"id":1,"text":"Товар"}]' | venv/bin/python embed.py
"""

import sys
import json
import os
import hashlib
from sentence_transformers import SentenceTransformer

MODEL_NAME = 'intfloat/multilingual-e5-large'
MODEL_PATH = os.path.join(os.path.dirname(__file__), 'model')


def main():
    # Читаем весь stdin
    raw = sys.stdin.read()
    if not raw.strip():
        print(json.dumps([]))
        return

    items = json.loads(raw)

    if not items:
        print(json.dumps([]))
        return

    # Загружаем модель (делается один раз за запуск)
    # Сначала локальная копия, если нет — HuggingFace
    if os.path.isdir(MODEL_PATH) and os.path.isfile(os.path.join(MODEL_PATH, 'model.safetensors')):
        model = SentenceTransformer(MODEL_PATH)
    else:
        model = SentenceTransformer(MODEL_NAME)

    # Извлекаем тексты с префиксом "passage: " (требование e5)
    texts = []
    for item in items:
        text = item.get('text', '')
        texts.append('passage: ' + text[:1024])

    # Векторизуем все тексты разом (batch)
    embeddings = model.encode(
        texts,
        normalize_embeddings=True,
        show_progress_bar=False
    )

    # Формируем результат
    result = []
    for item, embedding in zip(items, embeddings):
        text = item.get('text', '')
        result.append({
            'id': item['id'],
            'hash': hashlib.md5(text.encode('utf-8')).hexdigest(),
            'embedding': embedding.tolist()  # float32 → list для JSON
        })

    print(json.dumps(result, ensure_ascii=False))


if __name__ == '__main__':
    main()
