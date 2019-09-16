<?php
/** @var \OCP\IL10N $l */
/** @var array $_ */

script('spreed', 'talk');

style('spreed', 'merged');
\OC::$server->getEventDispatcher()->dispatch('\OCP\Collaboration\Resources::loadAdditionalScripts');
?>