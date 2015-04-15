<?php
namespace Ishikawakun\Falfocusarea\Service;

use TYPO3\CMS\Core\SingletonInterface;

class FocusAlgorithmService implements SingletonInterface {
    /**
     * @param int $width
     * @param int $height
     * @return array
     */
    protected function calcAspectRatio($width, $height) {
        // FIXME: stub
        return array(4, 3);
    }

    public function processConfiguration() {

    }
}