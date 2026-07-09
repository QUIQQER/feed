<?php

namespace QUI\Feed\Bricks\Controls;

use QUI;
use QUI\Exception;

class FeedList extends QUI\Control
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->setAttributes([
            'title' => '',
            'text' => '',
            'class' => 'qui-feeds-brick-FeedList',
            'nodeName' => 'div'
        ]);

        $this->addCSSFile(
            dirname(__FILE__) . '/FeedList.css'
        );


        parent::__construct($attributes);
    }

    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();

        if ($this->getAttribute("layout") == "icons") {
            $this->addCSSFile(
                dirname(__FILE__) . '/FeedListIcons.css'
            );
        }

        $Engine->assign([
            'this' => $this,
            'feeds' => $this->getFeeds(),
            'newwindow' => $this->getAttribute("newwindow"),
            'layout' => $this->getAttribute("layout")
        ]);

        $template = dirname(__FILE__) . '/FeedList.html';

        if ($this->getAttribute("layout") == "icons") {
            $template = dirname(__FILE__) . '/FeedListIcons.html';
        }

        return $Engine->fetch($template);
    }

    /**
     * Gets all currently configured feeds
     *
     * @return array<int, array{
     *   feedID: mixed,
     *   name: mixed,
     *   type: mixed,
     *   desc: mixed,
     *   url: string
     * }>
     * @throws Exception
     */
    protected function getFeeds(): array
    {
        $Manager = new QUI\Feed\Manager();
        $configuredFeeds = $Manager->getList();
        $result = [];
        $curProject = QUI::getRewrite()->getProject();

        if (!$curProject) {
            return $result;
        }

        foreach ($configuredFeeds as $feed) {
            $feedID = $feed['id'];
            $name = $feed['feedName'];
            $description = $feed['feedDescription'];
            $project = $feed['project'];
            $language = $feed['lang'];
            $publish = $feed['publish'];

            if (!$publish) {
                continue;
            }

            if ($curProject->getName() != $project) {
                continue;
            }

            if ($curProject->getLang() != $language) {
                continue;
            }

            $Feed = $Manager->getFeed((int)$feedID);
            $FeedType = $Feed->getFeedType();

            if (empty($FeedType->getAttribute('publishable'))) {
                continue;
            }

            $result[] = [
                "feedID" => $feedID,
                "name" => $name,
                "type" => $FeedType->getAttribute('title'),
                "desc" => $description,
                "url" => $Feed->getUrl()
            ];
        }

        if ($this->getAttribute("limit") > 0) {
            $result = array_slice($result, 0, $this->getAttribute("limit"));
        }

        return $result;
    }
}
