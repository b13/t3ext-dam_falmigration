<?php
namespace TYPO3\CMS\DamFalmigration\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Benjamin Mack <benni@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the
 *  license from the author is found in LICENSE.txt distributed with these
 *  scripts.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Migrate DAM relations to FAL relations
 * right now this is dam_ttcontent, dam_uploads
 *
 * @author Benjamin Mack <benni@typo3.org>
 */
class MigrateRelationsService extends AbstractService {

    /**
     * @var \TYPO3\CMS\Core\Database\ReferenceIndex
     * @inject
     */
    protected $referenceIndex;

    /**
     * @var string
     */
    protected $tablename = '';

    /**
     * main function
     *
     * @throws \Exception
     * @return FlashMessage
     */
    public function execute() {
        if ($this->tablename === '') {
            $this->controller->headerMessage(LocalizationUtility::translate('migrateRelationsCommand', 'dam_falmigration'));
        } else {
            $this->controller->headerMessage(LocalizationUtility::translate('migrateRelationsCommandForTable', 'dam_falmigration', array($this->tablename)));
        }
        if (!$this->isTableAvailable('tx_dam_mm_ref')) {
            return $this->getResultMessage('referenceTableNotFound');
        }

        $numberImportedRelationsByContentElement = array();
        $damRelations = $this->execSelectDamReferencesWhereSysFileExists();

        $counter = 0;
        $total = $this->database->sql_num_rows($damRelations);

        $this->controller->message('Found ' . $total . ' relations.');
        while ($damRelation = $this->database->sql_fetch_assoc($damRelations)) {
            $counter++;
            $pid = $this->getPidOfForeignRecord($damRelation);
            $insertData = array(
                    'pid' => ($pid === NULL) ? 0 : $pid,
                    'tstamp' => time(),
                    'crdate' => time(),
                    'cruser_id' => $GLOBALS['BE_USER']->user['uid'],
                    'sorting_foreign' => $damRelation['sorting_foreign'],
                    'uid_local' => $damRelation['sys_file_uid'],
                    'uid_foreign' => $damRelation['uid_foreign'],
                    'tablenames' => $damRelation['tablenames'],
                    'fieldname' => $this->getColForFieldName($damRelation),
                    'table_local' => 'sys_file',
                    'title' => $damRelation['title'],
                    'description' => $damRelation['description'],
                    'alternative' => $damRelation['alternative'],
            );

            // we need an array holding the already migrated file-relations to choose the right line of the imagecaption-field.
            if ($insertData['tablenames'] == 'tt_content' && ($insertData['fieldname'] == 'media' || $insertData['fieldname'] == 'image')) {
                $numberImportedRelationsByContentElement[$insertData['uid_foreign']]++;
            }

            if (!$this->doesFileReferenceExist($damRelation)) {
                $this->database->exec_INSERTquery(
                        'sys_file_reference',
                        $insertData
                );
                $newRelationsRecordUid = $this->database->sql_insert_id();
                $this->updateReferenceIndex($newRelationsRecordUid);

                // pageLayoutView-object needs image to be set something higher than 0
                if ($damRelation['tablenames'] === 'tt_content' ||
                        $damRelation['tablenames'] === 'pages' ||
                        $damRelation['tablenames'] === 'pages_language_overlay'
                ) {
                    if ($insertData['fieldname'] === 'image') {
                        $tcaConfig = $GLOBALS['TCA']['tt_content']['columns']['image']['config'];
                        if ($tcaConfig['type'] === 'inline') {
                            $this->database->exec_UPDATEquery(
                                    'tt_content',
                                    'uid = ' . $damRelation['uid_foreign'],
                                    array('image' => 1)
                            );
                        }

                        // migrate settings from tt_content.
                        $ttContentFields = $this->database->exec_SELECTgetSingleRow(
                                'image_link, imagecaption, titleText, altText',
                                'tt_content',
                                'uid = ' . $damRelation['uid_foreign']
                        );
                        if (!empty($ttContentFields)) {

                            $imageLinks = explode(chr(10), $ttContentFields['image_link']);
                            $imageCaptions = explode(chr(10), $ttContentFields['imagecaption']);
                            $titleTexts = explode(chr(10), $ttContentFields['titleText']);
                            $altTexts = explode(chr(10), $ttContentFields['altText']);
                            $update = array();
                            
                            // if explodes from tt_content above yielded results...
                            // ... copy link
                            if ($imageLinks[$numberImportedRelationsByContentElement[$insertData['uid_foreign']] - 1]) {
                                $update['link'] = $imageLinks[$numberImportedRelationsByContentElement[$insertData['uid_foreign']] - 1];
                            }
                            
                            // ... copy title
                            if ($titleTexts[$numberImportedRelationsByContentElement[$insertData['uid_foreign']] - 1]) {
                                $update['title'] = $titleTexts[$numberImportedRelationsByContentElement[$insertData['uid_foreign']] - 1];
                            }
                            
                            // ... copy caption (now called "description")
                            if ($imageCaptions[$numberImportedRelationsByContentElement[$insertData['uid_foreign']] - 1]) {
                                $update['description'] = $imageCaptions[$numberImportedRelationsByContentElement[$insertData['uid_foreign']] - 1];
                            }
                            
                            // ... copy alt text
                            if ($altTexts[$numberImportedRelationsByContentElement[$insertData['uid_foreign']] - 1]) {
                                $update['alternative'] = $altTexts[$numberImportedRelationsByContentElement[$insertData['uid_foreign']] - 1];
                            }
                            
                            if (count($update)) {
                                $this->database->exec_UPDATEquery(
                                        'sys_file_reference',
                                        'uid = ' . $newRelationsRecordUid,
                                        $update
                                );
                            }
                        }
                    } elseif ($insertData['fieldname'] === 'media') {
                        // migrate captions from tt_content upload elements
                        $ttContentFields = $this->database->exec_SELECTgetSingleRow(
                                'imagecaption',
                                'tt_content',
                                'uid = ' . $damRelation['uid_foreign']
                        );

                        if (!empty($ttContentFields)) {
                            $imageCaptions = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(chr(10), $ttContentFields['imagecaption']);
                            $update = array();
                            // only update title & description (new caption field) if caption explode has some content
                            if ($imageCaptions[$numberImportedRelationsByContentElement[$insertData['uid_foreign']] - 1]) {
                                $update['title'] = $imageCaptions[$numberImportedRelationsByContentElement[$insertData['uid_foreign']] - 1];
                                $update['description'] = $update['title'];
                            }
                            if (count($update)) {
                                $this->database->exec_UPDATEquery(
                                        'sys_file_reference',
                                        'uid = ' . $newRelationsRecordUid,
                                        $update
                                );
                            }
                        }
                    }
                }
                $this->controller->message(number_format(100 * ($counter / $total), 1) . '% of ' . $total .
                        ' id: ' . $damRelation['uid_local'] .
                        ' table: ' . $damRelation['tablenames'] .
                        ' ident: ' . $damRelation['ident']);
                $this->amountOfMigratedRecords++;
            }
        }
        $this->database->sql_free_result($damRelations);

        return $this->getResultMessage();
    }

