<?php
namespace Ishikawakun\Falfocusarea\Utility;

class LogUtility {
    /**
     * @param mixed $value
     * @return string
     */
    public static function array2string($value) {
        $ret = '';
        if (!is_array($value)) {
            return $ret;
        }

        foreach ($value as $key => $val) {
            $ret .= '"' . (string)$key . '" => (';
            if (is_array($val)) {
                $ret .= self::array2string($val);
            } else {
                $ret .= '"' . (string)$val . '"';
            }
            $ret .= ')' . PHP_EOL;
        }

        return $ret;
    }
}