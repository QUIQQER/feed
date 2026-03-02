<?php

namespace QUI\Feed\Utils;

use SimpleXMLElement;

/**
 * SimpleXMLElement extent
 */
class SimpleXML extends SimpleXMLElement
{
    /**
     * Add an ![CDATA[ ]]> Entry
     *
     * @param string $cdata - CDATA value
     */
    public function addCData(string $cdata): void
    {
        $Node = dom_import_simplexml($this);
        $CData = $Node->ownerDocument?->createCDATASection($cdata);

        if (!$CData) {
            return;
        }

        $Node->appendChild($CData);
    }
}
