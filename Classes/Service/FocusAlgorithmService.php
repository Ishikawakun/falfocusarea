<?php
namespace Ishikawakun\Falfocusarea\Service;

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

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FocusAlgorithmService implements SingletonInterface {
    /**
     * Orientation constants
     */
    const ORIENTATION_PORTRAIT = 0;
    const ORIENTATION_LANDSCAPE = 1;

    /**
     * @var \TYPO3\CMS\Frontend\Imaging\GifBuilder
     */
    protected $gifBuilder = NULL;

    /**
     * @var \TYPO3\CMS\Core\Log\Logger
     */
    protected $logger = NULL;

    /**
     * Calculate aspect ratio based on width and height.
     *
     * @param int $width
     * @param int $height
     *
     * @return array
     */
    protected function calcAspectRatio($width, $height) {
        $gcd = $this->gcd($width, $height);
        if ($gcd > 0) {
            return array($width / $gcd, $height / $gcd);
        }
        return array($width, $height);
    }

    /**
     * Calculate the greatest common divisor of a and b.
     *
     * @param int $a
     * @param int $b
     *
     * @return int
     */
    protected function gcd($a, $b) {
        return $b === 0 ? $a : $this->gcd($a, $a % $b);
    }

    /**
     * Load configuration and metadata, then process the configuration and calculate image constrains based on it
     *
     * @param string $originalFileName
     * @param File $sourceFile
     * @param File $targetFile
     * @param array $configuration
     * @param array $fileMetaData
     *
     * @return array
     */
    public function buildResult($originalFileName, $sourceFile, $targetFile, $configuration, $fileMetaData) {
        if ($this->gifBuilder === NULL) {
            $this->gifBuilder = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Imaging\\GifBuilder');
            $this->gifBuilder->init();
            $this->gifBuilder->absPrefix = PATH_site;
        }

        $debug_mode = FALSE;
        
        if ($debug_mode && $this->logger === NULL) {
            $this->logger = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Log\\LogManager')->getLogger(__CLASS__);
        }

        $width = 0;
        $height = 0;

        // Check focus area corners in metadata and fallback if necessary
        $focusArea = array(
            'focal_x_min' => isset($fileMetaData['focal_x_min']) ? $fileMetaData['focal_x_min'] : 0,
            'focal_x_max' => isset($fileMetaData['focal_x_max']) ? $fileMetaData['focal_x_max'] : 0,
            'focal_y_min' => isset($fileMetaData['focal_y_min']) ? $fileMetaData['focal_y_min'] : 0,
            'focal_y_max' => isset($fileMetaData['focal_y_max']) ? $fileMetaData['focal_y_max'] : 0,
        );

        // Calculate focus area width and height
        $focusArea['width'] = $focusArea['focal_x_max'] - $focusArea['focal_x_min'];
        $focusArea['height'] = $focusArea['focal_y_max'] - $focusArea['focal_y_min'];

        // Get file width and height from file if metadata is missing
        if ($width == 0 || $height == 0) {
            $imageDimensions = $this->gifBuilder->getImageDimensions($originalFileName);
            if ($imageDimensions != NULL) {
                $width = $imageDimensions[0];
                $height = $imageDimensions[1];
            }
        }

        $originalAspectRatio = $this->calcAspectRatio($width, $height);
        $focusAreaAspectRatio = $this->calcAspectRatio($focusArea['width'], $focusArea['height']);

        if (($configuration['width'] == 'auto' && $configuration['height'] == 'auto') || ((!isset($configuration['width']) && (!isset($configuration['height']))))) {
            if ($this->logger) {
                $this->logger->info(sprintf('CSM - Auto size fallback for image (%s)', $originalFileName));
            }

            // If both width and height are auto use original image size
            $configuration['width'] = $width;
            $configuration['height'] = $height;
        } else {
            if ($focusArea['width'] > 10 && $focusArea['height'] > 10) {
                if ($configuration['width'] !== 0 && $configuration ['height'] === 0) {
                    $configuration['height'] = (int)(($configuration['width'] / $focusAreaAspectRatio[0]) * $focusAreaAspectRatio[1]);
                } elseif ($configuration['width'] === 0 && $configuration['height'] !== 0) {
                    $configuration['width'] = (int)(($configuration['height'] / $focusAreaAspectRatio[1]) * $focusAreaAspectRatio[0]);
                }
            } else {
                if ($configuration['width'] !== 0 && $configuration ['height'] === 0) {
                    $configuration['height'] = (int)(($configuration['width'] / $originalAspectRatio[0]) * $originalAspectRatio[1]);
                } elseif ($configuration['width'] === 0 && $configuration['height'] !== 0) {
                    $configuration['width'] = (int)(($configuration['height'] / $originalAspectRatio[1]) * $originalAspectRatio[0]);
                }
            }
        }

        // Check with min/max width and height to calculate target parameters
        $configuration = $this->respectBoundaries($configuration);

        // Determine target scale and crop parameters
        if ($configuration['width'] !== 0 && $configuration['height'] !== 0) {
            $orientation = $originalAspectRatio[0] >= $originalAspectRatio[1] ? self::ORIENTATION_LANDSCAPE : self::ORIENTATION_PORTRAIT;

            $preferredScale = 1;
            if ($orientation == self::ORIENTATION_LANDSCAPE) {
                $preferredScale = $GLOBALS['TYPO3_CONF_VARS']['GFX']['preferredWidth'] * pow($width, -1);
            } elseif ($orientation == self::ORIENTATION_PORTRAIT) {
                $preferredScale = $GLOBALS['TYPO3_CONF_VARS']['GFX']['preferredHeight'] * pow($height, -1);
            }

            $scaleAndCrop = $this->findOptimalTargetScaleAndOffsets($preferredScale, $focusArea, $configuration, $width, $height, $orientation);

            $scaleWidth = (int)($scaleAndCrop['targetScale'] * $width);
            $scaleHeight = (int)($scaleAndCrop['targetScale'] * $height);

            return $this->executeImageMagickCropResize($originalFileName, $configuration['width'],
                $configuration['height'], $scaleWidth, $scaleHeight, $scaleAndCrop['offsetX'],
                $scaleAndCrop['offsetY']);
        } else {
            return array();
        }
    }

    /**
     * Determine a target scale for image resizing so that the focus area is fitted into the resulting image as best as
     * possible while strictly keeping to image size constraints as present in the configuration array.
     *
     * @param float $preferredScale
     * @param array $focusArea
     * @param array $configuration
     * @param int $sourceWidth
     * @param int $sourceHeight
     * @param int $orientation
     *
     * @return array
     */
    protected function findOptimalTargetScaleAndOffsets($preferredScale, $focusArea, $configuration, $sourceWidth, $sourceHeight, $orientation) {
        if ($focusArea['width'] > 10 && $focusArea['height'] > 10) {
            if ($orientation == self::ORIENTATION_PORTRAIT) {
                $targetScale = $configuration['width'] / $sourceWidth;
            } else {
                if (floor(($configuration['width'] / $focusArea['width']) * $focusArea['height']) <= $configuration['height']) {
                    $targetScale = $configuration['width'] / $focusArea['width'];
                } elseif (floor(($configuration['height'] / $focusArea['height']) * $focusArea['width']) <= $configuration['width']) {
                    $targetScale = $configuration['height'] / $focusArea['height'];
                } else {
                    $targetScale = min($configuration['width'] / $focusArea['width'], $configuration['height'] / $focusArea['height']);
                }
            }
        } else {
            if ($sourceWidth > 0 && $sourceHeight > 0) {
                $targetScale = max($configuration['width'] / $sourceWidth, $configuration['height'] / $sourceHeight);
            } else {
                $targetScale = $preferredScale;
            }
        }

        $offsetX = 0;
        // Focus width in target scale has to be greater than zero to determine an usable offset
        if ((int)($focusArea['width'] * $targetScale) > 0) {
            // Determine crop offset x
            $offsetX = $this->determineOffset($sourceWidth, $targetScale,
                $configuration['width'], $focusArea['focal_x_min'], $focusArea['focal_x_max']);
        }

        $offsetY = 0;
        // Focus height in target scale has to be greater than zero to determine an usable offset
        if ((int)($focusArea['height'] * $targetScale) > 0) {
            // Determine crop offset y
            $offsetY = $this->determineOffset($sourceHeight, $targetScale,
                $configuration['height'], $focusArea['focal_y_min'], $focusArea['focal_y_max']);
        }

        if ($this->logger) {
            $this->logger->info(sprintf('CSM - adjustments scale ("%f") offset x ("%d") offset y ("%d")', $targetScale, $offsetX, $offsetY));
        }

        return array('targetScale' => $targetScale, 'offsetX' => $offsetX, 'offsetY' => $offsetY);
    }

    /**
     * Try to position the focus area in the center of the generated image as best as possible
     *
     * @param int $sourceValue
     * @param float $targetScale
     * @param int $configurationValue
     * @param int $focusAreaMinValue
     * @param int $focusAreaMaxValue
     * @return int
     */
    protected function determineOffset($sourceValue, $targetScale, $configurationValue, $focusAreaMinValue, $focusAreaMaxValue) {
        // Determine non cropped image size on target scale
        $targetScaleImageValue = (int)($sourceValue * $targetScale);

        // Center position of focus area when image is scaled to target scale
        $focusAreaCenterValueScaled = ($focusAreaMinValue + (($focusAreaMaxValue - $focusAreaMinValue) * 0.5)) * $targetScale;

        // Determine offset position to try centering the focus area in the target scaled image
        $offset = $configurationValue + ($focusAreaCenterValueScaled - ($configurationValue * 0.5)) > $targetScaleImageValue ?
            max(0, $targetScaleImageValue - $configurationValue) :
            max(0, $focusAreaCenterValueScaled - ($configurationValue * 0.5));

        return $offset;
    }

    /**
     * Check configuration for compliance with maxW, minW, maxH, minH parameters
     *
     * @param array $configuration the configuration array of the processing task
     * @return array processed configuration
     */
    protected function respectBoundaries($configuration) {
        // Respect boundary configuration for width
        $configuration['width'] = isset($configuration['maxW']) ?
            $configuration['width'] > $configuration['maxW'] ? 
                min($configuration['width'], $configuration['maxW']) : 
                isset($configuration['minW']) ? max($configuration['width'], $configuration['minW']) : $configuration['width'] :
            isset($configuration['minW']) ? max($configuration['width'], $configuration['minW']) : $configuration['width'];

        // Respect boundary configuration for height
        $configuration['height'] = isset($configuration['maxH']) ?
            $configuration['height'] > $configuration['maxH'] ?
                min($configuration['height'], $configuration['maxH']) :
                isset($configuration['minH']) ? max($configuration['height'], $configuration['minH']) : $configuration['height'] :
            isset($configuration['minH']) ? max($configuration['height'], $configuration['minH']) : $configuration['height'];

        return $configuration;
    }

    /**
     * This function takes the determined target, scale and crop values to generate the final image for the frontend.
     *
     * This happens in two steps, first the input image is resize it to the determined scale after which it is blended
     * with a transparent background mask and resize it to the target image size if target scale size and final image
     * size differ.
     *
     * @param string $input The relative (to PATH_site) image file path, input file (read from)
     * @param int $targetWidth final image width after processing
     * @param int $targetHeight final image height after processing
     * @param int $scaleWidth image width at target scale
     * @param int $scaleHeight image height at target scale
     * @param int $offsetX x-axis offset where cropping of image should start
     * @param int $offsetY y-axis offset where cropping of image should start
     *
     * @return array
     */
    protected function executeImageMagickCropResize($input, $targetWidth, $targetHeight, $scaleWidth, $scaleHeight, $offsetX, $offsetY) {
        // Command for generating the temporary image file (only resizing the input image)
        $params = '-resize "' . $scaleWidth . 'x' . $scaleHeight . '^" -crop "' . $targetWidth . 'x' . $targetHeight . '+' . $offsetX . '+' . $offsetY . '" +repage';

        /**
         * Adaption of code from TYPO3\CMS\Core\Imaging\GraphicalFunctions->imageMagickConvert()
         */
        if ($this->gifBuilder->NO_IMAGE_MAGICK) {
            // Returning file info right away
            return $this->gifBuilder->getImageDimensions($input);
        }
        if ($info = $this->gifBuilder->getImageDimensions($input)) {
            // Determine final image file extension
            $newExt = $info[2];
            if ($newExt == 'web') {
                if (GeneralUtility::inList($this->gifBuilder->webImageExt, $info[2])) {
                    $newExt = $info[2];
                } else {
                    $newExt = $this->gifBuilder->gif_or_jpg($info[2], $info[0], $info[1]);
                }
            }
            if (GeneralUtility::inList($this->gifBuilder->imageFileExt, $newExt)) {
                if ($this->gifBuilder->alternativeOutputKey) {
                    $theOutputName = GeneralUtility::shortMD5($params . basename($input) . $this->gifBuilder->alternativeOutputKey . '[' . 0 . ']');
                } else {
                    $theOutputName = GeneralUtility::shortMD5($params . $input . filemtime($input) . '[' . 0 . ']');
                }
                $this->gifBuilder->createTempSubDir('pics/');
                $this->gifBuilder->createTempSubDir('pics/temp/');
                // Real output file
                $output = $this->gifBuilder->absPrefix . $this->gifBuilder->tempPath . 'pics/' . $this->gifBuilder->filenamePrefix . $theOutputName . '.' . $newExt;
                // Register temporary filename:
                $GLOBALS['TEMP_IMAGES_ON_PAGE'][] = $output;
                if ($this->gifBuilder->dontCheckForExistingTempFile || !$this->gifBuilder->file_exists_typo3temp_file($output, $input)) {
                    $ret = $this->gifBuilder->imageMagickExec($input, $output, $params, 0);
                    if ($this->logger) {
                        $this->logger->info(sprintf('CSM - Execute command (%s) returned (%s) input (%s) output (%s)', $params, $ret, $input, $output));
                    }
                }
                if (file_exists($output)) {
                    $info[3] = $output;
                    $info[2] = $newExt;
                    // params could actually change some image data!
                    if ($params) {
                        $info = $this->gifBuilder->getImageDimensions($info[3]);
                    }
                    if ($info[2] == $this->gifBuilder->gifExtension && !$this->gifBuilder->dontCompress) {
                        // Compress with IM (lzw) or GD (rle)  (Workaround for the absence of lzw-compression in GD)
                        GeneralUtility::gif_compress($info[3], '');
                    }
                    return $info;
                }
            }
        }
        return NULL;
    }
}
