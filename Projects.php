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
 * @description a
 *
 * @copyright    Copyright (C) 2013 CFA Group. All rights reserved.
 * @license      GNU General Public License Version 2 or later.
 * @author       Stuart Eske, <stuart.eske@cfa-group.com>
 */
class Projects {

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
    public $projectArray;

    /**
     *
     */
    function __construct() {
        // Get the content type from the script filename
        $this->setContentType(
            str_replace(".php", "", basename($_SERVER["SCRIPT_NAME"]))
        );

        // Init the project array
        $this->setProjectArray(array());
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
        $projectArray = $this->getProjectArray();

        while($resultArray = mysql_fetch_array($queryResult)) {
            switch($resultArray[4]) {
                case 'Copy':
                    $textString = $resultArray[5];

                    $projectArray[$resultArray['Slug']][$resultArray[4]] = $this->parseText($textString);
                    //$projectArray[$resultArray['Slug']][$resultArray[4]] = "";
                    break;
                case 'Image':
                    $imageIdArray = explode( ',', $resultArray[5] );

                    foreach( $imageIdArray as $key => $imageId ) {
                        $projectArray[$resultArray['Slug']]['Image'][$key]['ImageUrl'] = $imageId;
                        $projectArray[$resultArray['Slug']]['Image'][$key][$resultArray[4]] = '';
                    }
                    break;
                default:
                    if (empty($resultArray[5])) $keyValue = 0;
                    else $keyValue = $resultArray[5];

                    $projectArray[$resultArray['Slug']][$resultArray[4]] = $keyValue;
                    break;
            }
        }

        $this->setProjectArray($projectArray);
    }

    /**
     *
     */
    public function getJson() {
        $queryResult = $this->queryContentItemsAndTopLevelItemDateFields();

        $this->parseContentItemsAndTopLevelFields($queryResult);

        echo json_encode($this->getProjectArray());
    }

    private function parseImages($imageString = '') {
        $imageArray = array();
        $imageArray = explode(',', $imageString);

        return $imageArray;
    }

