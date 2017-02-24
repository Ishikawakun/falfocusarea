<?php

$EM_CONF[$_EXTKEY] = array(
    'title' => 'Focus areas for fal images',
    'description' => 'Backend extension for defining and utilizing focus areas in images or image references. Updated for TYPO3 CMS 7.6.x',
    'category' => 'backend',
    'version' => '7.6.0',
    'state' => 'beta',
    'uploadfolder' => true,
    'createDirs' => '',
    'clearcacheonload' => true,
    'author' => 'Sven Radetzky',
    'author_email' => 'sven.radetzky@mail.de',
    'author_company' => '',
    'constraints' =>
        array(
            'depends' =>
                array(
                    'typo3' => '7.6.0-7.6.99',
                    'cms' => '',
                    'extbase' => '',
                    'fluid' => '',
                    'filemetadata' => '7.6.0-7.6.99',
                ),
            'conflicts' =>
                array(
                ),
            'suggests' =>
                array(
                ),
        ),
);
