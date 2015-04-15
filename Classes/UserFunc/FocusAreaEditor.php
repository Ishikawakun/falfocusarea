<?php
namespace Ishikawakun\Falfocusarea\UserFunc;

use TYPO3\CMS\Backend\Form\FormEngine;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FocusAreaEditor {
    /**
     * @param array $PA
     * @param FormEngine $fObj
     */
    public function tcaField($PA, $fObj) {
        $fObj->addStyleSheet('jcrop', '/typo3conf/ext/falfocusarea/Resources/Public/Jcrop/css/jquery.Jcrop.css');

        /** @var PageRenderer $pageRenderer */
        $pageRenderer = $GLOBALS['SOBE']->doc->getPageRenderer();

        /** @var FileRepository $fileRepository */
        $fileRepository = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Resource\\FileRepository');
        $sys_file_metadata = $PA['row'];

        if (isset($sys_file_metadata['file']) && $sys_file_metadata['file'] != 0) {
            $sys_file = $fileRepository->findByIdentifier($sys_file_metadata['file']);
        }

        if ($sys_file instanceof File) {
            $pUrl = $sys_file->getPublicUrl();

            $hiddenXminField = $fObj->getSingleHiddenField('sys_file_metadata', 'focal_x_min', $PA['row']);
            $hiddenXmaxField = $fObj->getSingleHiddenField('sys_file_metadata', 'focal_x_max', $PA['row']);

            $hiddenYminField = $fObj->getSingleHiddenField('sys_file_metadata', 'focal_y_min', $PA['row']);
            $hiddenYmaxField = $fObj->getSingleHiddenField('sys_file_metadata', 'focal_y_max', $PA['row']);

            $pageRenderer->addJsFile('/typo3conf/ext/falfocusarea/Resources/Public/Jcrop/js/jquery.color.js', 'text/javascript', FALSE, TRUE, '', TRUE);
            $pageRenderer->addJsFile('/typo3conf/ext/falfocusarea/Resources/Public/Jcrop/js/jquery.Jcrop.js', 'text/javascript', FALSE, TRUE, '', TRUE);

            $formField = '
            <div id="focusareaeditor" class="focusareaeditor">
                <img src="/' . $pUrl . '" style="max-width: 100%;"/>' . PHP_EOL .
            $hiddenXminField . PHP_EOL .
            $hiddenXmaxField . PHP_EOL .
            $hiddenYminField . PHP_EOL .
            $hiddenYmaxField . PHP_EOL .
            '</div>
            <script type="text/javascript">
                +function($) {
                    var disabledXminField;
                    var disabledYminField;
                    var disabledXmaxField;
                    var disabledYmaxField;

                    var hiddenXminField;
                    var hiddenYminField;
                    var hiddenXmaxField;
                    var hiddenYmaxField;

                    var width = ' . (int)$sys_file_metadata['width'] . ';

                    var height = ' . (int)$sys_file_metadata['height'] .';

                    var initXminValue = ' . (int)$sys_file_metadata['focal_x_min'] . ';
                    var initXmaxValue = ' . (int)$sys_file_metadata['focal_x_max'] . ';

                    var initYminValue = ' . (int)$sys_file_metadata['focal_y_min'] . ';
                    var initYmaxValue = ' . (int)$sys_file_metadata['focal_y_max'] . ';

                    function changeFields(c) {
                        // variables can be accessed here as
                        // c.x, c.y, c.x2, c.y2, c.w, c.h
                        hiddenXminField.val(Math.floor(c.x)); disabledXminField.html(Math.floor(c.x));
                        hiddenXmaxField.val(Math.floor(c.x2)); disabledXmaxField.html(Math.floor(c.x2));

                        hiddenYminField.val(Math.floor(c.y)); disabledYminField.html(Math.floor(c.y));
                        hiddenYmaxField.val(Math.floor(c.y2)); disabledYmaxField.html(Math.floor(c.y2));
                    }

                    $(document).ready(function() {
                        // collect disabled form elements
                        disabledXminField = $("div[id^=\'TCEFORMS_sys_file_metadata_70\'] span.nobr").eq(0);
                        disabledXmaxField = $("div[id^=\'TCEFORMS_sys_file_metadata_70\'] span.nobr").eq(1);

                        disabledYminField = $("div[id^=\'TCEFORMS_sys_file_metadata_80\'] span.nobr").eq(0);
                        disabledYmaxField = $("div[id^=\'TCEFORMS_sys_file_metadata_80\'] span.nobr").eq(1);

                        // collect hidden form elements
                        hiddenXminField = $("input[name$=\'[focal_x_min]\']");
                        hiddenXmaxField = $("input[name$=\'[focal_x_max]\']");

                        hiddenYminField = $("input[name$=\'[focal_y_min]\']");
                        hiddenYmaxField = $("input[name$=\'[focal_y_max]\']");

                        // Start Jcrop plugin
                        $("#focusareaeditor > img").Jcrop({
                            onSelect: changeFields,
                            onChange: changeFields,
                            trueSize: [width,height],
                            setSelect: [initXminValue, initYminValue, initXmaxValue, initYmaxValue]
                        });
                    });
                }(TYPO3.jQuery);
            </script>
            ';
        } else {
            $formField = '
            <div id="focusareaeditor" class="focusareaeditor">
                <p>Fehler beim laden der Bilddatei!</p>
            </div>
            ';
        }
        return $formField;
    }
}