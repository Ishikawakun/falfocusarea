<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Core\\Resource\\Processing\\LocalCropScaleMaskHelper'] =
    array('className' => \Ishikawakun\Falfocusarea\Override\Resource\LocalCropScaleMaskHelperWithFocusArea::class);

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Core\\Resource\\Processing\\ImageCropScaleMaskTask'] =
    array('className' => \Ishikawakun\Falfocusarea\Override\Resource\ImageCropScaleMaskTaskWithFocusArea::class);
