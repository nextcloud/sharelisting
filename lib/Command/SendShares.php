<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2018 Roeland Jago Douma <roeland@famdouma.nl>
// SPDX-FileCopyrightText: 2022 Solution Libre SAS
// SPDX-FileContributor: Florent Poinsaut <florent@solution-libre.fr>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\ShareListing\Command;

use OCA\ShareListing\Service\ReportSender;
use OCA\ShareListing\Service\SharesList;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @psalm-api
 */
class SendShares extends AbstractCommand {
	public function __construct(
		private readonly IUserManager $userManager,
		private readonly ReportSender $reportSender,
		SharesList $sharesList,
	) {
		parent::__construct($sharesList);
	}

	public function configure(): void {
		parent::configure();

		$this->setName('sharing:send')
			->setDescription('Send list who has access to shares by owner')
			->addOption(
				'diff',
				'd',
				InputOption::VALUE_NONE,
				'Create a differential report in json format from the last available report'
			)
			->addOption(
				'recipients',
				'r',
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'Recipients users of generated reports'
			)
			->addOption(
				'target-path',
				'x',
				InputOption::VALUE_REQUIRED,
				'Generated reports will be stored on this path'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->checkAllRequiredOptionsAreNotEmpty($input);

		[$user, $path, $token, $filter] = $this->getOptions($input);
		$diff = $input->getOption('diff');
		$recipients = $input->getOption('recipients');
		$targetPath = $input->getOption('target-path');

		$dateTime = new \DateTimeImmutable();

		foreach ($recipients as $recipient) {
			$this->reportSender->createReport(
				$recipient,
				$targetPath,
				$dateTime,
				$user,
				$filter,
				$path,
				$token
			);

			if ($diff) {
				$this->reportSender->diff($recipient, $targetPath);
			}

			$this->reportSender->sendReport($recipient, $dateTime);
		}
		return 0;
	}

	protected function checkAllRequiredOptionsAreNotEmpty(InputInterface $input): void {
		$errors = [];

		if (!$input->getOption('target-path')) {
			$errors[] = 'The required option --target-path is not set or is empty.';
		}

		$recipients = $this->getDefinition()->getOption('recipients');

		/** @var InputOption $option */
		foreach ([$recipients] as $option) {
			$name = $option->getName();
			$values = $input->getOption($name);

			if ($values === null || $values === '' || ($option->isArray() && empty($values))) {
				$errors[] = sprintf('The required option --%s is not set or is empty.', $name);
			}

			foreach ($values as $value) {
				if (!$this->userManager->userExists($value)) {
					$errors[] = sprintf('The recipient user %s does not exist.', $value);
				}
			}
		}

		if (count($errors)) {
			throw new \InvalidArgumentException(implode("\n\n", $errors));
		}
	}
}
