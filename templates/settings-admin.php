<?php
script('spreed', ['settings-admin']);
?>

<div id="spreed" class="section">
    <form id="spreed_settings_form" class="spreed_settings">
        <h2 class="app-name">Spreed video calls</h2>
        <span id="spreed_settings_msg" class="msg"></span>
        <p>
            <label for="stun_server"><?php p($l->t('STUN server')) ?></label>
            <!-- TODO(fancycode): Should use CSS style to make input wider. -->
            <input type="text" style="width:300px" id="stun_server"
                   name="stun_server" placeholder="stunserver:port"
                   value="<?php p($_['stunServer']) ?>" />
            <p>
                <em><?php p($l->t('The STUN server is used to determine the public address of participants behind a router.')) ?></em>
            </p>
        </p>
    </form>
</div>
