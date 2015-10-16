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

use Ishikawakun\Falfocusarea\Utility\LogUtility;
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
     * @var bool
     */
    protected $forcePng = FALSE;

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
     * TODO: comments
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

        $this->forcePng = TRUE;

        $debug_mode = FALSE;
        
        if ($debug_mode && $this->logger === NULL) {
            $this->logger = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Log\\LogManager')->getLogger(__CLASS__);
        }

        $width = 0;
        $height = 0;

        // Backup original configuration settings for later checks
        $originalConfiguration = $configuration;

        // Check focus area corners in metadata and fallback if necessary
        $focusArea = array(
            'focal_x_min' => isset($fileMetaData['focal_x_min']) ? $fileMetaData['focal_x_min'] : 0,
            'focal_x_max' => isset($fileMetaData['focal_x_max']) ? $fileMetaData['focal_x_max'] : 0,
            'focal_y_min' => isset($fileMetaData['focal_y_min']) ? $fileMetaData['focal_y_min'] : 0,
            'focal_y_max' => isset($fileMetaData['focal_y_max']) ? $fileMetaData['focal_y_max'] : 0,
        );

        // Get file width and height from file if metadata is missing
        if ($width == 0 || $height == 0) {
            $imageDimensions = $this->gifBuilder->getImageDimensions($originalFileName);
            if ($imageDimensions != NULL) {
                $width = $imageDimensions[0];
                $height = $imageDimensions[1];
            }
        }

        // Calculate focus area side lengths
        $focusArea['width'] = $focusArea['focal_x_max'] - $focusArea['focal_x_min'];
        $focusArea['height'] = $focusArea['focal_y_max'] - $focusArea['focal_y_min'];

        // Check if focus area is usable (arbitrary minimal condition) and use fallback mode if necessary
        if ($focusArea['width'] <= 10 || $focusArea['height'] <= 10) {
            $originalAspectRatio = $this->calcAspectRatio($width, $height);

            // Check configuration width and height for usability
            if (($configuration['width'] == 'auto' && $configuration['height'] == 'auto') || ((!isset($configuration['width']) && (!isset($configuration['height']))))) {
                if ($this->logger) {
                    $this->logger->info(sprintf('CSM - Auto size fallback for image (%s)', $originalFileName));
                }
                // If both width and height are auto use original image size
                $configuration['width'] = $width;
                $configuration['height'] = $height;
            } else {
                // Check for missing resize info and calculate it based on aspect ratio of the image
                if ($configuration['width'] !== 0 && $configuration ['height'] === 0) {
                    $configuration['height'] = (int)($configuration['width'] / $originalAspectRatio[0]) * $originalAspectRatio[1];
                } elseif ($configuration['width'] === 0 && $configuration['height'] !== 0) {
                    $configuration['width'] = (int)($configuration['height'] / $originalAspectRatio[1]) * $originalAspectRatio[0];
                }
            }

            // Don't use full image size as focus area fallback if size is greater threshold
            if ($configuration['width'] > 400 && $configuration['height'] > 400) {
                // Create full focus area for fallback
                $focusArea = array(
                    'focal_x_min' => 0,
                    'focal_x_max' => $width,
                    'focal_y_min' => 0,
                    'focal_y_max' => $height,
                );
            } else {
                $focusArea = array(
                    'focal_x_min' => max(0, (int)(($width * 0.5) - ($configuration['width'] * 0.5))),
                    'focal_x_max' => min($width, (int)(($width * 0.5) + ($configuration['width'] * 0.5))),
                    'focal_y_min' => max(0, (int)(($height * 0.5) - ($configuration['height'] * 0.5))),
                    'focal_y_max' => min($height, (int)(($height * 0.5) + ($configuration['height'] * 0.5))),
                );
            }

            // Re-Calculate focus area side lengths
            $focusArea['width'] = $focusArea['focal_x_max'] - $focusArea['focal_x_min'];
            $focusArea['height'] = $focusArea['focal_y_max'] - $focusArea['focal_y_min'];
        } else {
            // Interpret rescaling case based on configuration data
            $originalAspectRatio = $this->calcAspectRatio($width, $height);

            if (($configuration['width'] == 'auto' && $configuration['height'] == 'auto') || ((!isset($configuration['width']) && (!isset($configuration['height']))))) {
                if ($this->logger) {
                    $this->logger->info(sprintf('CSM - Auto size fallback for image (%s)', $originalFileName));
                }
                $configuration['width'] = $width;
                $configuration['height'] = $height;
            }

            // Check for missing resize info and calculate it based on aspect ratio of the image
            if ($configuration['width'] !== 0 && $configuration ['height'] === 0) {
                $configuration['height'] = ($configuration['width'] / $originalAspectRatio[0]) * $originalAspectRatio[1];
            } elseif ($configuration['width'] === 0 && $configuration['height'] !== 0) {
                $configuration['width'] = ($configuration['height'] / $originalAspectRatio[1]) * $originalAspectRatio[0];
            }
        }

        // Check with min/max width and height to calculate target parameters
        $configuration = $this->respectBoundaries($configuration);

        // Determine target scale and crop parameters
        if ($configuration['width'] !== 0 && $configuration['height'] !== 0) {
            $orientation = $originalAspectRatio[0] >= $originalAspectRatio[1] ? self::ORIENTATION_LANDSCAPE : self::ORIENTATION_PORTRAIT;

            $preferredScale = 1;
            if ($orientation == self::ORIENTATION_LANDSCAPE) {
                $preferredScale = $configuration['width'] * pow($width, -1);
            } elseif ($orientation == self::ORIENTATION_PORTRAIT) {
                $preferredScale = $configuration['height'] * pow($height, -1);
            }

            $scaleAndCrop = $this->findOptimalTargetScaleAndOffsets($preferredScale, $focusArea, $configuration, $width, $height, $orientation);

            $scaleWidth = (int)($scaleAndCrop['targetScale'] * $width);
            $scaleHeight = (int)($scaleAndCrop['targetScale'] * $height);

            // Recheck auto configuration options for width and height
            if ($originalConfiguration['width'] == 'auto') {
                $configuration['width'] = $scaleWidth;
                // Redetermine if max and min constraints are ensured
                $this->respectBoundaries($configuration);
            } else if ($originalConfiguration['height'] == 'auto') {
                $configuration['height'] = $scaleHeight;
                // Redetermine if max and min constraints are ensured
                $this->respectBoundaries($configuration);
            }

            // Determine wether a transparent mask is needed for target image based on current configuration
            if ($scaleWidth < $configuration['width']) {
                $cropWidth = $scaleWidth;
                $this->forcePng = TRUE;
            } else {
                $cropWidth = $configuration['width'];
            }
            if ($scaleHeight < $configuration['height']) {
                $cropHeight = $scaleHeight;
                $this->forcePng = TRUE;
            } else {
                $cropHeight = $configuration['height'];
            }

            return $this->executeImageMagickCropResize($originalFileName, $configuration['width'],
                $configuration['height'], $scaleWidth, $scaleHeight, $cropWidth, $cropHeight, $scaleAndCrop['offsetX'],
                $scaleAndCrop['offsetY']);
        } else {
            return array();
        }
    }

    /**
     * TODO: comments
     *
     * @param $scale
     * @param $configuration
     */
    protected function scaleFillsConfiguration($scale, $configuration, $sourceWidth, $sourceHeight) {
        if ((int)($scale * $sourceWidth) >= $configuration['width'] &&
            (int)($scale * $sourceHeight) >= $configuration['height']) {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * TODO: comments
     *
     * @param array $preferredScale
     * @param array $focusArea
     * @param array $configuration
     * @param int $orientation
     *
     * @return array
     */
    protected function findOptimalTargetScaleAndOffsets($preferredScale, $focusArea, $configuration, $sourceWidth, $sourceHeight, $orientation) {
        if ($focusArea['width'] > 10 && $focusArea['height'] > 10) {
            $targetScale = min($configuration['width'] / $focusArea['width'], $configuration['height'] / $focusArea['height']);
        } else {
            $targetScale = $preferredScale;
        }

        // Prefer preferred scale IF it leaves no transparent areas
        if ($preferredScale < $targetScale && $this->scaleFillsConfiguration($preferredScale, $configuration, $sourceWidth, $sourceHeight)) {
            $targetScale = $preferredScale;
        }

        // Determine focus size on target scale
        $targetFocusWidth = (int)($focusArea['width'] * $targetScale);
        $targetFocusHeight = (int)($focusArea['height'] * $targetScale);

        // Determine non cropped image size on target scale
        $targetScaleImageWidth = (int)($sourceWidth * $targetScale);
        $targetScaleImageHeight = (int)($sourceHeight * $targetScale);

        $offsetX = 0;
        if ($targetFocusWidth > 0) {
            // Determine crop offset x
            $offsetX = $this->determineOffset('x-axis', $targetFocusWidth, $targetScaleImageWidth, $targetScale,
                $sourceWidth, $configuration['width'], $focusArea['focal_x_min'], $focusArea['focal_x_max']);
        }

        $offsetY = 0;
        if ($targetFocusHeight > 0) {
            // Determine crop offset y
            $offsetY = $this->determineOffset('y-axis', $targetFocusHeight, $targetScaleImageHeight, $targetScale,
                $sourceHeight, $configuration['height'], $focusArea['focal_y_min'], $focusArea['focal_y_max']);
        }

        if ($this->logger) {
            $this->logger->info(sprintf('CSM - adjustments scale ("%f") offset x ("%d") offset y ("%d")', $targetScale, $offsetX, $offsetY));
        }

        return array('targetScale' => $targetScale, 'offsetX' => $offsetX, 'offsetY' => $offsetY);
    }

    /**
     * TODO: comments
     *
     * @param string $axisName
     * @param int $targetFocusValue
     * @param int $targetScaleImageValue
     * @param float $targetScale
     * @param int $sourceValue
     * @param int $configurationValue
     * @param int $focusAreaMinValue
     * @param int $focusAreaMaxValue
     * @return int
     */
    protected function determineOffset($axisName, $targetFocusValue, $targetScaleImageValue, $targetScale, $sourceValue, $configurationValue, $focusAreaMinValue, $focusAreaMaxValue) {
        $offset = 0;
        if ($targetFocusValue < $configurationValue) {
            $availableBuffer = max(0, $targetScaleImageValue - $targetFocusValue);
            $neededBuffer = max(0, $configurationValue - $targetFocusValue);
            $firstMarginScale = (int)(($sourceValue - $focusAreaMaxValue) * $targetScale);
            $secondMarginScale = (int)(($sourceValue - $focusAreaMinValue) * $targetScale);

            if ($neededBuffer === 0) {
                return (int)($focusAreaMinValue * $targetScale);
            }
    
            if ($availableBuffer >= $neededBuffer) {
                $preferredMargin = ((int)($neededBuffer / 2));
                if ($preferredMargin <= $firstMarginScale && $preferredMargin <= $secondMarginScale) {
                    $offset = max(0, ((int)($focusAreaMinValue * $targetScale)) - $preferredMargin);
                } else {
                    // TODO: Better case distinction
                    if ($preferredMargin <= $firstMarginScale && $preferredMargin > $secondMarginScale) {
                        $missingMargin = $preferredMargin - $secondMarginScale;
                        if ($preferredMargin + $missingMargin <= $firstMarginScale) {
                            $offset = ((int)($focusAreaMinValue * $targetScale));
                        } else {
                            // Margin exception
                        }
                    } elseif ($preferredMargin > $firstMarginScale && $preferredMargin <= $secondMarginScale) {
                        $missingMargin = $preferredMargin - $firstMarginScale;
                        if ($preferredMargin + $missingMargin <= $secondMarginScale) {
                            $offset = max(0, ((int)($focusAreaMinValue * $targetScale)) - ($preferredMargin + $missingMargin));
                        } else {
                            // Margin exception
                        }
                    } else {
                        // Margin exception
                    }
                }
            } else {
                // Buffer not enough
            }
        } elseif ($targetFocusValue == $configurationValue) {
            return (int)($focusAreaMinValue * $targetScale);
        }
        return $offset;
    }

    /**
     * Check configuration for compliance with maxW, minW, maxH, minH parameters
     *
     * @param array $configuration the configuration array of the processing task
     * @return array processed configuration
     */
    protected function respectBoundaries($configuration) {
        $configuration = $this->respectBoundariesBySide($configuration, 'width');
        $configuration = $this->respectBoundariesBySide($configuration, 'height');

        return $configuration;
    }

    /**
     * Check limits for the specified side in the configuration.
     *
     * @param array $configuration the configuration array of the processing task
     * @param string $sideLong either "width" or "height"
     * @param null|string $sideOther short descriptor used in configuration array
     *
     * @return array processed configuration
     */
    protected function respectBoundariesBySide($configuration, $sideLong, $sideOther = NULL) {
        // Determine short descriptor for side used in configuration
        if ($sideOther === NULL) {
            $sideOther = ucfirst($sideLong);
        }

        // Ensure minimum and maximum values are respected
        if (isset($configuration[$sideLong]) && $configuration[$sideLong] > 0) {
            if (isset($configuration['min' . $sideOther]) && $configuration['min' . $sideOther] > 0) {
                $configuration[$sideLong] = $configuration[$sideLong] >= $configuration['min' . $sideOther] ? $configuration[$sideLong] : $configuration['min' . $sideOther];
            }

            if (isset($configuration['max' . $sideOther]) && $configuration['max' . $sideOther] > 0) {
                $configuration[$sideLong] = $configuration[$sideLong] <= $configuration['max' . $sideOther] ? $configuration[$sideLong] : $configuration['max' . $sideOther];
            }
        } else {
            if (isset($configuration['min' . $sideOther]) && $configuration['min' . $sideOther] > 0) {
                $configuration[$sideLong] = $configuration['min' . $sideOther];
            }

            if (isset($configuration['max' . $sideOther]) && $configuration['max' . $sideOther] > 0) {
                $configuration[$sideLong] = $configuration['max' . $sideOther];
            }
        }

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
     * @param int $cropWidth image width after cropping
     * @param int $cropHeight image height after cropping
     * @param int $cropOffsetX x-axis offset where cropping of image should start
     * @param int $cropOffsetY y-axis offset where cropping of image should start
     *
     * @return array
     */
    protected function executeImageMagickCropResize($input, $targetWidth, $targetHeight, $scaleWidth, $scaleHeight, $cropWidth, $cropHeight, $cropOffsetX, $cropOffsetY) {
        // Command for generating the temporary image file (only resizing the input image)
        $paramsTemp = '-resize "' . $scaleWidth . 'x' . $scaleHeight . '^" -crop ' . $cropWidth . 'x' . $cropHeight . '+' . $cropOffsetX . '+' . $cropOffsetY . ' +repage';
        // Command for generating the final image file with the transparent background mask
        $paramsFinal = '-resize "' . $targetWidth . 'x' . $targetHeight . '" -background transparent -gravity center -extent "' . $targetWidth . 'x' . $targetHeight . '"';

        // Concatenate both command parameters for hash generation
        $params = $paramsTemp . $paramsFinal;

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
            if ($this->forcePng && $newExt !== 'png') {
                $newExt = 'png';
            }
            // If file extension is allowed generate the temporary and final image using imagemagick or gdlib as
            // configured in $TYPO3_CONF_VARS['GFX']
            if (GeneralUtility::inList($this->gifBuilder->imageFileExt, $newExt)) {
                if ($this->gifBuilder->alternativeOutputKey) {
                    $theOutputName = GeneralUtility::shortMD5($params . basename($input) . $this->gifBuilder->alternativeOutputKey . '[' . 0 . ']');
                } else {
                    $theOutputName = GeneralUtility::shortMD5($params . $input . filemtime($input) . '[' . 0 . ']');
                }
                $this->gifBuilder->createTempSubDir('pics/');
                $this->gifBuilder->createTempSubDir('pics/temp/');
                // Temp output file
                $tempFileName = $this->gifBuilder->absPrefix . $this->gifBuilder->tempPath . 'pics/temp/' . $this->gifBuilder->filenamePrefix . $theOutputName . '.' . $newExt;
                if ($this->gifBuilder->dontCheckForExistingTempFile || !$this->gifBuilder->file_exists_typo3temp_file($tempFileName, $input)) {
                    $status = $this->gifBuilder->imageMagickExec($input, $tempFileName, $paramsTemp, 0);
                    if ($this->logger) {
                        $this->logger->info(sprintf('CSM - Execute command (%s) returned (%s) input (%s) output (%s)', $paramsTemp, $status, $input, $tempFileName));
                    }
                }
                // Real output file
                $output = $this->gifBuilder->absPrefix . $this->gifBuilder->tempPath . 'pics/' . $this->gifBuilder->filenamePrefix . $theOutputName . '.' . $newExt;
                // Register temporary filename:
                $GLOBALS['TEMP_IMAGES_ON_PAGE'][] = $output;
                if ($this->gifBuilder->dontCheckForExistingTempFile || !$this->gifBuilder->file_exists_typo3temp_file($output, $input)) {
                    $ret = $this->gifBuilder->imageMagickExec($tempFileName, $output, $paramsFinal, 0);
                    if ($this->logger) {
                        $this->logger->info(sprintf('CSM - Execute command (%s) returned (%s) input (%s) output (%s)', $paramsFinal, $ret, $tempFileName, $output));
                    }
                }
                if (file_exists($tempFileName)) {
                    unlink($tempFileName);
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