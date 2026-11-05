<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeSchulungen\BackgroundJob;

use OCA\Leiterrunde\Db\PersonMapper;
use OCA\LeiterrundeSchulungen\Db\TrainingTypeMapper;
use OCA\Leiterrunde\Service\NotificationService;
use OCA\Leiterrunde\Service\PermissionService;
use OCA\Leiterrunde\Service\SettingsService;
use OCA\Leiterrunde\Service\StateService;
use OCA\LeiterrundeSchulungen\Service\TrainingStatusService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class TrainingCheckJob extends TimedJob {
	private const NOTIFY_STATES = ['warn', 'fail', 'missing'];

	public function __construct(
		ITimeFactory $time,
		private PersonMapper $personMapper,
		private TrainingTypeMapper $typeMapper,
		private TrainingStatusService $statusService,
		private StateService $stateService,
		private NotificationService $notificationService,
		private SettingsService $settings,
		private \OCA\LeiterrundeSchulungen\Service\ModuleConfigService $moduleConfig,
		private IUserManager $userManager,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(24 * 3600);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
	}

	protected function run($argument): void {
		$at = new \DateTimeImmutable('today');
		$persons = $this->personMapper->findByStatuses(array_map('trim', explode(',', $this->moduleConfig->get('scope_statuses'))));
		$requiredTypes = array_filter(
			$this->typeMapper->findAll(true),
			static fn ($t) => $t->getRequired()
		);
		foreach ($persons as $person) {
			try {
				$this->checkPerson($person, $requiredTypes, $at);
			} catch (\Throwable $e) {
				$this->logger->warning('Leiterrunde TrainingCheckJob: Fehler bei Person ' . $person->getId() . ': ' . $e->getMessage());
			}
		}
	}

	private function checkPerson($person, array $requiredTypes, \DateTimeImmutable $at): void {
		$status = $this->statusService->personStatus($person, $at);
		foreach ($status['types'] as $t) {
			$this->handleCheck(
				$person, 'type:' . $t['type_id'], $t['state'],
				$this->settings->notifyGroups('training'),
				'Schulung „' . $t['name'] . '“',
			);
		}
		$this->handleCheck(
			$person, 'hours', $status['hours']['state'],
			$this->settings->notifyGroups('hours'),
			'Stundenkonto',
		);
		$fz = $this->statusService->fzStatus($person, $at);
		$this->handleCheck(
			$person, 'fz', $fz['state'],
			[PermissionService::G_FZ],
			'Extended certificate of conduct',
			false
		);
	}

	private function handleCheck($person, string $checkKey, string $newState, array $groups, string $checkLabel, bool $notifyPerson = true): void {
		$old = $this->stateService->transition('person', $person->getId(), $checkKey, $newState);
		if ($old === $newState) {
			return;
		}
		if (!in_array($newState, self::NOTIFY_STATES, true)) {
			return;
		}
		$extraUid = ($notifyPerson && $this->settings->getBool('notify_person')) ? $person->getNcUserId() : null;
		$name = trim($person->getFirstName() . ' ' . $person->getLastName());
		$subject = $checkKey === 'fz' ? 'fz_state' : ($checkKey === 'hours' ? 'hours_state' : 'training_state');
		$this->notificationService->notifyState(
			$subject,
			[
				'person_id' => $person->getId(),
				'first_name' => $person->getFirstName(),
				'last_name' => $person->getLastName(),
				'check_label' => $checkLabel,
				'state' => $newState,
			],
			$groups,
			$extraUid,
			$checkLabel . ': ' . $this->stateLabel($newState),
			"Hallo,\n\nbei {$name} hat der Check „{$checkLabel}“ den Status „{$this->stateLabel($newState)}“ erreicht.\n\nPlease check in the Leiterrunde app.\n",
		);
	}

	private function stateLabel(string $state): string {
		return match ($state) {
			'ok' => 'OK',
			'warn' => 'Warnung',
			'fail' => 'Abgelaufen',
			'missing' => 'Fehlt',
			default => $state,
		};
	}
}
