<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Solution Libre SAS
 * @copyright Copyright (c) 2018 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Florent Poinsaut <florent@solution-libre.fr>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author John Molakvoæ <skjnldsv@protonmail.com>
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

namespace OCA\ShareListing\Command;

use OCA\ShareListing\Service\ReportSender;
use OCA\ShareListing\Service\SharesList;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SendShares extends AbstractCommand {
	/** @var IUserManager */
	private $userManager;

	/** @var ReportSender */
	private $reportSender;

	public function __construct(
		IUserManager $userManager,
		ReportSender $reportSender,
		SharesList $sharesList
	) {
		parent::__construct($sharesList);

		$this->userManager = $userManager;
		$this->reportSender = $reportSender;
	}

	public function configure() {
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

	protected function checkAllRequiredOptionsAreNotEmpty(InputInterface $input)
    {
        $errors = [];

		if (!$input->getOption('target-path')) {
			$errors[] = 'The required option --target-path is not set or is empty.';
		}

        $recipients = $this->getDefinition()->getOption('recipients');

        /** @var InputOption $recipient */
        foreach ([$recipients] as $recipient) {
            $name = $recipient->getName();
            $values = $input->getOption($name);

            if ($values === null || $values === '' || ($recipient->isArray() && empty($values))) {
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
