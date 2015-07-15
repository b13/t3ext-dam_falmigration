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
use TYPO3\CMS\Core\Utility\GeneralUtility;
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
     * Layout to set on migrated content elements of CType "uploads".
     * Layout 1 matches dam_filelinks behaviour (file type icon before links).
     * @var int
     */
    protected $uploadsLayout = 1;

    /**
     * Chain defining priority and handling of fields for image captions.
     * @var array
     */
    protected $chainImageCaption = array();

    /**
     * Chain defining priority and handling of fields for image titles.
     * @var array
     */
    protected $chainImageTitle = array();

    /**
     * Chain defining priority and handling of fields for image alt texts.
     * @var array
     */
    protected $chainImageAlt = array();

    /* constants for parsed chain options
     * may not be the same as parser input, so do not use outside this class
     */
    const CHAIN_CONTENT_TITLE = 'contentTitle';
    const CHAIN_CONTENT_ALT = 'contentAlt';
    const CHAIN_CONTENT_CAPTION = 'contentCaption';
    const CHAIN_META_TITLE = 'metaTitle';
    const CHAIN_META_ALT = 'metaAlt';
    const CHAIN_META_CAPTION = 'metaCaption';
    const CHAIN_META_DESCRIPTION = 'metaDescription';
    const CHAIN_EMPTY = 'empty';
    const CHAIN_DEFAULT = 'default';

    /**
     * Chain options parser mapping. Used to verify valid options and associate
     * them to above constants.
     * @var array
     */
    protected $chainOptionsMap = array(
        'contentTitle' => self::CHAIN_CONTENT_TITLE,
        'contentAlt' => self::CHAIN_CONTENT_ALT,
        'contentCaption' => self::CHAIN_CONTENT_CAPTION,
        'metaTitle' => self::CHAIN_META_TITLE,
        'metaAlt' => self::CHAIN_META_ALT,
        'metaCaption' => self::CHAIN_META_CAPTION,
        'metaDescription' => self::CHAIN_META_DESCRIPTION,
        'empty' => self::CHAIN_EMPTY,
        'default' => self::CHAIN_DEFAULT,
    );

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

                $isTablePagesOrOverlay = (($damRelation['tablenames'] === 'pages') || ($damRelation['tablenames'] === 'pages_language_overlay'));
                $isTableTTContent = ($damRelation['tablenames'] === 'tt_content');
                if ($isTableTTContent || $isTablePagesOrOverlay) {
                    // when using IRRE (should be default for image & media?) we
                    // need to supply the actual number of images referenced
                    // by the content element
                    // QUESTION: we currently white-list only known fieldnames,
                    //           can we fully rely on TCA to check if this is
                    //           being necessary? (may apply to more fields than
                    //           just image & media)
                    $needsReferenceCount = (($insertData['fieldname'] === 'image') ||
                                            ($insertData['fieldname'] === 'media'));
                    if ($needsReferenceCount) {
                        $tcaConfig = $GLOBALS['TCA']['tt_content']['columns'][$insertData['fieldname']]['config'];
                        if ($tcaConfig['type'] === 'inline') {
                            $this->database->exec_UPDATEquery(
                                    'tt_content',
                                    'uid = ' . $damRelation['uid_foreign'],
                                    array($insertData['fieldname'] => $numberImportedRelationsByContentElement[$insertData['uid_foreign']])
                            );
                        }
                    }

                    if ($isTableTTContent && ($insertData['fieldname'] === 'image')) {
                        // get image-related settings saved on content element
                        $ttContentFields = $this->database->exec_SELECTgetSingleRow(
                                'image_link, imagecaption, titleText, altText',
                                'tt_content',
                                'uid = ' . $damRelation['uid_foreign']
                        );

                        // prepare content element data to apply via chain
                        $contentElementRelationIndex = ($numberImportedRelationsByContentElement[$insertData['uid_foreign']] - 1);
                        $chainFieldArray = $this->compileChainFieldArray($damRelation, $ttContentFields, $contentElementRelationIndex);

                        // process configurable image field chains
                        $update = array();
                        $update['title'] = $this->applyChain($this->chainImageTitle, $chainFieldArray);
                        $update['alternative'] = $this->applyChain($this->chainImageAlt, $chainFieldArray);
                        $update['description'] = $this->applyChain($this->chainImageCaption, $chainFieldArray);

                        // copy link if content element has got any
                        if (!empty($ttContentFields)) {
                            $imageLinks = explode(chr(10), $ttContentFields['image_link']);

                            if ($imageLinks[$contentElementRelationIndex]) {
                                $update['link'] = $imageLinks[$numberImportedRelationsByContentElement[$insertData['uid_foreign']] - 1];
                            }
                        }

                        // save to database
                        $this->database->exec_UPDATEquery(
                                'sys_file_reference',
                                'uid = ' . $newRelationsRecordUid,
                                $update
                        );
                    } elseif ($insertData['fieldname'] === 'media') {
                        // "media" is processed for both tt_content and pages
                        // (see getColForFieldName for applicable mappings)

                        // QUESTION: The way this is handled for pages and page
                        //           language overlays does not appear to make
                        //           any sense (introduced for dam_pages):
                        //           Do we really query tt_content using a page
                        //           ID for a content UID? This should not yield
                        //           any valid results and may require testing.
                        //           (we believe tt_content should be
                        //           $damRelation['tablenames'] instead)
                        //           see GitHub issue #73

                        // migrate captions from tt_content upload elements
                        $ttContentFields = $this->database->exec_SELECTgetSingleRow(
                                'imagecaption',
                                'tt_content',
                                'uid = ' . $damRelation['uid_foreign']
                        );

                        if (!empty($ttContentFields)) {
                            $imageCaptions = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(chr(10), $ttContentFields['imagecaption']);
                            $update = array();
                            // only update description (new caption field) if caption explode has some content
                            if ($imageCaptions[$numberImportedRelationsByContentElement[$insertData['uid_foreign']] - 1]) {
                                $update['description'] = $imageCaptions[$numberImportedRelationsByContentElement[$insertData['uid_foreign']] - 1];
                            }
                            if (count($update)) {
                                $this->database->exec_UPDATEquery(
                                        'sys_file_reference',
                                        'uid = ' . $newRelationsRecordUid,
                                        $update
                                );
                            }
                        }

                        // update layout of CType uploads
                        if ($isTableTTContent) {
                            // check if content element actually has CType uploads
                            $contentElement = $this->database->exec_SELECTgetSingleRow(
                                    'CType',
                                    'tt_content',
                                    'uid = ' . $damRelation['uid_foreign']
                            );

                            $shouldSetLayout = (($this->uploadsLayout !== NULL) && ($contentElement !== NULL) && is_array($contentElement) && ($contentElement['CType'] == 'uploads'));

                            if ($shouldSetLayout) {
                                $this->database->exec_UPDATEquery(
                                        'tt_content',
                                        'uid = ' . $damRelation['uid_foreign'],
                                        array(
                                                'layout' => $this->uploadsLayout
                                        )
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
			sys_file_metadata.caption,
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

    /**
     * Compiles all chain-relevant content required for applyChain method into
     * an array per given file relation including all options to end chains.
     *
     * @param array $damRelation fields from DAM/FAL (as used in execute())
     * @param array $ttContentFields fields from tt_content (as used in execute())
     * @param int $contentElementRelationIndex array index of current file in tt_content multi-line string fields
     *
     * @return array all content required for applyChain method
     */
    protected function compileChainFieldArray($damRelation, $ttContentFields, $contentElementRelationIndex) {
        // pre-defined values to end chain
        $out = array(
            self::CHAIN_EMPTY => '',
            self::CHAIN_DEFAULT => NULL
        );

        // split tt_content fields by line
        $hasTTContentFields = !empty($ttContentFields);
        $contentCaptions = $hasTTContentFields ? explode(chr(10), $ttContentFields['imagecaption']) : array();
        $contentTitles = $hasTTContentFields ? explode(chr(10), $ttContentFields['titleText']) : array();
        $contentAlts = $hasTTContentFields ? explode(chr(10), $ttContentFields['altText']) : array();

        // assign tt_content fields
        $out[self::CHAIN_CONTENT_CAPTION] = (count($contentCaptions) > $contentElementRelationIndex) ? $contentCaptions[$contentElementRelationIndex] : '';
        $out[self::CHAIN_CONTENT_TITLE] = (count($contentTitles) > $contentElementRelationIndex) ? $contentTitles[$contentElementRelationIndex] : '';
        $out[self::CHAIN_CONTENT_ALT] = (count($contentAlts) > $contentElementRelationIndex) ? $contentAlts[$contentElementRelationIndex] : '';

        // assign DAM meta data fields
        // fields are actually coming from migrated sys_file_metadata
        $out[self::CHAIN_META_ALT] = $damRelation['alternative'];
        $out[self::CHAIN_META_CAPTION] = $damRelation['caption'];
        $out[self::CHAIN_META_DESCRIPTION] = $damRelation['description'];
        $out[self::CHAIN_META_TITLE] = $damRelation['title'];

        return $out;
    }

    /**
     * Parses the given chain string to an array of chain option constants.
     * Prints an error message and terminates program on unknown options.
     *
     * @param string $chain comma-separated list of chain option names as documented (not necessarily matching constants!)
     *
     * @return array array of chain option constants
     */
    protected function parseChain($chain) {
        $parsed = array();

        $chainSplit = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $chain);
        foreach ($chainSplit as $elem) {
            if (array_key_exists($elem, $this->chainOptionsMap)) {
                $parsed[] = $this->chainOptionsMap[$elem];
            } else {
                $this->controller->errorMessage('invalid chain option: ' . $elem);
                exit();
            }
        }

        return $parsed;
    }

    /**
     * Determines and returns the value to set according to given chain.
     *
     * @param array $chain chain as an array of chain option constants (use parseChain)
     * @param array $chainFieldArray all content of current file reference for chain options (use compileChainFieldArray)
     *
     * @return mixed field value of first "non-empty" chain option or last chain option given; may be empty string (overriding FAL record data on content element), null (the opposite, not overriding central FAL record metadata) or anything else $chainFieldArray may have yielded
     */
    protected function applyChain($chain, $chainFieldArray) {
        $out = null;

        // replace $out by all specified fields in order
        foreach ($chain as $chainOption) {
            $out = $chainFieldArray[$chainOption];

            // we stop on first "non-empty" (not null) field
            // NOTE: empty($s) is false for strings consisting only of
            //       white-space. This appears to be what TYPO3 checks for FE
            //       rendering, it does not appear to check empty(trim($s)).
            if (!empty($out)) {
                break;
            }
        }

        return $out;
    }

    /**
     * Sets the chain used to determine image caption content. See documentation
     * on what values are supported and why they should be set.
     * May terminate program on invalid input.
     *
     * @param string $chainImageCaption options by documented names (not class constants), separated by commas
     *
     * @return \TYPO3\CMS\DamFalmigration\Service\MigrateRelationsService for chaining
     */
    public function setChainImageCaption($chainImageCaption) {
        $this->chainImageCaption = $this->parseChain($chainImageCaption);

        if (count($this->chainImageCaption) === 0) {
            $this->controller->errorMessage('image caption chain cannot be empty');
            exit;
        }

        return $this;
    }

    /**
     * Sets the chain used to determine image title content. See documentation
     * on what values are supported and why they should be set.
     * May terminate program on invalid input.
     *
     * @param string $chainImageTitle options by documented names (not class constants), separated by commas
     *
     * @return \TYPO3\CMS\DamFalmigration\Service\MigrateRelationsService for chaining
     */
    public function setChainImageTitle($chainImageTitle) {
        $this->chainImageTitle = $this->parseChain($chainImageTitle);

        if (count($this->chainImageTitle) === 0) {
            $this->controller->errorMessage('image title chain cannot be empty');
            exit;
        }

        return $this;
    }

    /**
     * Sets the chain used to determine image alternative text content. See
     * documentation on what values are supported and why they should be set.
     * May terminate program on invalid input.
     *
     * @param string $chainImageAlt options by documented names (not class constants), separated by commas
     *
     * @return \TYPO3\CMS\DamFalmigration\Service\MigrateRelationsService for chaining
     */
    public function setChainImageAlt($chainImageAlt) {
        $this->chainImageAlt = $this->parseChain($chainImageAlt);

        if (count($this->chainImageAlt) === 0) {
            $this->controller->errorMessage('image alt text chain cannot be empty');
            exit;
        }

        return $this;
    }

    /*
     * Sets the layout ID to update "uploads" content elements with upon migration.
     * @param mixed $uploadsLayout layout ID to set, NULL or 'null' to disable
     * @return $this to allow for chaining
     */
    public function setUploadsLayout($uploadsLayout) {
        if (($uploadsLayout === NULL) || (strtolower($uploadsLayout) === 'null')) {
            $this->uploadsLayout = NULL;
        } else {
            $this->uploadsLayout = (int)$uploadsLayout;
        }
    }
}
