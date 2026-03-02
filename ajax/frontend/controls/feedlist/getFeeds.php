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

            if ($feedRow['feedtype'] == "googleSitemap") {
                continue;
            }

            $Project = QUI::getProject(
                $feedRow['project'],
                $feedRow['lang']
            );

            $projectHost = $Project->getVHost(true, true);
            $feedUrl = $projectHost . URL_DIR . 'feed=' . $feedRow['id'] . '.xml';

            $feedRow['url'] = $feedUrl;
            $result[] = $feedRow;
        }

        return $result;
    }
);
