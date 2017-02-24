<?php
defined('TYPO3_MODE') or die();

$extensionConfiguration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['falfocusarea']);

if ($extensionConfiguration['focusAreaTable'] == 'metadata') {
    $tca = array(
        'ctrl' => array(
            'type' => 'file:type',
        ),
        'types' => array(
            TYPO3\CMS\Core\Resource\File::FILETYPE_IMAGE => array('showitem' => '
			fileinfo, title, description, keywords, --palette--;;20;;,

			--div--;LLL:EXT:falfocusarea/Resources/Private/Language/locallang_tca.xlf:tabs.focalpoint,
				focal_point_editor,
				--palette--;LLL:EXT:falfocusarea/Resources/Private/Language/locallang_tca.xlf:palette.focalpoint_x;70;;,
				--palette--;LLL:EXT:falfocusarea/Resources/Private/Language/locallang_tca.xlf:palette.focalpoint_y;80;;,

			--div--;LLL:EXT:cms/locallang_ttc.xml:tabs.access,
				--palette--;LLL:EXT:filemetadata/Resources/Private/Language/locallang_tca.xlf:palette.visibility;10;; ,
				fe_groups,

			--div--;LLL:EXT:filemetadata/Resources/Private/Language/locallang_tca.xlf:tabs.metadata,
				creator,
				--palette--;LLL:EXT:filemetadata/Resources/Private/Language/locallang_tca.xlf:palette.geo_location;40;; ,
				--palette--;;30;;,
				--palette--;LLL:EXT:filemetadata/Resources/Private/Language/locallang_tca.xlf:palette.metrics;50;;'),
        ),
        'palettes' => array(
            '70' => array('showitem' => 'focal_x_min, focal_x_max', 'canNotCollapse' => '1'),
            '80' => array('showitem' => 'focal_y_min, focal_y_max', 'canNotCollapse' => '1'),
        ),
        'columns' => array(
            'focal_x_min' => array(
                'exclude' => 1,
                'l10n_mode' => 'exclude',
                'l10n_display' => 'defaultAsReadonly',
                'label' => 'LLL:EXT:falfocusarea/Resources/Private/Language/locallang_tca.xlf:sys_file_metadata.focal_x_min',
                'config' => array(
                    'type' => 'input',
                    'size' => '10',
                    'max' => '20',
                    'eval' => 'int',
                    'default' => '0',
                    'readOnly' => true,
                ),
            ),

            'focal_y_min' => array(
                'exclude' => 1,
                'l10n_mode' => 'exclude',
                'l10n_display' => 'defaultAsReadonly',
                'label' => 'LLL:EXT:falfocusarea/Resources/Private/Language/locallang_tca.xlf:sys_file_metadata.focal_y_min',
                'config' => array(
                    'type' => 'input',
                    'size' => '10',
                    'max' => '20',
                    'eval' => 'int',
                    'default' => '0',
                    'readOnly' => true,
                ),
            ),

            'focal_x_max' => array(
                'exclude' => 1,
                'l10n_mode' => 'exclude',
                'l10n_display' => 'defaultAsReadonly',
                'label' => 'LLL:EXT:falfocusarea/Resources/Private/Language/locallang_tca.xlf:sys_file_metadata.focal_x_max',
                'config' => array(
                    'type' => 'input',
                    'size' => '10',
                    'max' => '20',
                    'eval' => 'int',
                    'default' => '0',
                    'readOnly' => true,
                ),
            ),

            'focal_y_max' => array(
                'exclude' => 1,
                'l10n_mode' => 'exclude',
                'l10n_display' => 'defaultAsReadonly',
                'label' => 'LLL:EXT:falfocusarea/Resources/Private/Language/locallang_tca.xlf:sys_file_metadata.focal_y_max',
                'config' => array(
                    'type' => 'input',
                    'size' => '10',
                    'max' => '20',
                    'eval' => 'int',
                    'default' => '0',
                    'readOnly' => true,
                ),
            ),

            'focal_point_editor' => array(
                'exclude' => 0,
                'label' => 'LLL:EXT:falfocusarea/Resources/Private/Language/locallang_tca.xlf:sys_file_metadata.focal_point_editor',
                'config' => array(
                    'type' => 'user',
                    'size' => '30',
                    'userFunc' => 'Ishikawakun\\Falfocusarea\\UserFunc\\FocusAreaEditor->tcaField',
                )
            ),

            'width' => array(
                'exclude' => 1,
                'l10n_mode' => 'exclude',
                'l10n_display' => 'defaultAsReadonly',
                'label' => 'LLL:EXT:filemetadata/Resources/Private/Language/locallang_tca.xlf:sys_file_metadata.width',
                'config' => array(
                    'type' => 'input',
                    'size' => '10',
                    'max' => '20',
                    'eval' => 'int',
                    'default' => '0',
                    'readOnly' => false,
                ),
            ),

            'height' => array(
                'exclude' => 1,
                'l10n_mode' => 'exclude',
                'l10n_display' => 'defaultAsReadonly',
                'label' => 'LLL:EXT:filemetadata/Resources/Private/Language/locallang_tca.xlf:sys_file_metadata.height',
                'config' => array(
                    'type' => 'input',
                    'size' => '10',
                    'max' => '20',
                    'eval' => 'int',
                    'default' => '0',
                    'readOnly' => false,
                ),
            ),
        ),
    );

    $GLOBALS['TCA']['sys_file_metadata'] = array_replace_recursive($GLOBALS['TCA']['sys_file_metadata'], $tca);

    /**
     * Add category tab if categories column is present
     * @deprecated
     */
    /*if (isset($GLOBALS['TCA']['sys_file_metadata']['columns']['categories'])) {
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
            'sys_file_metadata',
            '--div--;LLL:EXT:lang/locallang_tca.xlf:sys_category.tabs.category,categories'
        );
    }*/
}
