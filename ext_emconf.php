<?php

$EM_CONF[$_EXTKEY] = array (
    'title' => 'FAL: Focus area for images',
    'description' => 'Backend extension for defining focus areas in images and respecting those during resizing operations.',
    'category' => 'backend',
    'version' => '1.0.0',
    'state' => 'stable',
    'uploadfolder' => true,
    'createDirs' => '',
    'clearcacheonload' => true,
    'author' => 'Sven Radetzky',
    'author_email' => 'sven.radetzky@mail.de',
    'author_company' => '',
    'constraints' =>
        array (
            'depends' =>
                array (
                    'typo3' => '6.2.0-6.2.99',
                    'cms' => '',
                    'extbase' => '',
                    'fluid' => '',
                    'filemetadata' => '6.2.0-6.2.99',
                ),
            'conflicts' =>
                array (
                ),
            'suggests' =>
                array (
                ),
        ),
);

