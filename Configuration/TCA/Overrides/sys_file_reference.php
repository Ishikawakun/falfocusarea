<?php
defined('TYPO3_MODE') or die();

$extensionConfiguration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['falfocusarea']);

if ($extensionConfiguration['focusAreaTable'] == 'reference') {
    // TODO: add configuration for falfocusarea column if table is configured as extension data target
}