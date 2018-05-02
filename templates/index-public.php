<?php
/** @var \OCP\IL10N $l */
/** @var array $_ */

vendor_script('select2/select2');
vendor_style('select2/select2');

style('spreed', 'style');
style('spreed', 'comments');
style('spreed', 'autocomplete');
script(
	'spreed',
	[
		'vendor/backbone/backbone-min',
		'vendor/backbone.radio/build/backbone.radio.min',
		'vendor/backbone.marionette/lib/backbone.marionette.min',
		'vendor/jshashes/hashes.min',
		'vendor/Caret.js/dist/jquery.caret.min',
		'vendor/At.js/dist/js/jquery.atwho.min',
		'models/chatmessage',
		'models/chatmessagecollection',
		'models/localstoragemodel',
		'models/room',
		'models/roomcollection',
		'views/callinfoview',
		'views/chatview',
		'views/editabletextlabel',
		'views/roomlistview',
		'views/sidebarview',
		'views/tabview',
		'richobjectstringparser',
		'simplewebrtc',
		'webrtc',
		'signaling',
		'connection',
		'app',
		'init',
	]
);
?>

<div id="notification-container">
	<div id="notification" style="display: none;"></div>
</div>
<div id="app" class="nc-enable-screensharing-extension" data-token="<?php p($_['token']) ?>">
	<script type="text/json" id="signaling-settings">
	<?php echo json_encode($_['signaling-settings']) ?>
	</script>
	<div id="app-content" class="participants-1">

		<header>
			<div id="header" class="spreed-public">
				<div id="header-left">
					<a href="<?php print_unescaped(link_to('', 'index.php')); ?>" title="" id="nextcloud" target="_blank">
						<div class="logo logo-icon svg">
							<h1 class="hidden-visually">
								<?php p($theme->getName() . ' ' . $l->t('Talk')); ?>
							</h1>
						</div>
					</a>
				</div>
				<div id="header-right">
				</div>
			</div>
		</header>

		<div id="app-content-wrapper">
			<button id="video-fullscreen" class="icon-fullscreen icon-white icon-shadow public" data-placement="bottom" data-toggle="tooltip" data-original-title="<?php p($l->t('Fullscreen (f)')) ?>"></button>

			<div id="video-speaking">

			</div>
			<div id="videos">
				<div class="videoView videoContainer hidden" id="localVideoContainer">
					<video id="localVideo"></video>
					<div class="avatar-container hidden">
						<div class="avatar"></div>
					</div>
					<div class="nameIndicator">
						<button id="mute" class="icon-audio icon-white icon-shadow" data-placement="top" data-toggle="tooltip" data-original-title="<?php p($l->t('Mute audio (m)')) ?>"></button>
						<button id="hideVideo" class="icon-video icon-white icon-shadow" data-placement="top" data-toggle="tooltip" data-original-title="<?php p($l->t('Disable video (v)')) ?>"></button>
						<button id="screensharing-button" class="app-navigation-entry-utils-menu-button icon-screen-off icon-white icon-shadow screensharing-disabled" data-placement="top" data-toggle="tooltip" data-original-title="<?php p($l->t('Share screen')) ?>"></button>
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
				<h2><?php p($l->t('Join a conversation or start a new one')) ?></h2>
				<p class="uploadmessage"></p>
			</div>
		</div>
	</div>
</div>
