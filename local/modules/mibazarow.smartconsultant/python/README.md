# Python-окружение для mibazarow.smartconsultant

## Установка (только Linux)

```bash
cd /path/to/local/modules/mibazarow.smartconsultant/python

# 1. Создать виртуальное окружение
python3 -m venv venv

# 2. Установить torch (CPU-версия, ~800MB)
venv/bin/pip install torch --index-url https://download.pytorch.org/whl/cpu

# 3. Установить остальные зависимости
venv/bin/pip install -r requirements.txt

# 4. Прогреть модель - загружает multilingual-e5-base из HuggingFace (~1.1 GB)
#    Модель кэшируется и при следующих запусках грузится мгновенно
venv/bin/python -c "from sentence_transformers import SentenceTransformer; SentenceTransformer('intfloat/multilingual-e5-base')"
```

## Проверка

```bash
# Проверить, что модель работает
echo '[{"id":1,"text":"Тестовый товар"}]' | venv/bin/python embed.py

# Проверить HTTP-сервис
venv/bin/python server.py &
curl -X POST http://127.0.0.1:9876/embed -H 'Content-Type: application/json' -d '{"text":"тест"}'
```

## Системные требования

- Python 3.10+
- Доступ к HuggingFace/PyPI (для первого скачивания модели)
- ~3GB свободного диска (torch ~800MB + модель ~1.1GB + venv)

## Файлы

- `embed.py` - CLI для пакетной индексации (вызывается агентом раз в сутки)
- `server.py` - HTTP-сервис для поисковых запросов (всегда в памяти)
- `requirements.txt` - зависимости
- `venv/` - виртуальное окружение (не в git, создаётся на сервере)
