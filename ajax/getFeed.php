<?php

/**
 * This file contains package_quiqqer_feed_ajax_getFeed
 */

/**
 * Returns the feed list
 *
 * @param integer $feedId - ID of the Feed
 */

QUI::getAjax()->registerFunction(
    'package_quiqqer_feed_ajax_getFeed',
    function ($feedId) {
        $FeedManager = new QUI\Feed\Manager();

        return $FeedManager->getFeed($feedId)->getAttributes();
    },
    ['feedId'],
    'Permission::checkAdminUser'
);
