<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Core\\Resource\\Processing\\LocalCropScaleMaskHelper'] =
	array('className' => 'Ishikawakun\\Falfocusarea\\Override\\Resource\\LocalCropScaleMaskHelperWithFocusArea');

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Core\\Resource\\Processing\\ImageCropScaleMaskTask'] =
    array('className' => 'Ishikawakun\\Falfocusarea\\Override\\Resource\\ImageCropScaleMaskTaskWithFocusArea');

$GLOBALS['TYPO3_CONF_VARS']['GFX']['advanced'] = 1;
$GLOBALS['TYPO3_CONF_VARS']['GFX']['preferredWidth'] = 1280;
$GLOBALS['TYPO3_CONF_VARS']['GFX']['preferredHeight'] = 720;
$GLOBALS['TYPO3_CONF_VARS']['GFX']['respectAspectRatio'] = 0;
