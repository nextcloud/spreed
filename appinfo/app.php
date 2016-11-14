<?php
/**
 * @author Jan-Christoph Borchardt, http://jancborchardt.net
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

\OC::$server->getNavigationManager()->add(function() {
	$l = \OC::$server->getL10N('spreed');
	$g = \OC::$server->getURLGenerator();

	return [
		'id' => 'spreed',
		'order' => 3,
		'href' => $g->linkToRoute('spreed.page.index'),
		'icon' => $g->imagePath('spreed', 'app.svg'),
		'name' => $l->t('Calls'),
	];
});


$manager = \OC::$server->getNotificationManager();
$manager->registerNotifier(function() {
	return \OC::$server->query('OCA\Spreed\Notification\Notifier');
}, function() {
	$l = \OC::$server->getL10N('spreed');

	return [
		'id' => 'spreed',
		'name' => $l->t('Spreed'),
	];
});

\OCP\Util::connectHook('OC_User', 'post_deleteUser', \OCA\Spreed\Util::class, 'deleteUser');

OC_App::registerPersonal('spreed', 'personal');
