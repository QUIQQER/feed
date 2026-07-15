<?php

define('QUIQQER_SYSTEM', true);
define('QUIQQER_AJAX', true);

$exit = function (int $code) {
    $Response = QUI::getGlobalResponse();
    $Response->setStatusCode($code);
    $Response->send();

    exit;
};

if (!isset($_REQUEST['id'])) {
    $exit(400);
}

require_once dirname(__FILE__, 5) . '/header.php';

use QUI\Feed\Manager as FeedManager;

$User = QUI::getUserBySession();

if (!$User->canUseBackend()) {
    $exit(401);
}

$Request = QUI::getRequest();

try {
    $FeedManager = new FeedManager();
    $Feed = $FeedManager->getFeed((int)$_REQUEST['id']);

    if (!$FeedManager->isFeedBuilt($Feed) && !$Feed->getAttribute('directOutput')) {
        $exit(503);
    }

    $fileExt = match ($Feed->getFeedType()->getAttribute('mimeType')) {
        'text/csv' => 'csv',
        default => 'xml',
    };

    $xmlString = $FeedManager->getFeedOutput($Feed);
    $varDir = QUI::getPackage('quiqqer/feed')->getVarDir();
    $tmpFile = $varDir . hash('sha256', microtime()) . '.' . $fileExt;

    file_put_contents($tmpFile, $xmlString);

    QUI\Utils\System\File::send($tmpFile, 0, 'feed_' . $Feed->getId() . '.' . $fileExt);
} catch (\Exception $Exception) {
    QUI\System\Log::addDebug($Exception->getMessage());
    $exit(500);
}
