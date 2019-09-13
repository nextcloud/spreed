<?php
/** @var \OCP\IL10N $l */
/** @var array $_ */

script('spreed', 'talk');

\OC::$server->getEventDispatcher()->dispatch('\OCP\Collaboration\Resources::loadAdditionalScripts');
?>