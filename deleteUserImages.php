<?php

/**
 * Скрипт удаляет аватарки пользователей и картинки групповых чатов.
 * Полезно для локально развёрнутого проекта, для которого не скачивалась папка upload 
 */

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
define('BX_NO_ACCELERATOR_RESET', true);
define('CHK_EVENT', true);
define('BX_WITH_ON_AFTER_EPILOG', true);
define('SITE_ID', 's1');

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;

Loader::includeModule('crm');

print_r('User avatars:' . deleteUserAvatars() . PHP_EOL);
print_r('Group chat photos:' . deleteGroupChatPhotos());

function deleteUserAvatars(): bool|string
{
    $userTable = UserTable::getList([
            'select' => [
                'ID',
                'PERSONAL_PHOTO',
            ],
            'filter' => [
                '!PERSONAL_PHOTO' => false
            ]
        ]);


    $cUser = new CUser();

    while ($user = $userTable->fetch()) {
        $fields = [
            "PERSONAL_PHOTO" => [
                "old_file" => $user["PERSONAL_PHOTO"],
                "del" => $user["PERSONAL_PHOTO"]
            ]
        ];
        $cUser->Update($user['ID'], $fields);
    }
    return 'success';
}

function deleteGroupChatPhotos(): bool|string
{
    $chatTable = \Bitrix\Im\Model\ChatTable::getList([
        'select' => [
            'ID',
            'AVATAR'
        ],
        'filter' => [
            '!AVATAR' => false
        ]
    ]);

    while ($chat = $chatTable->fetch()) {
        \Bitrix\Im\Model\ChatTable::update($chat['ID'], [
            'fields' => [
                'AVATAR' => null
            ]
        ]);
    }
    return 'success';
}