<?php

/**
 * This file contains package_quiqqer_feed_ajax_getList
 */

/**
 * Returns the feed list
 *
 * @param string $feedIds - JSON array, array of feed ids
 */

QUI::getAjax()->registerFunction(
    'package_quiqqer_feed_ajax_delete',
    function ($feedIds) {
        $FeedManager = new QUI\Feed\Manager();
        $feedIds = json_decode($feedIds, true);

        foreach ($feedIds as $feedId) {
            $FeedManager->deleteFeed($feedId);
        }
    },
    ['feedIds'],
    'Permission::checkAdminUser'
);
