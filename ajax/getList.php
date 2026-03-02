<?php

/**
 * This file contains package_quiqqer_feed_ajax_getList
 */

/**
 * Returns the feed list
 *
 * @param string $gridParams - grid params
 * @return array
 */

QUI::$Ajax->registerFunction(
    'package_quiqqer_feed_ajax_getList',
    function ($gridParams) {
        $FeedManager = new QUI\Feed\Manager();
        $gridParams = json_decode($gridParams, true);

        $Grid = new QUI\Utils\Grid();
        $result = $FeedManager->getList($gridParams);

        foreach ($result as $k => $row) {
            $FeedType = $FeedManager->getType($row['type_id']);

            $result[$k]['feedtype_title'] = $FeedType->getAttribute('title');
        }

        return $Grid->parseResult($result, $FeedManager->count());
    },
    ['gridParams'],
    'Permission::checkAdminUser'
);
