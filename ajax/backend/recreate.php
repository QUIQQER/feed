<?php

/**
 * Recreate a feed
 *
 * @param int $feedId
 * @return void
 */

QUI::getAjax()->registerFunction(
    'package_quiqqer_feed_ajax_backend_recreate',
    function ($feedId) {
        $FeedManager = new QUI\Feed\Manager();
        $Feed = $FeedManager->getFeed((int)$feedId);

        $FeedManager->deleteFeedOutputCache($Feed);

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'quiqqer/feed',
                'message.ajax.backend.recreate.success',
                [
                    'feedId' => $Feed->getId()
                ]
            )
        );
    },
    ['feedId'],
    'Permission::checkAdminUser'
);
