<?php

/**
 * @author Andrei Nikolaev <gromdron@yandex.ru>
 */

namespace Mi\Report\Controllers;

use Bitrix\Blog;
use Bitrix\Iblock;
use Bitrix\Main;
use Bitrix\Pull\Event;
use Bitrix\Socialnetwork;
use Bitrix\Socialnetwork\ComponentHelper;
use CBlogPost;
use CComponentEngine;
use CIBlockElement;
use CIBlockPropertyEnum;
use CSocNetLog;
use Exception;
use Throwable;

/**
 * Класс помощник для выдачи наград пользователю
 */
class GratitudeController
{
    /**
     * Выдача награды пользователю
     * @param string $rewardTitle      Тема сообщения
     * @param string $rewardText       Текст сообщения
     * @param int    $rewardTarget     ID пользователя-получателя награды
     * @param int    $rewardFromUser   ID пользователя-вручителя награды
     * @param string $rewardType       Тип награды
     * @return void
     */
    public function sendGratitudeAction(
        string  $rewardTitle,
        string  $rewardText,
        int     $rewardTarget,
        int     $rewardFromUser,
        string  $rewardType = 'cup'
    ): void {

        global $APPLICATION;
        global $CACHE_MANAGER;

        $arParams = [
            'REWARD' => $rewardType,
            'REWARD_TITLE' => $rewardTitle,
            'REWARD_TEXT' => $rewardText,
            'REWARD_TARGET' => $rewardTarget,
            'REWARD_FROM_USER_ID' => $rewardFromUser,
            'GROUP_ID' => 368,
            'SITE_ID' => SITE_ID,
            "PATH_TO_BLOG" => '/company/personal/user/#user_id#/blog/',
            'PATH_TO_POST' => '/company/personal/user/#user_id#/blog/#post_id#/',];

        try {
            $requiredModules = [
                'socialnetwork',
                'blog',
                'intranet',
                'iblock',
                'pull'
            ];

            foreach ($requiredModules as $module) {
                if (!Main\Loader::IncludeModule($module)) {
                    throw new Exception("Module $module not included");
                }
            }
            unset($requiredModules);
            unset($module);

            /**
             * Ищем благодарность, которую хотим выдать
             */
            $gratIblock = Iblock\IblockTable::getRow([
                'select' => ['ID'],
                'filter' => [
                    '=CODE' => 'honour'
                ]
            ]);

            if (!$gratIblock) {
                throw new Exception("Grat iblock not found");
            }

            $rsIBlockPropertyEnum = CIBlockPropertyEnum::GetList(
                array(
                    "SORT" => "ASC",
                    "XML_ID" => "ASC"
                ),
                [
                    "CODE" => 'GRATITUDE',
                    "IBLOCK_ID" => $gratIblock['ID'],
                    'XML_ID' => $arParams['REWARD'],
                ]
            );

            if (!($arGratFromPOST = $rsIBlockPropertyEnum->Fetch())) {
                throw new Exception("Reward with XML ID: {$arParams['REWARD']} not found ");
            }

            /**
             * Проверяем существование блога для пользователя
             */
            $arBlog = Blog\Item\Blog::getByUser([
                "SITE_ID" => $arParams["SITE_ID"],
                "USER_ID" => $arParams["REWARD_FROM_USER_ID"],
                "USE_SOCNET" => "Y",
                "GROUP_ID" => $arParams['GROUP_ID'],
            ]);

            /**
             * Блога нет (не писал еще сообщений)
             * Нужно сделать
             */
            if (empty($arBlog)) {
                $arBlog = ComponentHelper::createUserBlog([
                    "BLOG_GROUP_ID" => $arParams["GROUP_ID"],
                    "USER_ID" => $arParams["REWARD_TARGET"],
                    "SITE_ID" => $arParams["SITE_ID"],
                    "PATH_TO_BLOG" => $arParams["PATH_TO_BLOG"]
                ]);

                if (!$arBlog) {
                    throw new Exception("Unable to create blog: " . $APPLICATION->LAST_ERROR);
                }
            }

            /**
             * Готовим пост в социальную сеть
             */
            $arFields = [
                "TITLE" => $arParams['REWARD_TITLE'],
                "DETAIL_TEXT" => $arParams['REWARD_TEXT'],
                "DETAIL_TEXT_TYPE" => "text",
                "PUBLISH_STATUS" => BLOG_PUBLISH_STATUS_PUBLISH,
                "URL" => $arBlog["URL"],
                'MICRO' => 'N',
                "PATH" => CComponentEngine::MakePathFromTemplate(
                    htmlspecialcharsBack($arParams["PATH_TO_POST"]),
                    [
                        "post_id" => "#post_id#",
                        "user_id" => $arBlog["OWNER_ID"]
                    ]
                ),
                'PERMS_POST' => [],
                'PERMS_COMMENT' => [],
                'CATEGORY_ID' => '',
                'SOCNET_RIGHTS' => [],
            ];

            $resultFields = [
                'ERROR_MESSAGE' => false,
                'PUBLISH_STATUS' => BLOG_PUBLISH_STATUS_PUBLISH
            ];

            $arFields["SOCNET_RIGHTS"] = ComponentHelper::convertBlogPostPermToDestinationList(
                [
                    'POST_ID' => 0,
                    'PERM' => [
                        'UA' => ['UA']
                    ],
                    'IS_REST' => false,
                    'IS_EXTRANET_USER' => false,
                    'AUTHOR_ID' => $arParams['REWARD_FROM_USER_ID']
                ],
                $resultFields
            );

            if (mb_strlen($resultFields['ERROR_MESSAGE']) > 0) {
                throw new Exception("Socnet perm convert error: " . $resultFields['ERROR_MESSAGE']);
            }
            unset($resultFields);

            $arFields["=DATE_CREATE"] = 'NOW()';
            $arFields["=DATE_PUBLISH"] = 'NOW()';
            $arFields["AUTHOR_ID"] = $arParams['REWARD_FROM_USER_ID'];
            $arFields["BLOG_ID"] = $arBlog["ID"];

            $newID = CBlogPost::Add($arFields);
            if (intval($newID) < 1) {
                throw new Exception("Error when create blog post: " . $APPLICATION->LAST_ERROR);
            }
            $arFields["ID"] = $newID;

            $el = new CIBlockElement();
            $new_grat_element_id = $el->Add(
                array(
                    "IBLOCK_ID" => $gratIblock['ID'],
                    "DATE_ACTIVE_FROM" => ConvertTimeStamp(false, "FULL"),
                    "NAME" => $arGratFromPOST["VALUE"]
                ),
                false,
                false
            );

            if ($new_grat_element_id === false) {
                throw new Exception("Error when grat add: " . $el->LAST_ERROR);
            }

            CIBlockElement::SetPropertyValuesEx(
                $new_grat_element_id,
                $gratIblock['ID'],
                array(
                    "USERS" => [
                        $arParams['REWARD_TARGET']
                    ],
                    "GRATITUDE" => [
                        "VALUE" => $arGratFromPOST["ID"]
                    ]
                )
            );

            if (defined("BX_COMP_MANAGED_CACHE")) {
                $CACHE_MANAGER->clearByTag("BLOG_POST_GRATITUDE_TO_USER_" . $arParams['REWARD_TARGET']);
            }

            CBlogPost::Update($newID, array(
                "DETAIL_TEXT_TYPE" => "text",
                "UF_GRATITUDE" => $new_grat_element_id
            ));


            $arFieldsHave = array(
                "HAS_IMAGES" => 'N',
                "HAS_TAGS" => 'N',
                "HAS_PROPS" => 'Y',
                "HAS_SOCNET_ALL" => 'Y',
            );

            CBlogPost::Update($newID, $arFieldsHave, false);

            $logId = (int)CBlogPost::Notify(
                $arFields,
                $arBlog,
                [
                    "bSoNet" => true,
                    "UserID" => $arParams['REWARD_FROM_USER_ID'],
                    "user_id" => $arParams["REWARD_TARGET"],
                    'SITE_ID' => $arParams['SITE_ID'],
                ]
            );

            if (empty($logId)) {
                $blogPostLiveFeedProvider = new Socialnetwork\Livefeed\BlogPost();

                $logFields = Socialnetwork\LogTable::getRow([
                    'select' => ['ID'],
                    'filter' => [
                        '=EVENT_ID' => $blogPostLiveFeedProvider->getEventId(),
                        '=SOURCE_ID' => $newID
                    ],
                ]);

                if ($logFields) {
                    $logId = $logFields['ID'];
                }
            }

            if (empty($logId)) {
                throw new Exception("Error when blog post notify: " . $APPLICATION->LAST_ERROR);
            }

            $logFields = [
                "EVENT_ID" => Blog\Integration\Socialnetwork\Log::EVENT_ID_POST_GRAT
            ];

            if ($post = Blog\Item\Post::getById($newID)) {
                $logFields["TAG"] = $post->getTags();
            }

            CSocNetLog::Update($logId, $logFields);

            $postUrl = CComponentEngine::MakePathFromTemplate(
                htmlspecialcharsBack($arParams["PATH_TO_POST"]),
                [
                    "post_id" => $newID,
                    "user_id" => $arBlog["OWNER_ID"]
                ]
            );

            BXClearCache(true, ComponentHelper::getBlogPostCacheDir([
                'TYPE' => 'posts_last',
                'SITE_ID' => $arParams["SITE_ID"]
            ]));
            BXClearCache(true, ComponentHelper::getBlogPostCacheDir([
                'TYPE' => 'posts_last_blog',
                'SITE_ID' => $arParams["SITE_ID"]
            ]));

            ComponentHelper::notifyBlogPostCreated([
                'post' => [
                    'ID' => $arFields['ID'],
                    'TITLE' => $arFields["TITLE"],
                    'AUTHOR_ID' => $arParams["REWARD_TARGET"]
                ],
                'siteId' => $arParams['SITE_ID'],
                'postUrl' => $postUrl,
                'socnetRights' => $arFields["SOCNET_RIGHTS"],
                'socnetRightsOld' => [
                    "U" => []
                ],
                'mentionListOld' => [],
                'mentionList' => [],
                'gratData' => [
                    'TYPE' => $arParams['REWARD'],
                    'USERS' => [
                        'U' . $arParams['REWARD_TARGET']
                    ]
                ]
            ]);

            /**
             * Отправляем пуш уведомления о добавлении
             * сообщения в живую ленту
             */
            Event::send();
        } catch (Throwable $e) {
            var_dump($e->getMessage());
        }
    }
}
