<?php

use QUI\Feed\Manager;

QUI::getAjax()->registerFunction(
    'package_quiqqer_feed_ajax_frontend_controls_feedlist_getFeeds',
    function () {
        $Manager = new Manager();
        $feedList = $Manager->getList();
        $result = [];

        foreach ($feedList as $feedRow) {
            if ($feedRow['publish'] != "1") {
                continue;
            }

            $Feed = $Manager->getFeed((int)$feedRow['id']);
            $FeedType = $Feed->getFeedType();

            if (empty($FeedType->getAttribute('publishable'))) {
                continue;
            }

            $feedRow['url'] = $Feed->getUrl();
            $result[] = $feedRow;
        }

        return $result;
    }
);