    private function parseText($textString = '') {
        $textString = preg_replace('/\r|\n|\t/m','', $textString);
        $textString = htmlentities($textString);

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
     * @param array $projectArray
     */
    public function setProjectArray($projectArray)
    {
        $this->projectArray = $projectArray;
    }

    /**
     * @return array
     */
    public function getProjectArray()
    {
        return $this->projectArray;
    }

    /**
     * @return string
     */
    public function getProtocal()
    {
        return $this->protocal;
    }
}

$projectsConverter = new Projects();
$projectsConverter->getJson();

// Get the data type name from the php script file
$dataType = str_replace(".php", "", basename($_SERVER["SCRIPT_NAME"]));

$sql = "SELECT tas.SectionId AS SID, tcp.ID AS CID, tcp.Slug, tcpd.ID AS DID, tcpd.key, tcpd.value "
    . "FROM tbladminsections AS tas "
    . " LEFT JOIN tblcustompage AS tcp on tas.SectionId = tcp.Section "
    . " LEFT JOIN tblcustompagedata AS tcpd on tcp.ID = tcpd.CustomPageID "
    . "WHERE tas.SectionName = '" . $dataType . "' "
    . "AND tcp.Enabled = 1 ";

$queryResult = QueryDB($sql);

$imageManager = new Images_ImageManager();

$projectArray = array();

while($resultArray = mysql_fetch_array($queryResult)) {
    switch($resultArray[4]) {
        case 'Copy':
            $keyValue = preg_replace('/\r|\n|\t/m','', $resultArray[5]);
            $keyValue = htmlentities($keyValue);

            $projectArray[$resultArray['Slug']][$resultArray[4]] = $keyValue;
            //$projectArray[$resultArray['Slug']][$resultArray[4]] = "";
            break;
        case 'Image':
            $imageIdArray = explode( ',', $resultArray[5] );

            foreach( $imageIdArray as $key => $imageId ) {
                $projectArray[$resultArray['Slug']]['Image'][$key]['ImageUrl'] = $imageId;
                $projectArray[$resultArray['Slug']]['Image'][$key][$resultArray[4]] = '';
            }

//            $keyValue = $imageManager->getImageString(
//                $resultArray[5],
//                'portfolio-main'
//            );

            //$imageFile = file_get_contents($keyValue);
            //$imageData = 'data:image/png;base64,' . base64_encode($imageFile);

//            $projectArray[$resultArray['Slug']]['ImageUrl'] = $keyValue;
//            $projectArray[$resultArray['Slug']][$resultArray[4]] = '';
            break;
        default:
            if (empty($resultArray[5])) $keyValue = 0;
                else $keyValue = $resultArray[5];

            $projectArray[$resultArray['Slug']][$resultArray[4]] = $keyValue;
            break;
    }
}

foreach(array_keys($projectArray) as $custompageSlug) {
//    echo $custompageSlug . '<br />';

    /*
    SELECT tcp.ID, tcp.Slug, tcpl.*
    FROM tblcustompage AS tcp
    JOIN tblcustompagelinks AS tcpl on tcp.ID = tcpl.ParentId
    WHERE Slug = 'rbli-'
    |------------------------------------------------------|
    | Id  | Slug  | linkid | parentid | childid | childkey |
    | 199 | rbli- | 1776   | 199      | 6       | Services |
    | 199 | rbli- | 1775   | 199      | 195     | Client   |
    | 199 | rbli- | 1778   | 199      | 184     | Sectors  |
    | 199 | rbli- | 1777   | 199      | 190     | Sectors  |
    |------------------------------------------------------|
     */

    // Get the extra data entities
    $sql = "SELECT tcp.ID, tcp.Slug, tcpl.*
      FROM tblcustompage AS tcp
      JOIN tblcustompagelinks AS tcpl on tcp.ID = tcpl.ParentId
      WHERE Slug = '" . $custompageSlug . "'";

    $sectionResult = QueryDB($sql);
    while($sectionResultArray = mysql_fetch_array($sectionResult)) {
//        echo '&nbsp;&nbsp;&nbsp;&nbsp;' . $resultArray['childid'] . ' ' . $resultArray['childkey'] . '<br />' ;
        //$projectArray[$resultArray['Slug']][$resultArray['childkey']]

        /*
         SELECT * FROM `tblcustompagedata` WHERE CustomPageID = 111
        |-----------------------------------------------|
        | ID  | CustomPageId | Key     | Value          |
        | 690 | 111          | Title   | Media Planning |
        | 691 | 111          | Image   | 402,497        |
        | 692 | 111          | Online  |                |
        | 693 | 111          | Offline | 1              |
        | 954 | 111          | Copy    | <p>CFA ...     |
        |-----------------------------------------------|
         */
        $queryDataPropertiesSql = "SELECT * "
            . "FROM tblcustompagedata "
            . "WHERE CustomPageID = " . $sectionResultArray['childid'] . " ";

        $queryDataPropertiesResult = QueryDB($queryDataPropertiesSql);

        $additionalDataBlock = array();

        while($dataPropertiesArray = mysql_fetch_array($queryDataPropertiesResult)) {

//            echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'
//                . $dataPropertiesArray['Key']
//                . ' '
//                . $dataPropertiesArray['Value']
//                . '<br />' ;

            switch($dataPropertiesArray['Key']) {
                case 'Image':
                    $imageIdArray = explode( ',', $dataPropertiesArray['Value'] );

                    foreach( $imageIdArray as $key => $imageId ) {
                        $additionalDataBlock[$dataPropertiesArray['Key']][$key]['ImageUrl'] = $imageId;
                        $additionalDataBlock[$dataPropertiesArray['Key']][$key]['Image'] = '';
                    }
                    break;
                case 'Copy':
                    $keyValue = preg_replace('/\r|\n|\t/m','', $dataPropertiesArray['Value']);
                    $keyValue = htmlentities($keyValue);

                    $additionalDataBlock[$dataPropertiesArray['Key']] = $keyValue;
                    //$additionalDataBlock[$dataPropertiesArray['Key']] = "";
                    break;
                default:
                    if (empty($dataPropertiesArray['Value'])) $keyValue = 0;
                        else $keyValue = $dataPropertiesArray['Value'];

                    $additionalDataBlock[$dataPropertiesArray['Key']] = $keyValue;
                    break;
            }
        }

        $projectArray[$custompageSlug][$sectionResultArray['childkey']][] = $additionalDataBlock;
    }
}

//echo var_dump(array_keys($projectArray));
 //echo var_dump($projectArray[$resultArray['Slug']]);

//echo json_encode($projectArray);