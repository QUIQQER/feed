<?php

/**
 * Set a feed
 *
 * @param string|boolean $feedId - ID of the Feed
 * @param string $params - JSON params
 * @return array
 * @throws QUI\Exception
 */

QUI::getAjax()->registerFunction(
    'package_quiqqer_feed_ajax_setFeed',
    function ($feedId, $params) {
        $FeedManager = new QUI\Feed\Manager();
        $params = json_decode($params, true);

        if ($feedId) {
            $Feed = $FeedManager->getFeed($feedId);
            $Feed->setAttributes($FeedManager->filterFeedParams($Feed->getTypeId(), $params));
            $Feed->save();
        } else {
            if (empty($params['feedtype'])) {
                throw new QUI\Exception([
                    'quiqqer/feed',
                    'exception.ajax.setFeed.missing_type'
                ]);
            }

            $Feed = $FeedManager->addFeed($params['feedtype'], $params);
        }

        return $Feed->getAttributes();
    },
    ['feedId', 'params'],
    'Permission::checkAdminUser'
);
