<?php
script('spreed', ['settings-personal']);
?>

<div id="spreedSettings" class="section">
    <form id="spreed_settings_form" class="spreed_settings">
        <h2><?php p($l->t('Spreed video calls')); ?></h2>
        <p>
            <?php p($l->t('The TURN server is used to relay audio/video streams in cases where the participants can\'t connect directly to each other.')) ?>
        </p>
        <span id="spreed_settings_msg" class="msg"></span>
        <p>
            <label for="turn_server"><?php p($l->t('TURN server')) ?></label>
            <input type="text" id="turn_server"
                   name="turn_server" placeholder="turnserver:port"
                   value="<?php p($_['turnSettings']['server']) ?>" />
        </p>
        <p>
            <label for="turn_username"><?php p($l->t('Username')) ?></label>
            <input type="text" id="turn_username"
                   name="turn_username" placeholder="<?php p($l->t('Username')) ?>"
                   value="<?php p($_['turnSettings']['username']) ?>" />
        </p>
        <p>
            <label for="turn_password"><?php p($l->t('Password')) ?></label>
            <input type="text" id="turn_password"
                   name="turn_password" placeholder="<?php p($l->t('Password')) ?>"
                   value="<?php p($_['turnSettings']['password']) ?>" />
        </p>
        <p>
            <label for="turn_protocols"><?php p($l->t('Protocols')) ?></label>
            <select id="turn_protocols" name="turn_protocols">
                <option value="udp,tcp"
                    <?php p($_['turnSettings']['protocols'] === 'udp,tcp' ? 'selected' : '') ?>>
                    <?php p($l->t('udp and tcp')) ?><</option>
                <option value="udp"
                    <?php p($_['turnSettings']['protocols'] === 'udp' ? 'selected' : '') ?>>
                    <?php p($l->t('udp only')) ?><</option>
                <option value="tcp"
                    <?php p($_['turnSettings']['protocols'] === 'tcp' ? 'selected' : '') ?>>
                    <?php p($l->t('tcp only')) ?><</option>
            </select>
        </p>
    </form>
</div>
