<?php

class shopYmlupdatePlugin extends shopPlugin {

    private static $developer = false;

    private static function uuid() {
        $uuid = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x', // 32 bits for "time_low"
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), // 16 bits for "time_mid"
                mt_rand(0, 0xffff), // 16 bits for "time_hi_and_version",
                // four most significant bits holds version number 4
                mt_rand(0, 0x0fff) | 0x4000, // 16 bits, 8 bits for "clk_seq_hi_res",
                // 8 bits for "clk_seq_low",
                // two most significant bits holds zero and one for variant DCE1.1
                mt_rand(0, 0x3fff) | 0x8000, // 48 bits for "node"
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        return $uuid;
    }

    public function getHash($profile = 0) {
        $uuid = $this->getSettings('uuid');
        if (!is_array($uuid)) {
            if ($uuid) {
                $uuid = array(
                    0 => $uuid,
                );
            } else {
                $uuid = array();
            }
        }

        if ($profile) {
            $updated = false;
            if ((count($uuid) == 1) && isset($uuid[0])) {
                $uuid[$profile] = $uuid[0];
                $updated = true;
            } elseif (!isset($uuid[$profile])) {
                $uuid[$profile] = self::uuid();
                $updated = true;
            }
            if ($updated) {
                $this->saveSettings(array('uuid' => $uuid));
            }
        }
        return ifset($uuid[$profile]);
    }

}
