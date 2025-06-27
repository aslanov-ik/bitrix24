При проверке системы видим ошибки в базе данных:

```log
Кодировка поля "SITE_ID" таблицы "b_abtest" (utf8mb4) отличается от кодировки базы (utf8mb3)
Кодировка поля "ACTIVE" таблицы "b_abtest" (utf8mb4) отличается от кодировки базы (utf8mb3)
Кодировка поля "ENABLED" таблицы "b_abtest" (utf8mb4) отличается от кодировки базы (utf8mb3)
Кодировка поля "NAME" таблицы "b_abtest" (utf8mb4) отличается от кодировки базы (utf8mb3)
Кодировка поля "DESCR" таблицы "b_abtest" (utf8mb4) отличается от кодировки базы (utf8mb3)
Кодировка поля "TEST_DATA" таблицы "b_abtest" (utf8mb4) отличается от кодировки базы (utf8mb3)
Кодировка таблицы "b_translate_phrase_fts_en" (utf8mb4) отличается от кодировки базы (utf8mb3)
...
```

Для решения проблем с кодировками, необходимо выполнить SQL запрос, который ответом пришлёт готовый запрос на исправление кодировки:

```sql
SELECT CONCAT('ALTER TABLE `', t.`TABLE_SCHEMA`, '`.`', t.`TABLE_NAME`, '` CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci;') as sqlcode
    FROM `information_schema`.`TABLES` t
    WHERE 1
    AND t.`TABLE_SCHEMA` = 'site_manager'
    AND t.`TABLE_COLLATION` = 'utf8mb4_unicode_ci'
    ORDER BY 1
```

Полученный ответ:

```sql
ALTER TABLE `site_manager`.`b_clouds_size_queue` CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci;
ALTER TABLE `site_manager`.`b_crm_ai_queue_buffer` CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci;
ALTER TABLE `site_manager`.`b_crm_repeat_sale_job` CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci;
ALTER TABLE `site_manager`.`b_crm_repeat_sale_log` CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci;
```

Обратить внимание на кодировки и название таблиц. Если всё нормально, то выполняем полученный выше запрос - готово.