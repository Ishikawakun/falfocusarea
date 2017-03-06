<?php
namespace Ishikawakun\Falfocusarea\Override\Resource;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 Sven Radetzky <sven.radetzky@gmx.de>
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
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use \TYPO3\CMS\Core\Resource;

/**
 * Extending task checksum with focus area information from sys_file_metadata.
 */
class ImageCropScaleMaskTaskWithFocusArea extends Resource\Processing\ImageCropScaleMaskTask
{
    /**
     * Extends checksum with focus area information.
     *
     * The debug value is included for now to allow forcing of image processing for debugging purposes.
     *
     * @return array
     */
    protected function getChecksumData()
    {
        // Collect file metadata properties
        $fileMetaData = $this->getSourceFile()->getProperties();

        // Check focus area corners in metadata and fallback if necessary
        $focusAreaData = array(
            'focal_x_min' => isset($fileMetaData['focal_x_min']) ? $fileMetaData['focal_x_min'] : 0,
            'focal_x_max' => isset($fileMetaData['focal_x_max']) ? $fileMetaData['focal_x_max'] : 0,
            'focal_y_min' => isset($fileMetaData['focal_y_min']) ? $fileMetaData['focal_y_min'] : 0,
            'focal_y_max' => isset($fileMetaData['focal_y_max']) ? $fileMetaData['focal_y_max'] : 0,
            'debug' => filemtime(__FILE__),
        );

        // Return combined checksum
        return array_merge(
            parent::getChecksumData(),
            array(serialize($focusAreaData))
        );
    }
}
