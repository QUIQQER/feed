<?php

/**
 * Get all feed types installed on this system
 *
 * @return array
 */

QUI::getAjax()->registerFunction(
    'package_quiqqer_feed_ajax_backend_getTypes',
    function () {
        $FeedManager = new QUI\Feed\Manager();
        return $FeedManager->getTypes();
    },
    [],
    'Permission::checkAdminUser'
);
