<?php
header("Access-Control-Allow-Origin: *");

require_once '../config/constants.php';
require_once '../classes/Images/ImageManager.php';

/**
 * Created by PhpStorm.
 * User: stuart.eske
 * Date: 12/11/13
 * Time: 15:36
 *
 * @description This class will extract all entities 
 * of a specific category type, from the php_cms
 * database. the name of the file should be named
 * after the category type with the database. The
 * system is case sensitive!
 * 
 * @version 1.00.02
 *
 * @copyright    Copyright (C) 2013 CFA Group. All rights reserved.
 * @license      GNU General Public License Version 2 or later.
 * @author       Stuart Eske, <stuart.eske@cfa-group.com>
 */
class CategoryTypeNameHere {

    /**
     * @var string
     */
    private $protocal = 'http://';

    /**
     * @var
     */
    private $contentType;

    /**
     * @var
     */
    public $sectionArray;

    /**
     * @var
     */
    private $imageManager;


    /**
     *
     */
    function __construct() {
        // Get the content type from the script filename
        $this->setContentType(
            str_replace(".php", "", basename($_SERVER["SCRIPT_NAME"]))
        );

        // Init the project array
        $this->setSectionArray(array());

        // Setup the image manager
        $this->setImageManager( new Images_ImageManager() );

        // Get the url protocol
        $this->setProtocal(stripos($_SERVER['SERVER_PROTOCOL'],'https') === true ? 'https://' : 'http://');
    }

    /**
     * queryContentItemsAndTopLevelItemDateFields()
     *
     * This function will return the MySql result for
     * the content items and the items top level data.
     * The result set need parsing with mysql_fetch_array()
     * function.
     *
     * SQL: SELECT tas.SectionId AS SID, tcp.ID AS CID, tcp.Slug, tcpd.ID AS DID, tcpd.key, tcpd.value
     *      FROM tbladminsections AS tas
     *      LEFT JOIN tblcustompage AS tcp on tas.SectionId = tcp.Section
     *      LEFT JOIN tblcustompagedata AS tcpd on tcp.ID = tcpd.CustomPageID
     *      WHERE tas.SectionName = 'Projects'
     *      AND tcp.Enabled = 1
     *
     * Example Data:
     * |---------------------------------------------------------------------|
     * | SID | CID | Slug  | DID  | key         | value                      |
     * | 66  | 199 | rbli- | 1136 | Title       | RBLI Annual Report         |
     * | 66  | 199 | rbli- | 1136 | Description | RBLI                       |
     * | 66  | 199 | rbli- | 1138 | Image       | 441                        |
     * | 66  | 199 | rbli- | 1139 | Copy        | <ul><li><p>Clean design... |
     * |----------------------------------------------------------------------
     *
     * @param string $contentType
     * @return MySQLResource
     */
    private function queryContentItemsAndTopLevelItemDateFields($contentType = '') {

        // If the content type have been omitted then
        // retrieve it from the class.
        if (empty($contentType)) $contentType = $this->getContentType();

        $sql = "SELECT tas.SectionId AS SID, tcp.ID AS CID, tcp.Slug, tcpd.ID AS DID, tcpd.key, tcpd.value "
            . "FROM tbladminsections AS tas "
            . " LEFT JOIN tblcustompage AS tcp on tas.SectionId = tcp.Section "
            . " LEFT JOIN tblcustompagedata AS tcpd on tcp.ID = tcpd.CustomPageID "
            . "WHERE tas.SectionName = '{$contentType}' "
            . "AND tcp.Enabled = 1 ";

        return QueryDB($sql);
    }

    private function parseContentItemsAndTopLevelFields($queryResult) {
        $sectionArray = $this->getSectionArray();

        while($resultArray = mysql_fetch_array($queryResult)) {
            switch($resultArray[4]) {
                case 'Intro':
                    $textString = $resultArray[5];

                    $sectionArray[$resultArray['Slug']][$resultArray[4]] = $this->parseText($textString);
                    break;
                case 'Copy':
                    $textString = $resultArray[5];

                    $sectionArray[$resultArray['Slug']][$resultArray[4]] = $this->parseText($textString);
                    break;
                case 'Image':
                    $imageIdArray = explode( ',', $resultArray[5] );

                    foreach( $imageIdArray as $key => $imageId ) {
                        $keyValue = '';
                        $imageData = '';

                        if ( is_numeric($imageId) ) {
                            $keyValue = $this->getImageManager()->getImageString(
                                $imageId,
                                'original'
                            );

                            $imageFile = file_get_contents($keyValue);
                            $imageData = 'data:image/png;base64,' . base64_encode($imageFile);

                            $keyValue = $this->getProtocal() . $_SERVER['HTTP_HOST'] . str_replace("..", "", "$keyValue");

                            $sectionArray[$resultArray['Slug']]['Image'][$key]['ImageUrl'] = $this->parseText($keyValue);
                            $sectionArray[$resultArray['Slug']]['Image'][$key][$resultArray[4]] = $this->parseText($imageData);
                        }
                    }
                    break;
                case 'map':
                    $data = file_get_contents($resultArray[5]);
                    $imageEncoding = 'data:image/jpeg;base64,' . base64_encode($data);

                    $sectionArray[$resultArray['Slug']][$resultArray[4]] = $this->parseText($imageEncoding);
                    break;
                default:
                    if (empty($resultArray[5])) $keyValue = 0;
                    else $keyValue = $resultArray[5];

                    $sectionArray[$resultArray['Slug']][$resultArray[4]] = $this->parseText($keyValue);
                    break;
            }
        }

        $this->setSectionArray($sectionArray);
    }

