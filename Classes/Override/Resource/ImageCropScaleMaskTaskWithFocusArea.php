<?php
namespace Ishikawakun\Falfocusarea\Override\Resource;

use \TYPO3\CMS\Core\Resource;

/**
 * Extending task checksum with focus area information from sys_file_metadata.
 */
class ImageCropScaleMaskTaskWithFocusArea extends Resource\Processing\ImageCropScaleMaskTask {
    /**
     * Extends checksum with focus area information.
     *
     * The debug value is included for now to allow forcing of image processing for debugging purposes.
     *
     * @return array
     */
    protected function getChecksumData() {
        // Collect file metadata properties
        $fileMetaData = $this->getSourceFile()->getProperties();

        // Check focus area corners in metadata and fallback if necessary
        $focusArea = array(
            'focal_x_min' => isset($fileMetaData['focal_x_min']) ? $fileMetaData['focal_x_min'] : 0,
            'focal_x_max' => isset($fileMetaData['focal_x_max']) ? $fileMetaData['focal_x_max'] : 0,
            'focal_y_min' => isset($fileMetaData['focal_y_min']) ? $fileMetaData['focal_y_min'] : 0,
            'focal_y_max' => isset($fileMetaData['focal_y_max']) ? $fileMetaData['focal_y_max'] : 0,
            'debug' => 3,
        );

        // Return combined checksum
        return array_merge(
            parent::getChecksumData(),
            array(serialize($focusArea))
        );
    }
}
