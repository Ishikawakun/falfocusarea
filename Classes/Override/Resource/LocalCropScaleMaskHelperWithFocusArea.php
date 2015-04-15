<?php
namespace Ishikawakun\Falfocusarea\Override\Resource;

use Ishikawakun\Falfocusarea\Utility\LogUtility;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Processing\LocalCropScaleMaskHelper;
use TYPO3\CMS\Core\Resource\Processing\TaskInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class LocalCropScaleMaskHelperWithFocusArea extends LocalCropScaleMaskHelper {
    /**
     * This method actually does the processing of files locally
     *
     * Takes the original file (for remote storages this will be fetched from the remote server),
     * does the IM magic on the local server by creating a temporary typo3temp/ file,
     * copies the typo3temp/ file to the processing folder of the target storage and
     * removes the typo3temp/ file.
     *
     * The returned array has the following structure:
     *   width => 100
     *   height => 200
     *   filePath => /some/path
     *
     * @param TaskInterface $task
     * @return array|NULL
     */
    public function process(TaskInterface $task) {
        $result = NULL;
        $targetFile = $task->getTargetFile();
        $sourceFile = $task->getSourceFile();

        $originalFileName = $sourceFile->getForLocalProcessing(FALSE);
        /** @var $gifBuilder \TYPO3\CMS\Frontend\Imaging\GifBuilder */
        $gifBuilder = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Imaging\\GifBuilder');
        $gifBuilder->init();
        $gifBuilder->absPrefix = PATH_site;

        $configuration = $targetFile->getProcessingConfiguration();
        $configuration['additionalParameters'] = $this->modifyImageMagickStripProfileParameters($configuration['additionalParameters'], $configuration);

        if (empty($configuration['fileExtension'])) {
            $configuration['fileExtension'] = $task->getTargetFileExtension();
        }

        $options = $this->getConfigurationForImageCropScaleMask($targetFile, $gifBuilder);

        // Focal point handling starts here
        if (isset($GLOBALS['TYPO3_CONF_VARS']['GFX']['advanced']) && $GLOBALS['TYPO3_CONF_VARS']['GFX']['advanced']) {
            // Set debug flag
            $gfx_advanced_debug = TRUE;
            // Get logger instance
            $logger = NULL;
            if ($gfx_advanced_debug) {
                /** @var \TYPO3\CMS\Core\Log\Logger $logger */
                $logger = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Core\Log\LogManager')->getLogger(__CLASS__);
            }

            // Get file metadata
            $fileMetaData = $sourceFile->_getMetaData();

            $logger->info('Configuration Array: ' . LogUtility::array2string($configuration));

            // Check meta data width and height
            if (!isset($fileMetaData['width']) || !isset($fileMetaData['height'])) {
                if ($logger) {
                    // Generate meta data debug output
                    $width = isset($fileMetaData['width']) ? $fileMetaData['width'] : 0;
                    $height = isset($fileMetaData['width']) ? $fileMetaData['height'] : 0;
                    $logger->info(sprintf('CSM - Size information of image "%s" missing (w: %d, h: %d)', $originalFileName, $width, $height));
                }
            }

            // Check focus area corners in metadata and fallback if necessary
            $focusArea = array(
                'focal_x_min' => isset($fileMetaData['focal_x_min']) ? $fileMetaData['focal_x_min'] : 0,
                'focal_x_max' => isset($fileMetaData['focal_x_max']) ? $fileMetaData['focal_x_max'] : 0,
                'focal_y_min' => isset($fileMetaData['focal_y_min']) ? $fileMetaData['focal_y_min'] : 0,
                'focal_y_max' => isset($fileMetaData['focal_y_max']) ? $fileMetaData['focal_y_max'] : 0,
            );

            // Calculate focus area side lengths
            $focusArea['width'] = $focusArea['focal_x_max'] - $focusArea['focal_x_min'];
            $focusArea['height'] = $focusArea['focal_y_max'] - $focusArea['focal_y_min'];

            // Check if focus area is usable (arbitrary minimal condition)
            if ($focusArea['width'] <= 10 || $focusArea['height'] <= 10) {
                if ($logger) {
                    $logger->info(sprintf('CSM - Focus area fallback for image "%s"', $originalFileName));
                }

                // Fallback if necessary

                // TODO: design fallback mechanism (weighted something something)
            } else {
                // Interpret rescaling case based on configuration data
                if ($logger) {
                    $logger->info(sprintf('CSM - Configuration data (w: %d, h: %d)', $configuration['width'], $configuration['height']));
                }

                if ($configuration['width'] !== 0 && $configuration['height'] !== 0) {

                    // Case A: target width and target height present

                    // |-> Aa: one or both target values < corresponding focus area value

                } elseif ($configuration['width'] !== 0 && $configuration ['height'] === 0) {

                    if ($logger) {
                        $logger->info(sprintf('CSM - Height is actually 0'));
                    }

                    // Case B: only target width present

                    // |-> Ba: target value < corresponding focus area value

                } elseif ($configuration['width'] === 0 && $configuration['height'] !== 0) {

                    // Case C: only target height present

                    // |-> Ca: target value < corresponding focus area value

                }
            }
        }

        // Normal situation (no masking)
        if (!(is_array($configuration['maskImages']) && $GLOBALS['TYPO3_CONF_VARS']['GFX']['im'])) {
            // the result info is an array with 0=width,1=height,2=extension,3=filename
            $result = $gifBuilder->imageMagickConvert(
                $originalFileName,
                $configuration['fileExtension'],
                $configuration['width'],
                $configuration['height'],
                $configuration['additionalParameters'],
                $configuration['frame'],
                $options
            );
        } else {
            $targetFileName = $this->getFilenameForImageCropScaleMask($task);
            $temporaryFileName = $gifBuilder->tempPath . $targetFileName;
            $maskImage = $configuration['maskImages']['maskImage'];
            $maskBackgroundImage = $configuration['maskImages']['backgroundImage'];
            if ($maskImage instanceof FileInterface && $maskBackgroundImage instanceof FileInterface) {
                $temporaryExtension = 'png';
                if ($GLOBALS['TYPO3_CONF_VARS']['GFX']['im_mask_temp_ext_gif']) {
                    // If ImageMagick version 5+
                    $temporaryExtension = $gifBuilder->gifExtension;
                }
                $tempFileInfo = $gifBuilder->imageMagickConvert(
                    $originalFileName,
                    $temporaryExtension,
                    $configuration['width'],
                    $configuration['height'],
                    $configuration['additionalParameters'],
                    $configuration['frame'],
                    $options
                );
                if (is_array($tempFileInfo)) {
                    $maskBottomImage = $configuration['maskImages']['maskBottomImage'];
                    if ($maskBottomImage instanceof FileInterface) {
                        $maskBottomImageMask = $configuration['maskImages']['maskBottomImageMask'];
                    } else {
                        $maskBottomImageMask = NULL;
                    }

                    //	Scaling:	****
                    $tempScale = array();
                    $command = '-geometry ' . $tempFileInfo[0] . 'x' . $tempFileInfo[1] . '!';
                    $command = $this->modifyImageMagickStripProfileParameters($command, $configuration);
                    $tmpStr = $gifBuilder->randomName();
                    //	m_mask
                    $tempScale['m_mask'] = $tmpStr . '_mask.' . $temporaryExtension;
                    $gifBuilder->imageMagickExec($maskImage->getForLocalProcessing(TRUE), $tempScale['m_mask'], $command);
                    //	m_bgImg
                    $tempScale['m_bgImg'] = $tmpStr . '_bgImg.miff';
                    $gifBuilder->imageMagickExec($maskBackgroundImage->getForLocalProcessing(), $tempScale['m_bgImg'], $command);
                    //	m_bottomImg / m_bottomImg_mask
                    if ($maskBottomImage instanceof FileInterface && $maskBottomImageMask instanceof FileInterface) {
                        $tempScale['m_bottomImg'] = $tmpStr . '_bottomImg.' . $temporaryExtension;
                        $gifBuilder->imageMagickExec($maskBottomImage->getForLocalProcessing(), $tempScale['m_bottomImg'], $command);
                        $tempScale['m_bottomImg_mask'] = ($tmpStr . '_bottomImg_mask.') . $temporaryExtension;
                        $gifBuilder->imageMagickExec($maskBottomImageMask->getForLocalProcessing(), $tempScale['m_bottomImg_mask'], $command);
                        // BEGIN combining:
                        // The image onto the background
                        $gifBuilder->combineExec($tempScale['m_bgImg'], $tempScale['m_bottomImg'], $tempScale['m_bottomImg_mask'], $tempScale['m_bgImg']);
                    }
                    // The image onto the background
                    $gifBuilder->combineExec($tempScale['m_bgImg'], $tempFileInfo[3], $tempScale['m_mask'], $temporaryFileName);
                    $tempFileInfo[3] = $temporaryFileName;
                    // Unlink the temp-images...
                    foreach ($tempScale as $tempFile) {
                        if (@is_file($tempFile)) {
                            unlink($tempFile);
                        }
                    }
                }
                $result = $tempFileInfo;
            }
        }
        // check if the processing really generated a new file
        if ($result !== NULL) {
            if ($result[3] !== $originalFileName) {
                $result = array(
                    'width' => $result[0],
                    'height' => $result[1],
                    'filePath' => $result[3],
                );
            } else {
                // No file was generated
                $result = NULL;
            }
        }

        return $result;
    }
}