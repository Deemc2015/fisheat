<?php
declare(strict_types=1);

/**
 * Пайплайн индексации: собрать тексты → эмбеддинги → сохранить в БД.
 * Вызывается агентом раз в сутки.
 */

namespace Mibazarow\Smartconsultant\Index;

use Mibazarow\Smartconsultant\Embedding\Engine;
use Mibazarow\Smartconsultant\Embedding\Repository;
use Bitrix\Main\Config\Option;

class Pipeline
{
    const MODULE_ID = 'mibazarow.smartconsultant';

    /**
     * Запустить полный цикл индексации.
     *
     * @throws \RuntimeException
     */
    public function run(): void
    {
        // 1. Получить ID инфоблока из настроек модуля
        $iblockId = (int)Option::get(self::MODULE_ID, 'CATALOG_IBLOCK_ID', '');
        if ($iblockId <= 0) {
            throw new \RuntimeException(
                'Не указан инфоблок каталога. Выберите инфоблок в настройках модуля.'
            );
        }

        $iblockIds = [$iblockId];

        // 2. Извлечь тексты из b_search_content
        echo "  [1/4] Extracting texts...\n";
        flush();
        $items = SourceText::extractAll($iblockIds);
        echo "  [1/4] Found " . count($items) . " items\n";
        flush();

        $repo = new Repository();

        if (empty($items)) {
            $repo->deleteOrphaned();
            echo "  No items to index.\n";
            flush();
            return;
        }

        // 3. Получить существующие хеши (для проверки изменений)
        echo "  [2/4] Checking hashes...\n";
        flush();
        $existingHashes = $repo->getHashIndex();

        // 4. Подготовить данные для Python: только изменённые или новые
        $toEmbed = [];
        foreach ($items as $item) {
            $textHash = md5($item['text']);
            if (!isset($existingHashes[$item['id']]) || $existingHashes[$item['id']] !== $textHash) {
                $toEmbed[] = [
                    'id' => $item['id'],
                    'text' => $item['text'],
                ];
            }
        }

        echo "  [2/4] " . count($toEmbed) . " items to embed (changed/new)\n";
        flush();

        // 5. Если есть что индексировать — вызываем Python и сохраняем почанково
        if (!empty($toEmbed)) {
            echo "  [3/4] Embedding " . count($toEmbed) . " items via HTTP...\n";
            flush();

            $totalSaved = 0;
            Engine::embedBatch($toEmbed, function(array $chunkResults) use ($repo, &$totalSaved) {
                $repo->saveBatch($chunkResults);
                $totalSaved += count($chunkResults);
                echo "    Saved " . count($chunkResults) . " items to DB (total: {$totalSaved})\n";
                flush();
            });

            echo "  [3/4] Got and saved {$totalSaved} vectors\n";
            flush();
        }

        // 7. Удаляем осиротевшие записи (товары удалены из каталога)
        $repo->deleteOrphaned();
    }
}
