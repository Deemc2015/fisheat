<?php

declare(strict_types=1);

/**
 * Агент переиндексации — запускается раз в сутки.
 */

namespace Mibazarow\Smartconsultant\Agent;

use Mibazarow\Smartconsultant\Index\Pipeline;

class ReindexAgent
{
    /**
     * Запустить переиндексацию всех товаров.
     * Возвращает своё имя для цепочки агентов (периодический запуск).
     *
     * @return string
     */
    public static function reindex(): string
    {
        try {
            $pipeline = new Pipeline();
            $pipeline->run();
        } catch (\Throwable $e) {
            // Логируем ошибку, но не прерываем цепочку агентов
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'SMARTCONSULTANT_REINDEX',
                'MODULE_ID' => 'mibazarow.smartconsultant',
                'DESCRIPTION' => 'Ошибка индексации: ' . $e->getMessage(),
            ]);
        }

        return __METHOD__ . '();';
    }
}
