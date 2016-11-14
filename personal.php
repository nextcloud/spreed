<?php

use OCA\Spreed\Util;

$tmpl = new OCP\Template('spreed', 'settings-personal');
$config = \OC::$server->getConfig();
$uid = \OC::$server->getUserSession()->getUser()->getUID();

$settings = Util::getTurnSettings($config, $uid);
$tmpl->assign('turnSettings', $settings);
return $tmpl->fetchPage();
