<?php
declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

\OCP\Util::addStyle('leiterrunde', 'leiterrunde');
\OCP\Util::addScript('leiterrunde_schulungen', 'settings');
?>
<div class="section lr" id="lr-schulungen-settings">
	<h2><?php p($l->t('Training – Rules')); ?></h2>
	<div id="lr-rules-form"></div>
	<button class="lr-btn" id="lr-rules-save"><?php p($l->t('Save')); ?></button>
</div>