    /**
     * get pid of foreign record
     * this is needed by sys_file_reference records
     *
     * @param array $damRelation
     *
     * @return integer
     */
    protected function getPidOfForeignRecord(array $damRelation) {
        $record = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
                'pid',
                $damRelation['tablenames'],
                'uid=' . (int)$damRelation['uid_foreign']
        );

        return $record['pid'] ?: 0;
    }

    /**
     * After a migration of tx_dam -> sys_file the col _migrateddamuid is
     * filled with dam uid Now we can search in dam relations for dam records
     * which have already been migrated to sys_file
     *
     * @return \mysqli_result
     */
    protected function execSelectDamReferencesWhereSysFileExists() {
        $where = 'tx_dam_mm_ref.tablenames <> ""';
        if ($this->tablename !== '') {
            $where = 'tx_dam_mm_ref.tablenames = "' . $this->tablename . '"';
        }

        return $this->database->exec_SELECTquery(
                'tx_dam_mm_ref.*,
			sys_file_metadata.title,
			sys_file_metadata.description,
			sys_file_metadata.alternative,
			sys_file.uid as sys_file_uid',
                'tx_dam_mm_ref
			JOIN sys_file ON
				sys_file._migrateddamuid = tx_dam_mm_ref.uid_local
			JOIN sys_file_metadata ON
				sys_file.uid = sys_file_metadata.file
				',
                $where,
                '',
                'tx_dam_mm_ref.sorting ASC,tx_dam_mm_ref.sorting_foreign ASC',
                (int)$this->getRecordLimit()
        );
    }

    /**
     * col for fieldname was saved in col "ident"
     * But: If dam_ttcontent is installed fieldName is "image" for images and
     * "media" for uploads
     *
     * @param array $damRelation
     *
     * @return string
     */
    protected function getColForFieldName(array $damRelation) {
        if ($damRelation['tablenames'] == 'tt_content' && $damRelation['ident'] == 'tx_damttcontent_files') {
            $fieldName = 'image';
        } elseif ($damRelation['tablenames'] == 'tt_content' &&
                ($damRelation['ident'] == 'tx_damttcontent_files_upload' || $damRelation['ident'] == 'tx_damfilelinks_filelinks')
        ) {
            $fieldName = 'media';
        } elseif (($damRelation['tablenames'] == 'pages' || $damRelation['tablenames'] == 'pages_language_overlay')
                && $damRelation['ident'] == 'tx_dampages_files'
        ) {
            $fieldName = 'media';
        } else {
            $fieldName = $damRelation['ident'];
        }

        return $fieldName;
    }

    /**
     * update reference index
     *
     * @param integer $uid
     *
     * @return void
     */
    protected function updateReferenceIndex($uid) {
        $this->referenceIndex->updateRefIndexTable('sys_file_reference', $uid);
    }

    /**
     * @param string $tablename
     *
     * @return $this to allow for chaining
     */
    public function setTablename($tablename) {
        $this->tablename = $tablename;

        return $this;
    }

}
