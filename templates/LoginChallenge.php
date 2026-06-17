<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCP\IL10N;
use OCP\Util;

Util::addScript('twofactor_email', 'twofactor_email-login_challenge');
Util::addStyle('twofactor_email', 'twofactor_email-login_challenge');

/**
 * @var array $_ Template variables provided by Nextcloud
 * @var IL10N $l Nextcloud's translation object
 */

$codeLength = $_['codeLength']; // provided in Provider/TwoFactorEMail.php, so this fallback should never be used
if (!empty($codeLength)) {
	$minmax = " minlength=$codeLength maxlength=$codeLength";
} else {
	$minmax = '';
}

$newCodeWasSent = $_['newCodeWasSent']; // provided in Provider/TwoFactorEMail.php
$error = $_['error'] ?? null; // caught and passed in Provider/TwoFactorEMail.php
?>
<img class="two-factor-icon twofactor_email-challenge-icon"
	 src="<?php print_unescaped(image_path('twofactor_email', 'app.svg')); ?>" alt="Icon depicting a letter and a user">

<?php if ($error === 'no-email'): ?>
	<p class="warning">
		<?php p($l->t('An error occurred: No email address is configured in your personal settings. Please contact your administrator.')); ?>
	</p>
<?php elseif ($error === 'send-failed'): ?>
	<p class="warning">
		<?php p($l->t('The verification email could not be sent. Please try again later or contact your administrator.')); ?>
	</p>
<?php else: ?>
	<p><?php
		if ($newCodeWasSent) {
			p($l->t('A new authentication code was just sent. Please enter it:'));
		} else {
			p($l->t('Enter the authentication code that was sent to you:'));
		}
	?></p>
	<form method="POST" class="twofactor_email-challenge-form">
		<input type="text"<?= $minmax ?> name="challenge" required="required" autofocus autocomplete="one-time-code"
			   inputmode="numeric" autocapitalize="off" placeholder="<?php p($l->t('Authentication code')) ?>">
		<button class="primary two-factor-submit" type="submit">
			<?php p($l->t('Submit')); ?>
		</button>
	</form>
	<button type="button" class="twofactor_email-resend">
		<?php p($l->t('Send a new code')); ?>
	</button>
	<p class="twofactor_email-resend-status" aria-live="polite" hidden></p>
<?php endif; ?>