    private function parseAndGetLowerLevelFields($sectionArray) {

        foreach(array_keys($sectionArray) as $custompageSlug) {
            //echo $custompageSlug . '<br />';

            /**
             * SELECT tcp.ID, tcp.Slug, tcpl.*
             * FROM tblcustompage AS tcp
             * JOIN tblcustompagelinks AS tcpl on tcp.ID = tcpl.ParentId
             * WHERE Slug = 'rbli-'
             * |------------------------------------------------------|
             * | Id  | Slug  | linkid | parentid | childid | childkey |
             * | 199 | rbli- | 1776   | 199      | 6       | Services |
             * | 199 | rbli- | 1775   | 199      | 195     | Client   |
             * | 199 | rbli- | 1778   | 199      | 184     | Sectors  |
             * | 199 | rbli- | 1777   | 199      | 190     | Sectors  |
             * |------------------------------------------------------|
             */

            // Get the extra data entities
            $sql = "SELECT tcp.ID, tcp.Slug, tcpl.*
              FROM tblcustompage AS tcp
              JOIN tblcustompagelinks AS tcpl on tcp.ID = tcpl.ParentId
              WHERE Slug = '" . $custompageSlug . "'";

            $sectionResult = QueryDB($sql);
            while($sectionResultArray = mysql_fetch_array($sectionResult)) {

                //echo '&nbsp;&nbsp;&nbsp;&nbsp;' . $sectionResultArray['childid'] . ' ' . $sectionResultArray['childkey'] . '<br />' ;

                /**
                 * SELECT * FROM `tblcustompagedata` WHERE CustomPageID = 111
                 * |-----------------------------------------------|
                 * | ID  | CustomPageId | Key     | Value          |
                 * | 690 | 111          | Title   | Media Planning |
                 * | 691 | 111          | Image   | 402,497        |
                 * | 692 | 111          | Online  |                |
                 * | 693 | 111          | Offline | 1              |
                 * | 954 | 111          | Copy    | <p>CFA ...     |
                 * |-----------------------------------------------|
                 */
                $queryDataPropertiesSql = "SELECT * "
                    . "FROM tblcustompagedata "
                    . "WHERE CustomPageID = " . $sectionResultArray['childid'] . " ";

                $queryDataPropertiesResult = QueryDB($queryDataPropertiesSql);

                $additionalDataBlock = array();

                while($dataPropertiesArray = mysql_fetch_array($queryDataPropertiesResult)) {

                    switch($dataPropertiesArray['Key']) {
                        case 'Title':
                            $additionalDataBlock[$dataPropertiesArray['Key']] = $this->parseText($dataPropertiesArray['Value']);
                            break;
                        case 'label':
                            $additionalDataBlock[$dataPropertiesArray['Key']] = $this->parseText($dataPropertiesArray['Value']);
                            break;
                        case 'value':
                            $additionalDataBlock[$dataPropertiesArray['Key']] = $this->parseText($dataPropertiesArray['Value']);
                            break;
                        case 'urlId':
                            $additionalDataBlock[$dataPropertiesArray['Key']] = $this->parseText($dataPropertiesArray['Value']);
                            break;
                    }
                    //echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $dataPropertiesArray['Key'] . ' ' . $additionalDataBlock[$dataPropertiesArray['Key']] . '<br />' ;
                }

                $sectionArray[$custompageSlug][$sectionResultArray['childkey']] = array($additionalDataBlock);
            }
        }

        return $sectionArray;
    }

    /**
     *
     */
    public function getJson($updateCheckOnly = false) {
        $queryResult = $this->queryContentItemsAndTopLevelItemDateFields();
        $this->parseContentItemsAndTopLevelFields($queryResult);

        $this->setSectionArray(
            $this->parseAndGetLowerLevelFields($this->getSectionArray())
        );

        $outputString = json_encode($this->getSectionArray());

        if ($updateCheckOnly) {
            $outputArray = array(
                'version' => hash('sha512', $outputString)
            );
        } else {
            $outputArray = array(
                'version' => hash('sha512', $outputString),
                'total' => count($this->getSectionArray()),
                'results' => $this->getSectionArray()
            );
        }

        echo json_encode($outputArray);
    }

    private function parseImages($imageString = '') {
        $imageArray = array();
        $imageArray = explode(',', $imageString);

        return $imageArray;
    }

    private function parseText($textString = '') {
        $textString = preg_replace('/\r|\n|\t/m','', $textString);
        $textString = htmlspecialchars($textString);

        return $textString;
    }

    /**
     * @param mixed $contentType
     */
    public function setContentType($contentType)
    {
        $this->contentType = $contentType;
    }

    /**
     * @return mixed
     */
    public function getContentType()
    {
        return $this->contentType;
    }


    /**
     * @param array $sectionArray
     */
    public function setSectionArray($sectionArray)
    {
        $this->sectionArray = $sectionArray;
    }

    /**
     * @return array
     */
    public function getSectionArray()
    {
        return $this->sectionArray;
    }

    /**
     * @return string
     */
    public function getProtocal()
    {
        return $this->protocal;
    }

    /**
     * @param string $protocal
     */
    public function setProtocal($protocal)
    {
        $this->protocal = $protocal;
    }

    /**
     * @param mixed $imageManager
     */
    public function setImageManager($imageManager)
    {
        $this->imageManager = $imageManager;
    }

    /**
     * @return mixed
     */
    public function getImageManager()
    {
        return $this->imageManager;
    }
}

$categoryTypeEncoder = new CategoryTypeNameHere();

if ( isset($_GET['update']) ) $categoryTypeEncoder->getJson(true);
else $categoryTypeEncoder->getJson();
