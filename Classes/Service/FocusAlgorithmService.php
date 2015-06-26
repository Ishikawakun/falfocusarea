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
        if ($gcd > 0) {
            return array($width / $gcd, $height / $gcd);
        }
        return array($width, $height);
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

        // Check if focus area is usable (arbitrary minimal condition)
        if ($focusArea['width'] <= 10 || $focusArea['height'] <= 10) {
            $originalAspectRatio = $this->calcAspectRatio($width, $height);

            if (($configuration['width'] == 'auto' && $configuration['height'] == 'auto') || (!isset($configuration['width']) && !isset($configuration['height']))) {
                if ($this->logger) {
                    $this->logger->info(sprintf('CSM - Auto size fallback for image (%s)', $originalFileName));
                }
                $configuration['width'] = $width;
                $configuration['height'] = $height;
            } else {
                // Check for missing resize info and calculate it based on aspect ratio of the image
                if ($configuration['width'] !== 0 && $configuration ['height'] === 0) {
                    $configuration['height'] = ($configuration['width'] / $originalAspectRatio[0]) * $originalAspectRatio[1];
                } elseif ($configuration['width'] === 0 && $configuration['height'] !== 0) {
                    $configuration['width'] = ($configuration['height'] / $originalAspectRatio[1]) * $originalAspectRatio[0];
                }

                // Check with min/max width and height to calculate target parameters
                $configuration = $this->respectBoundaries($configuration);
            }

            // Only continue if TRUE resizing happens
            if ($configuration['width'] == $width && $configuration['height'] == $height) {
                return;
            }

            if ($configuration['width'] !== 0 && $configuration['height'] !== 0) {
                return $this->executeImageMagickCropResize($originalFileName, $configuration['width'], $configuration['height'], $configuration['width'],
                    $configuration['height'], $configuration['width'], $configuration['height'], 0, 0);
            }
        } else {
            // Interpret rescaling case based on configuration data
            $originalAspectRatio = $this->calcAspectRatio($width, $height);

            if (($configuration['width'] == 'auto' && $configuration['height'] == 'auto') || (!isset($configuration['width']) && !isset($configuration['height']))) {
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

            // Check with min/max width and height to calculate target parameters
            $configuration = $this->respectBoundaries($configuration);

            // Determine target scale and crop parameters
            if ($configuration['width'] !== 0 && $configuration['height'] !== 0) {
                $orientation = $originalAspectRatio[0] >= $originalAspectRatio[1] ? self::ORIENTATION_LANDSCAPE : self::ORIENTATION_PORTRAIT;

                $preferredScale = 1;
                if ($orientation == self::ORIENTATION_LANDSCAPE) {
                    $preferredScale = $configuration['width'] / $width;
                } elseif ($orientation == self::ORIENTATION_PORTRAIT) {
                    $preferredScale = $configuration['height'] / $height;
                }

                $scaleAndCrop = $this->findOptimalTargetScaleAndOffsets($preferredScale, $focusArea, $configuration, $width, $height);

                return $this->executeImageMagickCropResize($originalFileName, $configuration['width'], $configuration['height'], (int)($scaleAndCrop['targetScale'] * $width),
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
        if ($focusArea['width'] > 10 && $focusArea['height'] > 10) {
            $targetScale = min($configuration['width'] / $focusArea['width'], $configuration['height'] / $focusArea['height']);
        } else {
            $targetScale = $preferredScale;
        }

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
     * @param array $configuration
     * @return array
     */
    protected function respectBoundaries($configuration) {
        $configuration = $this->respectBoundariesBySide($configuration, 'width');
        $configuration = $this->respectBoundariesBySide($configuration, 'height');

        return $configuration;
    }

    /**
     * @param array $configuration
     * @param string $sideLong
     * @param string $sideShort
     * @return array
     */
    protected function respectBoundariesBySide($configuration, $sideLong, $sideOther = NULL) {
        if ($sideOther === NULL) {
            $sideOther = ucfirst($sideLong);
        }

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
    protected function executeImageMagickCropResize($input, $targetWidth, $targetHeight, $scaleWidth, $scaleHeight, $cropWidth, $cropHeight, $cropOffsetX, $cropOffsetY) {
        $paramsTemp = '-resize "' . $scaleWidth . 'x' . $scaleHeight . '^" -crop ' . $cropWidth . 'x' . $cropHeight . '+' . $cropOffsetX . '+' . $cropOffsetY . ' +repage';
        $paramsFinal = '-resize "' . $targetWidth . 'x' . $targetHeight . '" -background transparent -gravity center -extent "' . $targetWidth . 'x' . $targetHeight . '"';

        $params = $paramsTemp . $paramsFinal;

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
                $this->gifBuilder->createTempSubDir('pics/temp/');
                // Temp output file
                $tempFileName = $this->gifBuilder->absPrefix . $this->gifBuilder->tempPath . 'pics/temp/' . $this->gifBuilder->filenamePrefix . $theOutputName . '.' . $newExt;
                if ($this->gifBuilder->dontCheckForExistingTempFile || !$this->gifBuilder->file_exists_typo3temp_file($tempFileName, $input)) {
                    $status = $this->gifBuilder->imageMagickExec($input, $tempFileName, $paramsTemp, 0);
                    if ($this->logger) {
                        $this->logger->info(sprintf('CSM - Execute (%s) returned (%s)', $paramsTemp, $status));
                    }
                }
                // Real output file
                $output = $this->gifBuilder->absPrefix . $this->gifBuilder->tempPath . 'pics/' . $this->gifBuilder->filenamePrefix . $theOutputName . '.' . $newExt;
                // Register temporary filename:
                $GLOBALS['TEMP_IMAGES_ON_PAGE'][] = $output;
                if ($this->gifBuilder->dontCheckForExistingTempFile || !$this->gifBuilder->file_exists_typo3temp_file($output, $input)) {
                    $ret = $this->gifBuilder->imageMagickExec($tempFileName, $output, $paramsFinal, 0);
                    if ($this->logger) {
                        $this->logger->info(sprintf('CSM - Execute (%s) returned (%s)', $paramsFinal, $ret));
                    }
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