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

QUI::getAjax()->registerFunction(
    'package_quiqqer_feed_ajax_getList',
    function ($gridParams) {
        $FeedManager = new QUI\Feed\Manager();
        $gridParams = json_decode($gridParams, true);

        $Grid = new QUI\Utils\Grid();
        $result = $FeedManager->getList($gridParams);

        foreach ($result as $k => $row) {
            $Feed = $FeedManager->getFeedForList((int)$row['id']);

            $result[$k]['missingProject'] = $Feed === null;
            $result[$k]['feedtype_title'] = $Feed
                ? $Feed->getFeedType()->getAttribute('title')
                : $row['type_id'];
            $result[$k]['url'] = $Feed ? $Feed->getUrl() : '';
        }

        return $Grid->parseResult($result, $FeedManager->count());
    },
    ['gridParams'],
    'Permission::checkAdminUser'
);
