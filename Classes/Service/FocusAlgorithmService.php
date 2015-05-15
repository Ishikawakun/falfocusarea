<?php
namespace Ishikawakun\Falfocusarea\Service;

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
     * @var \TYPO3\CMS\Frontend\Imaging\GifBuilder
     */
    protected $gifBuilder = NULL;

    /**
     * @var \TYPO3\CMS\Core\Log\Logger
     */
    protected $logger = NULL;

    /**
     * @param int $width
     * @param int $height
     * @return array
     */
    protected function calcAspectRatio($width, $height) {
        $gcd = $this->gcd($width, $height);
        return array($width / $gcd, $height / $gcd);
    }

    /**
     * @param int $a
     * @param int $b
     * @return int
     */
    protected function gcd($a, $b) {
        return $b === 0 ? $a : $this->gcd($a, $a % $b);
    }

    /**
     * @param string $originalFileName
     * @param File $sourceFile
     * @param File $targetFile
     * @param array $configuration
     * @param array $fileMetaData
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

        // Check meta data width and height
        if (!isset($fileMetaData['width']) || !isset($fileMetaData['height'])) {
            if ($this->logger) {
                // Generate meta data debug output
                $width = isset($fileMetaData['width']) ? $fileMetaData['width'] : 0;
                $height = isset($fileMetaData['width']) ? $fileMetaData['height'] : 0;
                $this->logger->info(sprintf('CSM - Size information of image "%s" missing (w: %d, h: %d)', $originalFileName, $width, $height));
            }
        }

        // Check focus area corners in metadata and fallback if necessary
        $focusArea = array(
            'focal_x_min' => isset($fileMetaData['focal_x_min']) ? $fileMetaData['focal_x_min'] : 0,
            'focal_x_max' => isset($fileMetaData['focal_x_max']) ? $fileMetaData['focal_x_max'] : 0,
            'focal_y_min' => isset($fileMetaData['focal_y_min']) ? $fileMetaData['focal_y_min'] : 0,
            'focal_y_max' => isset($fileMetaData['focal_y_max']) ? $fileMetaData['focal_y_max'] : 0,
        );

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

        // Check if focus area is usable (arbitrary minimal condition)
        if (FALSE && $focusArea['width'] <= 10 || $focusArea['height'] <= 10) {
            if ($this->logger) {
                $this->logger->info(sprintf('CSM - Focus area fallback for image "%s"', $originalFileName));
            }

            // Fallback if necessary

            // TODO: design fallback mechanism (weighted something something)
            //                              OR
            // http://research.microsoft.com/en-us/um/people/kopf/downscaling/paper/pseudocode.pdf
        } else {
            // Interpret rescaling case based on configuration data
            $originalAspectRatio = $this->calcAspectRatio($width, $height);

            // Check for missing resize info and calculate it based on aspect ratio of the image
            if ($configuration['width'] !== 0 && $configuration ['height'] === 0) {
                $configuration['height'] = ($configuration['width'] / $originalAspectRatio[0]) * $originalAspectRatio[1];
            } elseif ($configuration['width'] === 0 && $configuration['height'] !== 0) {
                $configuration['width'] = ($configuration['height'] / $originalAspectRatio[1]) * $originalAspectRatio[0];
            }

            // Determine target scale and crop parameters
            if ($configuration['width'] !== 0 && $configuration['height'] !== 0) {
                $targetAspectRatio = $this->calcAspectRatio($configuration['width'], $configuration['height']);

                $orientation = $originalAspectRatio[0] > $originalAspectRatio[1] ? self::ORIENTATION_LANDSCAPE : self::ORIENTATION_PORTRAIT;

                $preferredScale = 1;
                if (isset($GLOBALS['TYPO3_CONF_VARS']['GFX']['prefferedWidth']) && $GLOBALS['TYPO3_CONF_VARS']['GFX']['prefferedWidth'] > 0) {
                    if ($orientation == self::ORIENTATION_LANDSCAPE) {
                        $preferredScale = $GLOBALS['TYPO3_CONF_VARS']['GFX']['prefferedWidth'] / $width;
                    } elseif ($orientation == self::ORIENTATION_PORTRAIT) {
                        $preferredScale = $GLOBALS['TYPO3_CONF_VARS']['GFX']['prefferedWidth'] / $height;
                    }
                }

                $scaleAndCrop = $this->findOptimalTargetScaleAndOffsets($preferredScale, $focusArea, $configuration, $width, $height);

                return $this->executeImageMagickCropResize($originalFileName, (int)($scaleAndCrop['targetScale'] * $width),
                    (int)($scaleAndCrop['targetScale'] * $height), $configuration['width'], $configuration['height'],
                    $scaleAndCrop['offsetX'], $scaleAndCrop['offsetY']);
            }
        }
    }

    /**
     * @param array $preferredScale
     * @param array $focusArea
     * @param array $configuration
     *
     * @return array
     */
    protected function findOptimalTargetScaleAndOffsets($preferredScale, $focusArea, $configuration, $sourceWidth, $sourceHeight) {
        $focusAreaAspectRatio = $this->calcAspectRatio($focusArea['width'], $focusArea['height']);

        $targetScale = min($configuration['width'] / $focusArea['width'], $configuration['height'] / $focusArea['height']);

        // Prefer preferred scale
        if ($preferredScale < $targetScale) {
            $targetScale = $preferredScale;
        }

        // Determine focus size on target scale
        $targetFocusWidth = (int)($focusArea['width'] * $targetScale);
        $targetFocusHeight = (int)($focusArea['height'] * $targetScale);

        // Determine non cropped image size on target scale
        $targetScaleImageWidth = (int)($sourceWidth * $targetScale);
        $targetScaleImageHeight = (int)($sourceHeight * $targetScale);

        // Determine crop offset x
        $offsetX = $this->determineOffset('x-axis', $targetFocusWidth, $targetScaleImageWidth, $targetScale,
            $sourceWidth, $configuration['width'], $focusArea['focal_x_min'], $focusArea['focal_x_max']);

        // Determine crop offset y
        $offsetY = $this->determineOffset('y-axis', $targetFocusHeight, $targetScaleImageHeight, $targetScale,
            $sourceHeight, $configuration['height'], $focusArea['focal_y_min'], $focusArea['focal_y_max']);

        if ($this->logger) {
            $this->logger->info(sprintf('CSM - adjustments' . PHP_EOL .  'scale ("%f") offset x ("%d") offset y ("%d")', $targetScale, $offsetX, $offsetY));
        }

        return array('targetScale' => $targetScale, 'offsetX' => $offsetX, 'offsetY' => $offsetY);
    }

    /**
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
                    // TODO: Case distinction
                    if ($preferredMargin <= $firstMarginScale && $preferredMargin > $secondMarginScale) {
                        $missingMargin = $preferredMargin - $secondMarginScale;
                        if ($preferredMargin + $missingMargin <= $firstMarginScale) {
                            $offset = ((int)($focusAreaMinValue * $targetScale));
                        } else {
                            if ($this->logger) {
                                $this->logger->info(sprintf('CSM - Preferred margin exception %s both values!', $axisName));
                            }
                        }
                    } elseif ($preferredMargin > $firstMarginScale && $preferredMargin <= $secondMarginScale) {
                        $missingMargin = $preferredMargin - $firstMarginScale;
                        if ($preferredMargin + $missingMargin <= $secondMarginScale) {
                            $offset = max(0, ((int)($focusAreaMinValue * $targetScale)) - ($preferredMargin + $missingMargin));
                        } else {
                            if ($this->logger) {
                                $this->logger->info(sprintf('CSM - Preferred margin exception %s both values!', $axisName));
                            }
                        }
                    } else {
                        if ($this->logger) {
                            $this->logger->info(sprintf('CSM - Preferred margin exception %s both values!', $axisName));
                        }
                    }
                }
            } else {
                if ($this->logger) {
                    $this->logger->info(sprintf('CSM - Available buffer not enough %s!', $axisName));
                }
            }
        } elseif ($targetFocusValue == $configurationValue) {
            return (int)($focusAreaMinValue * $targetScale);
        }
        return $offset;
    }

    /**
     * @param string $input The relative (to PATH_site) image filepath, input file (read from)
     * @param string $output The relative (to PATH_site) image filepath, output filename (written to)
     * @param int $scaleWidth
     * @param int $scaleHeight
     * @param int $cropWidth
     * @param int $cropHeight
     * @param int $cropOffsetX
     * @param int $cropOffsetY
     *
     * @return array
     */
    protected function executeImageMagickCropResize($input, $scaleWidth, $scaleHeight, $cropWidth, $cropHeight, $cropOffsetX, $cropOffsetY) {
        $params = '-resize "' . $scaleWidth . 'x' . $scaleHeight . '^" -crop ' . $cropWidth . 'x' . $cropHeight . '+' . $cropOffsetX . '+' . $cropOffsetY . ' +repage';

        /**
         * Copied code from TYPO3\CMS\Core\Imaging\GraphicalFunctions->imageMagickConvert()
         */
        if ($this->gifBuilder->NO_IMAGE_MAGICK) {
            // Returning file info right away
            return $this->gifBuilder->getImageDimensions($input);
        }
        if ($info = $this->gifBuilder->getImageDimensions($input)) {
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
                $output = $this->gifBuilder->absPrefix . $this->gifBuilder->tempPath . 'pics/' . $this->gifBuilder->filenamePrefix . $theOutputName . '.' . $newExt;
                // Register temporary filename:
                $GLOBALS['TEMP_IMAGES_ON_PAGE'][] = $output;
                if ($this->gifBuilder->dontCheckForExistingTempFile || !$this->gifBuilder->file_exists_typo3temp_file($output, $input)) {
                    $this->gifBuilder->imageMagickExec($input, $output, $params, 0);
                }
                if (file_exists($output)) {
                    $info[3] = $output;
                    $info[2] = $newExt;
                    // params could realisticly change some imagedata!
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