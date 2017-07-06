<?php
/** @var \OCP\IL10N $l */
/** @var array $_ */

vendor_script('select2/select2');
vendor_style('select2/select2');

style('spreed', 'style');
script(
	'spreed',
	[
		'vendor/backbone/backbone-min',
		'vendor/backbone.radio/build/backbone.radio.min',
		'vendor/backbone.marionette/lib/backbone.marionette.min',
		'models/room',
		'models/roomcollection',
		'views/roomlistview',
		'simplewebrtc',
		'webrtc',
		'signaling',
		'rooms',
		'app',
		'init',
	]
);
?>

<div id="notification-container">
	<div id="notification" style="display: none;"></div>
</div>
<div id="app" class="nc-enable-screensharing-extension" data-token="<?php p($_['token']) ?>">
	<div id="app-content" class="participants-1">

		<header>
			<div id="header" class="spreed-public">
				<div id="header-left">
					<a href="<?php print_unescaped(link_to('', 'index.php')); ?>" title="" id="nextcloud" target="_blank">
						<div class="logo-icon svg"></div>
					</a>
					<div class="header-appname-container">
						<h1 class="header-appname">
							<?php p($theme->getName()); ?>
						</h1>
					</div>
				</div>
				<div id="header-right">
					<div id="settings">
						<div id="guestName"><?php p($l->t('Guest')) ?></div>
						<input id="guestNameInput" class="hidden" type="text" maxlength="20" placeholder="<?php p($l->t('Guest')) ?>">
						<button id="guestNameConfirm" class="icon-confirm hidden"></button>
					</div>
				</div>
			</div>
		</header>

		<button id="video-fullscreen" class="icon-fullscreen-white public" data-placement="bottom" data-toggle="tooltip" data-original-title="<?php p($l->t('Fullscreen')) ?>"></button>

		<div id="video-speaking">

		</div>
		<div id="videos">
			<div class="videoView videoContainer hidden" id="localVideoContainer">
				<video id="localVideo"></video>
				<div class="avatar-container hidden">
					<div class="avatar"></div>
				</div>
				<div class="nameIndicator">
					<button id="mute" class="icon-audio-white" data-placement="top" data-toggle="tooltip" data-original-title="<?php p($l->t('Mute audio')) ?>"></button>
					<button id="hideVideo" class="icon-video-white" data-placement="top" data-toggle="tooltip" data-original-title="<?php p($l->t('Disable video')) ?>"></button>
					<button id="screensharing-button" class="app-navigation-entry-utils-menu-button icon-screen-off-white screensharing-disabled" data-placement="top" data-toggle="tooltip" data-original-title="<?php p($l->t('Share screen')) ?>"></button>
					<div id="screensharing-menu" class="app-navigation-entry-menu">
						<ul>
							<li>
								<button id="show-screen-button">
									<span class="icon-screen"></span>
									<span><?php p($l->t('Show your screen'));?></span>
								</button>
							</li>
							<li>
								<button id="stop-screen-button">
									<span class="icon-screen-off"></span>
									<span><?php p($l->t('Stop screensharing'));?></span>
								</button>
							</li>
						</ul>
					</div>
				</div>
			</div>
		</div>

		<div id="screens"></div>

		<div id="emptycontent">
			<div id="emptycontent-icon" class="icon-video"></div>
			<h2><?php p($l->t('Looking great today! :)')) ?></h2>
			<p class="uploadmessage"><?php p($l->t('Smile in 3… 2… 1!')) ?></p>
		</div>
	</div>
</div>
