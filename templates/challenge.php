<?php

/*
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

style('twofactor_email', 'style');

$rateLimited = $_['rateLimited'] ?? false;
$secondsRemaining = $_['secondsRemaining'] ?? 0;
$codeLength = $_['codeLength'] ?? 6;
?>

<img class="two-factor-icon two-factor-email-icon" src="<?php print_unescaped(image_path('twofactor_email', 'app.svg')); ?>" alt="">

<?php if ($rateLimited): ?>
<p class="warning">
	<?php if ($secondsRemaining > 0): ?>
		<?php p($l->t('Too many code requests. Please wait %d seconds before requesting a new code.', [$secondsRemaining])); ?>
	<?php else: ?>
		<?php p($l->t('Too many code requests. Please try again later.')); ?>
	<?php endif; ?>
</p>
<p><?php p($l->t('Enter the code that was previously sent to your email.')); ?></p>
<?php else: ?>
<p><?php p($l->t('Get the authentication code from your email inbox.')); ?></p>
<?php endif; ?>

<form method="POST" class="twofactor-email-form">
	<input type="text" minlength="<?php p($codeLength); ?>" maxlength="<?php p($codeLength); ?>" name="challenge" required="required" autofocus autocomplete="one-time-code" inputmode="numeric" autocapitalize="off" placeholder="<?php p($l->t('Authentication code')); ?>">
	<button class="primary two-factor-submit" type="submit">
		<?php p($l->t('Submit')); ?>
	</button>
</form>
