<?php

/**
 * @package nxcSitemap
 * @author  Alex Pilyavskiy <spi@nxc.no>
 * @date    2 Aug 2013
 **/

class nxcSitemap {

    private $classFilterList = array();
    private $classFilterType = 1;
    private $mainNodeOnly = false;
    private $priorities = array();
    private $outputType = 'file';
    private $sitemapXml;
    private $cli = false;
    private $additionalUrlParams;
    private $objectState = false;
    private $onlyTranslated = false;


    public function __construct( $outputType, $cli = false ) {
        $ini = eZIni::instance( 'nxc_sitemap.ini' );
        $this->classFilterType = ($ini->hasVariable('Classes', 'ClassFilterType') && $ini->variable('Classes', 'ClassFilterType') == 'exclude') ? 'exclude' : 'include';
        $this->classFilterList = ($ini->hasVariable('Classes', 'ClassFilterArray')) ? $ini->variable('Classes', 'ClassFilterArray') : array();
        $this->mainNodeOnly = ($ini->hasVariable('GeneralSettings', 'MainNodeOnly') && $ini->variable('GeneralSettings', 'MainNodeOnly') == 'true' ) ? true : false;
        $this->priorities = ($ini->hasVariable('GeneralSettings', 'NodeDepthPriority')) ? $ini->variable('GeneralSettings', 'NodeDepthPriority') : self::getDepthPriority();
        $this->outputType = ($outputType == 'file') ? 'file' : 'module';
        $this->cli = $cli;
        $this->additionalUrlParams = ($ini->hasVariable('NodeSettings', 'AdditionalUrlParams')) ? $ini->variableArray('NodeSettings', 'AdditionalUrlParams') : false;
        $this->objectState = ( $ini->hasVariable('GeneralSettings', 'UseObjectStates') && $ini->variable('GeneralSettings', 'UseObjectStates') == 'true' && $ini->hasVariable('GeneralSettings', 'LiveObjectState') && (int)$ini->variable('GeneralSettings', 'LiveObjectState') > 0 ) ? (int)$ini->variable('GeneralSettings', 'LiveObjectState') : false;
        $this->AttributeFilter = ($ini->hasVariable('AttributeFilter', 'AttributeFilter')) ? $ini->variable('AttributeFilter', 'AttributeFilter') : false;
        $this->onlyTranslated = ($ini->hasVariable('GeneralSettings', 'OnlyTranslated')) ? $ini->variable('GeneralSettings', 'OnlyTranslated') : false;
    }

    private function generateSitemap() {
        $tpl = eZTemplate::factory();
        $limit = 100;
        $offset = 0;
        $counter = 0;
        $fetchParams = array(
                    'Depth'           => 0,
                    'Limit'           => $limit,
                    'LoadDataMap'     => false,
                    'ClassFilterType' => $this->classFilterType,
                    'ClassFilterArray'=> $this->classFilterList,
                    'Main_Node_Only'  => $this->mainNodeOnly
        );

        if ($this->onlyTranslated) {
            $locale = eZLocale::instance();
            $fetchParams['Language'] = $locale->TranslationCode;
        }

        if ($this->AttributeFilter) {
            $fetchParams['AttributeFilter'][] = $this->AttributeFilter;
        }

        if ($this->objectState) {
            $fetchParams['AttributeFilter'][] = array( 'state', '=', $this->objectState  );
        }
        // would be great to change string to real XML dom document; need more time :(
        $result = "<?xml version='1.0' encoding='UTF-8'?><urlset xmlns='http://www.sitemaps.org/schemas/sitemap/0.9' xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' xsi:schemaLocation='http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd'>";
        do {
            $nodes = array();
            $fetchParams['Offset'] = $offset;
            $nodes = eZContentObjectTreeNode::subTreeByNodeID( $fetchParams, 1 );
            $offset += $limit;
            foreach( $nodes as $key => $node ) {
                //$tpl->resetVariables();
                $tpl->setVariable('priority', $this->priorities);
                $tpl->setVariable('additional_url_parameters', $this->additionalUrlParams);
                $tpl->setVariable( 'node', $node );
                $result .= $tpl->fetch( 'design:node/view/nxc_sitemap.tpl' );
                $object = $node->attribute( 'object' );
                eZContentObject::clearCache( $object->attribute( 'id' ) );
                $object->resetDataMap();
            }
            if ($this->cli) $this->cli->output('.', false);
        } while (count($nodes));

        $result .= "</urlset>";

        $this->sitemapXml = $result;
    }

    public function output() {
        $this->generateSitemap();
        if ($this->outputType == 'file') {
            eZFile::create('sitemap.xml', './var/storage/', $this->sitemapXml);
        }
        else {
            header("Content-Type: text/xml");
            echo $this->sitemapXml;
            eZExecution::cleanExit();
        }
    }


    private static function getDepthPriority() {
        return array( '1', '0.9', '0.8', '0.7', '0.6', '0.5', '0.4');
    }


}

?>
